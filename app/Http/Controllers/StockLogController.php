<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Services\InventoryHistoryService;
use App\Support\HistoricalDateRange;
use App\Support\SpreadsheetCsv;
use App\Support\StockLogBatchFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockLogController extends Controller
{
    private function checkAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (! in_array(session('role'), ['admin', 'staff'])) {
            return redirect()->route('login');
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        $historyRange = HistoricalDateRange::fromRequest($request);
        $this->validateFilters($request);
        $showEvidence = $this->showsEvidence($request);
        $stockLogs = $showEvidence
            ? app(InventoryHistoryService::class)->records($request, $historyRange, stockLogs: true)
            : $this->activeLogs($request, $historyRange)->paginate(20)->withQueryString();
        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();
        $medicines = Medicine::withTrashed()->orderBy('medicine_name')->get(['id', 'medicine_name', 'dosage']);

        return view('stock_logs.index', compact('stockLogs', 'healthUnits', 'historyRange', 'showEvidence', 'medicines'));
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'rhu_filter' => ['nullable', 'string', 'regex:/^(all|pho|[0-9]+)$/'],
            'medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'inventory_id' => ['nullable', 'integer', 'min:1'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'batch_input' => ['nullable', 'string', 'max:1000'],
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'in:addition,release,reservation,reversal'],
            'status' => ['nullable', 'in:active,cancelled,reversed,reversal,all'],
            'source' => ['nullable', 'in:stock_logs'],
            'movement_type' => ['nullable', 'in:addition,receipt,transfer_in,adjustment_in,dispensing,allocation,transfer_out,adjustment_out'],
        ]);
    }

    private function showsEvidence(Request $request): bool
    {
        return $request->boolean('evidence') || $request->anyFilled(['inventory_id', 'action', 'record_kind'])
            || in_array($request->input('type'), ['reservation', 'reversal'], true)
            || ($request->filled('status') && $request->input('status') !== 'active');
    }

    private function activeLogs(Request $request, HistoricalDateRange $range)
    {
        $unitId = session('health_unit_id');
        $facility = $request->input('rhu_filter');
        $phoId = $unitId === null ? HealthUnit::phoId() : null;
        if ($unitId === null && $facility && $facility !== 'all') {
            $unitId = $facility === 'pho' ? ($phoId ?? -1) : (int) $facility;
        }
        $query = StockLog::activeForReporting()->with(['medicine', 'healthUnit', 'addedByUser'])
            ->when($unitId !== null, fn ($q) => $q->where('health_unit_id', $unitId))
            ->when($unitId === null && ! $facility && $phoId, fn ($q) => $q->where('health_unit_id', '!=', $phoId))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(function ($search) use ($term) {
                    $search->whereHas('medicine', fn ($medicine) => $medicine->where('medicine_name', 'like', $term))
                        ->orWhereHas('addedByUser', fn ($staff) => $staff->where('name', 'like', $term)->orWhere('username', 'like', $term))
                        ->orWhere('batch_no', 'like', $term);
                });
            })
            ->when($request->input('type') === 'addition', fn ($q) => $q->additions())
            ->when($request->input('type') === 'release', fn ($q) => $q->releases())
            ->when($request->filled('movement_type'), fn ($q) => $q->where('movement_type', $request->input('movement_type')))
            ->when($request->filled('medicine_id'), fn ($q) => $q->where('medicine_id', $request->input('medicine_id')))
            ->when($request->filled('batch_no'), fn ($q) => $q->where('batch_no', $request->input('batch_no')))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('medicine',
                fn ($medicine) => $medicine->where('category', $request->input('category'))));
        StockLogBatchFilter::apply($query, 'batch_no', $request->input('batch_input'));
        $range->apply($query, 'date_added');

        return $query->orderByDesc('date_added')->orderByDesc('id');
    }

    public function create()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('stock_logs.index')
                ->with('error', 'Stock addition is only available at the RHU level.');
        }

        $healthUnitId = session('health_unit_id');

        $medicines = Medicine::orderBy('medicine_name')->get();

        $inventoryData = Inventory::where('health_unit_id', $healthUnitId)
            ->selectRaw('medicine_id, COALESCE(SUM(available_stock), 0) as total_stock')
            ->groupBy('medicine_id')
            ->pluck('total_stock', 'medicine_id')
            ->toArray();

        return view('stock_logs.create', compact('medicines', 'inventoryData'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('stock_logs.index')
                ->with('error', 'Stock addition is only available at the RHU level.');
        }

        $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'quantity_added' => 'required|integer|min:1',
            'date_added' => 'required|date|before_or_equal:today',
            'expiration_date' => 'nullable|date',
            'batch_no' => 'nullable|string|max:100',
            'storage_location' => 'nullable|string|max:255',
            'pack_type' => 'nullable|string|max:50',
            'pieces_per_pack' => 'nullable|integer|min:1',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $medicine = Medicine::findOrFail($request->medicine_id);
        $healthUnitId = session('health_unit_id');

        [$stockLog, $inventory] = DB::transaction(function () use ($request, $medicine, $healthUnitId) {
            HealthUnit::whereKey($healthUnitId)->lockForUpdate()->firstOrFail();
            $inventory = Inventory::firstOrCreate([
                'medicine_id' => $medicine->id, 'health_unit_id' => $healthUnitId,
                'batch_no' => $request->batch_no ?: null, 'expiration_date' => $request->expiration_date ?: null,
            ], ['available_stock' => 0, 'allocated_stock' => 0, 'storage_location' => $request->storage_location,
                'pack_type' => $request->pack_type, 'pieces_per_pack' => $request->pieces_per_pack]);

            $stockLog = StockLog::create([
                'medicine_id' => $medicine->id,
                'health_unit_id' => $healthUnitId,
                'quantity_added' => $request->quantity_added,
                'inventory_id' => $inventory->id,
                'movement_type' => 'addition',
                'batch_no' => $request->batch_no,
                'storage_location' => $request->storage_location,
                'date_added' => $request->date_added,
                'added_by' => session('user_id'),
                'remarks' => 'Added — '.($request->filled('remarks') ? trim($request->remarks) : 'Stock entry'),
            ]);

            $inventory->increment('available_stock', $request->quantity_added);

            return [$stockLog, $inventory->fresh()];
        });

        $logParts = [];
        $logParts[] = 'Added stock for '.$medicine->medicine_name;
        $logParts[] = 'with quantity '.$stockLog->quantity_added;
        if ($stockLog->storage_location) {
            $logParts[] = '| Storage: '.$stockLog->storage_location;
        }
        if ($inventory->pack_type && $inventory->pieces_per_pack) {
            $logParts[] = '| Packaging: '.$inventory->pieces_per_pack.' per '.$inventory->pack_type;
        }
        $logParts[] = '. New available stock: '.$inventory->available_stock.'.';
        if ($request->filled('remarks')) {
            $logParts[] = 'Reason: '.trim($request->remarks);
        }

        ActivityLogger::log(
            'Stock Logs',
            'Create',
            implode(' ', $logParts)
        );

        return redirect()->route('stock_logs.index')->with('success', 'Stock added successfully.');
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $historyRange = HistoricalDateRange::fromRequest($request);
        $this->validateFilters($request);
        $showEvidence = $this->showsEvidence($request);
        $stockLogs = $showEvidence
            ? app(InventoryHistoryService::class)->records($request, $historyRange, paginate: false, stockLogs: true)
            : $this->activeLogs($request, $historyRange)->get();

        $filename = 'stock_logs_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($stockLogs, $showEvidence) {
            $handle = fopen('php://output', 'w');

            $headers = [
                'ID',
                'Medicine',
                'Batch No.',
                'Storage Location',
                'Quantity Added',
                'Date Added',
                'Added By',
                'RHU',
                'Remarks',
            ];
            if ($showEvidence) {
                array_push($headers, 'Movement', 'Status', 'Reference', 'Available change', 'Reserved change');
            }
            SpreadsheetCsv::writeRow($handle, $headers);

            foreach ($stockLogs as $log) {
                $dispensingReference = $log->dispensing_record_id !== null ? 'Dispensing #'.$log->dispensing_record_id : null;
                $remarks = $log->report_remarks ?? $dispensingReference ?? '';
                if ($showEvidence) {
                    SpreadsheetCsv::writeRow($handle, [
                        $log->event_source.'-'.$log->event_id, $log->medicine_name ?? '', $log->batch_no ?? '',
                        $log->storage_location ?? '', $log->quantity_added, substr((string) $log->date_added, 0, 10),
                        $log->actor_name ?? '', $log->facility_name ?? '', $remarks, $log->event_label,
                        $log->status_label, $dispensingReference ?? $log->mechanism, $log->available_delta, $log->allocated_delta,
                    ]);
                    continue;
                }
                SpreadsheetCsv::writeRow($handle, [
                    $log->id,
                    $log->medicine->medicine_name ?? '',
                    $log->batch_no ?? '',
                    $log->storage_location ?? '',
                    $log->quantity_added,
                    $log->date_added,
                    $log->addedByUser->name ?? '',
                    $log->healthUnit->name ?? '',
                    $remarks,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function autocompleteMedicines(Request $request)
    {
        $search = trim($request->get('q', ''));
        $recommendations = $request->boolean('recommendations') && $search === '';
        if (! $recommendations && strlen($search) < 2) {
            return response()->json([]);
        }

        $medicines = Medicine::where(function ($q) use ($search) {
            $q->where('medicine_name', 'like', "%{$search}%")
                ->orWhere('dosage', 'like', "%{$search}%");
        })
            ->orderBy('medicine_name')
            ->limit($recommendations ? 6 : 15)
            ->get(['id', 'medicine_name', 'dosage', 'dosage_form', 'category', 'unit', 'requires_prescription']);

        $results = $medicines->map(function ($m) {
            $text = $m->medicine_name;
            if ($m->dosage) {
                $text .= ' - '.$m->dosage;
            }

            return [
                'id' => $m->id,
                'text' => $text,
                'dosage' => $m->dosage,
                'dosage_form' => $m->dosage_form,
                'category' => $m->category,
                'unit' => $m->unit,
                'requires_prescription' => $m->requires_prescription,
            ];
        });

        return response()->json($results);
    }
}
