<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Support\HistoricalDateRange;
use Carbon\CarbonInterface;
use App\Support\SpreadsheetCsv;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
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

    public function index()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        return view('reports.index');
    }

    /**
     * Parse batch input into a list of matching batch numbers.
     */
    private function parseBatchInput(Request $request, string $modelClass): array
    {
        $raw = (string) $request->input('batch_input', '');
        if (trim($raw) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $raw));
        $parts = array_values(array_filter($parts, fn ($v) => $v !== ''));

        $candidates = [];

        foreach ($parts as $part) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
                $start = (int) $m[1];
                $end = (int) $m[2];

                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }

                if ($end - $start > 1000) {
                    $end = $start + 1000;
                }

                for ($i = $start; $i <= $end; $i++) {
                    $candidates[] = (string) $i;
                }
            } else {
                $candidates[] = $part;
            }
        }

        $candidates = array_values(array_unique($candidates));

        if (empty($candidates)) {
            return [];
        }

        $query = $modelClass::query()->where(function ($q) use ($candidates) {
            foreach ($candidates as $v) {
                $q->orWhere(function ($sub) use ($v) {
                    $sub->where('batch_no', $v)
                        ->orWhere('batch_no', 'like', '%-'.$v)
                        ->orWhere('batch_no', 'like', '%-0'.$v)
                        ->orWhere('batch_no', 'like', '%-00'.$v);
                });
            }
        });

        return $query->distinct()->pluck('batch_no')->toArray();
    }

    // ========== INVENTORY REPORT ==========
    public function inventoryReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $inventories = $this->buildInventoryQuery($request)->get();

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('reports.inventory', compact('inventories', 'healthUnits'));
    }

    public function inventoryCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $inventories = $this->buildInventoryQuery($request)->get();

        $headers = ['ID', 'Batch No.', 'Medicine', 'Dosage', 'Dosage Form', 'Packaging',
            'Available', 'Allocated', 'Dispensed', 'Storage Location', 'Expiration Date'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'RHU';
        }

        $rows = $inventories->map(function ($inv) {
            $med = $inv->medicine;

            $packParts = [];
            if ($med && $med->pieces_per_pack && $med->unit) {
                $packParts[] = $med->pieces_per_pack.' '.$med->unit;
            } elseif ($med && $med->unit) {
                $packParts[] = $med->unit;
            }
            if ($med && $med->pack_type) {
                $packParts[] = $med->pack_type;
            }
            $packText = $packParts ? implode(' / ', $packParts) : '';

            $row = [
                $inv->id,
                $inv->batch_no ?? '',
                $med->medicine_name ?? '',
                $med->dosage ?? '',
                $med->dosage_form ?? '',
                $packText,
                $inv->available_stock,
                $inv->allocated_stock,
                $inv->total_dispensed ?? 0,
                $inv->storage_location ?? '',
                $inv->expiration_date,
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $inv->healthUnit->name ?? '';
            }

            return $row;
        });

        return $this->streamCsv(
            'inventory_report_'.now()->format('Ymd_His').'.csv',
            $headers,
            $rows
        );
    }

    private function buildInventoryQuery(Request $request)
    {
        $rhuFilter = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');
        $expiryFilter = $request->expiry_filter;
        $category = $request->category;

        $batchFilters = $this->parseBatchInput($request, Inventory::class);

        return Inventory::with(['medicine', 'healthUnit'])
            ->select('inventory.*')
            ->selectSub(
                StockLog::selectRaw('COALESCE(SUM(quantity_added), 0)')
                    ->whereColumn('inventory.id', 'stock_logs.inventory_id')
                    ->whereColumn('inventory.medicine_id', 'stock_logs.medicine_id')
                    ->whereColumn('inventory.health_unit_id', 'stock_logs.health_unit_id')
                    ->whereNull('stock_logs.voided_at')->whereHas('dispensingRecord'),
                'total_dispensed'
            )
            ->when($healthUnitId, fn ($q) => $q->where('inventory.health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('inventory.health_unit_id', $rhuFilter))
            ->when(trim((string) $request->input('batch_input', '')) !== '', fn ($q) => $q->whereIn('inventory.batch_no', $batchFilters))
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            ->when($expiryFilter === 'near_expiry', fn ($q) => $q->expiringWithin())
            ->when($expiryFilter === 'monitor', fn ($q) => $q->whereDate('inventory.expiration_date', '>', today()->addDays(Inventory::NEAR_EXPIRY_DAYS))
                ->whereDate('inventory.expiration_date', '<=', today()->addDays(180))
            )
            ->when($expiryFilter === 'expired', fn ($q) => $q->whereNotNull('inventory.expiration_date')
                ->whereDate('inventory.expiration_date', '<', now())
            )
            ->when($expiryFilter === 'not_expired', fn ($q) => $q->where(function ($sub) {
                $sub->whereNull('inventory.expiration_date')
                    ->orWhereDate('inventory.expiration_date', '>', now()->addDays(180));
            })
            )
            ->when($expiryFilter === 'near_expiry', fn ($q) => $q->fefo(), fn ($q) => $q->orderByDesc('id'));
    }

    // ========== STOCK LOGS REPORT ==========
    public function stockLogsReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $stockLogs = $this->buildStockLogsQuery($request)->get();

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('reports.stock', compact('stockLogs', 'healthUnits'));
    }

    public function stockLogsCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $stockLogs = $this->buildStockLogsQuery($request)->get();

        $headers = ['ID', 'Batch No.', 'Medicine', 'Type', 'Dosage', 'Dosage Form',
            'Packaging', 'Quantity', 'Storage Location', 'Date Added', 'Added By'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'RHU';
        }

        $rows = $stockLogs->map(function ($log) {
            $med = $log->medicine;

            $typeLabel = match ($log->movement_direction) {
                'release' => 'Stock Out', 'addition' => 'Stock In', default => 'Unknown',
            };

            $packParts = [];
            if ($med && $med->pieces_per_pack && $med->unit) {
                $packParts[] = $med->pieces_per_pack.' '.$med->unit;
            } elseif ($med && $med->unit) {
                $packParts[] = $med->unit;
            }
            if ($med && $med->pack_type) {
                $packParts[] = $med->pack_type;
            }
            $packText = $packParts ? implode(' / ', $packParts) : '';

            $unit = $med->unit ?? null;
            $qtyText = $log->quantity_added.($unit ? ' '.$unit : '');

            $row = [
                $log->id,
                $log->batch_no ?? '',
                $med->medicine_name ?? '',
                $typeLabel,
                $med->dosage ?? '',
                $med->dosage_form ?? '',
                $packText,
                $qtyText,
                $log->storage_location ?? '',
                $log->date_added,
                $log->addedByUser->name ?? '',
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $log->healthUnit->name ?? '';
            }

            return $row;
        });

        return $this->streamCsv(
            'stock_logs_report_'.now()->format('Ymd_His').'.csv',
            $headers,
            $rows
        );
    }

    private function buildStockLogsQuery(Request $request)
    {
        $historyRange = HistoricalDateRange::fromRequest($request);
        $rhuFilter = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');
        $category = $request->category;
        $type = $request->type;   // ← ADDED

        $batchFilters = $this->parseBatchInput($request, StockLog::class);

        return StockLog::whereNull('voided_at')->with(['medicine', 'addedByUser', 'healthUnit'])
            ->when($healthUnitId, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('health_unit_id', $rhuFilter))
            ->when(trim((string) $request->input('batch_input', '')) !== '', fn ($q) => $q->whereIn('batch_no', $batchFilters))
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            ->when($type === 'stock_in', fn ($q) => $q->additions())
            ->when($type === 'stock_out', fn ($q) => $q->releases())
            ->when($historyRange->isFiltered(), fn ($q) => $historyRange->apply($q, 'date_added'))
            ->orderByDesc('date_added')
            ->orderByDesc('id');
    }

    // ========== ALLOCATION REPORT ==========
    public function allocationReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $historyRange = HistoricalDateRange::fromRequest($request);
        [$facilityId, $status] = $this->allocationFilters($request);
        $allocations = $this->buildAllocationQuery($historyRange, $facilityId, $status)->get();
        $healthUnits = HealthUnit::orderBy('name')->get();
        $facilityScope = $this->allocationFacilityLabel($facilityId);

        return view('reports.allocation', compact('allocations', 'healthUnits', 'historyRange', 'facilityId', 'facilityScope', 'status'));
    }

    public function allocationCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        [$facilityId, $status] = $this->allocationFilters($request);
        $allocations = $this->buildAllocationQuery(HistoricalDateRange::fromRequest($request), $facilityId, $status)->get();
        $facilityScope = $this->allocationFacilityLabel($facilityId);
        $headers = ['ID', 'Medicine', 'Destination', 'Quantity', 'Date Allocated', 'Allocated By', 'Source Facility', 'Status', 'Facility Scope'];
        $rows = $allocations->map(fn (Allocation $allocation) => [
            $allocation->id,
            $allocation->medicine->medicine_name ?? '',
            $allocation->destination_display,
            $allocation->quantity_allocated,
            $allocation->date_allocated,
            $allocation->allocatedBy->name ?? '',
            $allocation->healthUnit->name ?? '',
            $allocation->status_label,
            $facilityScope,
        ]);

        return $this->streamCsv(
            'allocation_report_'.now()->format('Ymd_His').'.csv',
            $headers,
            $rows
        );
    }

    private function allocationFilters(Request $request): array
    {
        $facilityId = session('health_unit_id') ?? ($request->validate(['rhu_filter' => 'nullable|integer'])['rhu_filter'] ?? null);
        $status = $request->validate(['status' => ['nullable', Rule::in(array_keys(Allocation::STATUS_LABELS))]])['status'] ?? null;

        return [$facilityId === null ? null : (int) $facilityId, $status];
    }

    private function allocationFacilityLabel(?int $facilityId): string
    {
        return $facilityId === null ? 'All facilities' : (HealthUnit::find($facilityId)?->name ?? 'Selected facility');
    }

    private function buildAllocationQuery(HistoricalDateRange $historyRange, ?int $facilityId, ?string $status)
    {
        return Allocation::with(['medicine', 'allocatedBy', 'recipient', 'healthUnit', 'destinationHealthUnit'])
            ->when($facilityId !== null, fn ($q) => $q->involvingHealthUnit($facilityId))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($historyRange->isFiltered(), fn ($q) => $historyRange->apply($q, 'date_allocated'))
            ->orderByDesc('date_allocated')
            ->orderByDesc('id');
    }

    // ========== DISPENSING REPORT (RHU-only) ==========
    public function dispensingReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('reports.index')
                ->with('error', 'Dispensing reports are only available at the RHU level.');
        }

        $query = $this->buildDispensingQuery($request);
        $records = $query->get();
        $facilityRecordCount = $records->count();
        $barangayBreakdown = (clone $query)->reorder()->barangayBreakdown()->get();
        $healthUnits = HealthUnit::orderBy('name')->get();
        $barangayFilterOptions = DispensingRecord::qualifying()->where('health_unit_id', session('health_unit_id'))
            ->whereNotNull('barangay')->pluck('barangay')->map(fn ($area) => trim($area))->filter()
            ->unique(fn ($area) => mb_strtolower($area))->sort()->values();
        $selectedMedicine = $request->filled('medicine_id') ? Medicine::withTrashed()->find($request->medicine_id) : null;

        return view('reports.dispensing', compact('records', 'healthUnits', 'facilityRecordCount', 'barangayBreakdown', 'barangayFilterOptions', 'selectedMedicine'));
    }

    public function dispensingCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('reports.index')
                ->with('error', 'Dispensing reports are only available at the RHU level.');
        }

        $records = $this->buildDispensingQuery($request)->get();

        $headers = ['ID', 'Medicine', 'Quantity', 'Doctor', 'Date Dispensed', 'Dispensed By', 'Barangay served', 'Stock source', 'Lot quantities'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'RHU';
        }

        $rows = $records->map(function ($rec) {
            $row = [
                $rec->id,
                $rec->medicine->medicine_name ?? '',
                $rec->quantity_dispensed,
                $rec->prescribingDoctorName(),
                $rec->date_dispensed,
                $rec->dispensedByUser->name ?? '',
                $rec->barangay,
                $rec->stock_source ?: 'rhu',
                $rec->lotBreakdown(),
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $rec->healthUnit->name ?? '';
            }

            return $row;
        });

        return $this->streamCsv(
            'dispensing_report_'.now()->format('Ymd_His').'.csv',
            $headers,
            $rows
        );
    }

    private function buildDispensingQuery(Request $request)
    {
        $historyRange = HistoricalDateRange::fromRequest($request);
        $rhuFilter = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');
        $request->validate(['barangay' => 'nullable|string|max:100', 'barangay_missing' => 'nullable|boolean',
            'medicine_id' => 'nullable|integer|exists:medicines,id', 'batch_no' => 'nullable|string|max:100']);
        $barangay = trim((string) $request->barangay);
        if ($barangay !== '' && $request->boolean('barangay_missing')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['barangay' => 'Choose a barangay or records without a saved barangay.']);
        }

        return DispensingRecord::qualifying()->with(['medicine', 'dispensedByUser', 'healthUnit', 'issuedLots'])
            ->when($healthUnitId, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('health_unit_id', $rhuFilter))
            ->when($barangay !== '', fn ($q) => $q->forBarangays([$barangay]))
            ->when($request->boolean('barangay_missing'), fn ($q) => $q->withoutRecordedBarangay())
            ->when($request->filled('medicine_id'), fn ($q) => $q->where('medicine_id', $request->medicine_id))
            ->when($request->filled('batch_no'), fn ($q) => $q->forIssuedLot(trim($request->batch_no)))
            ->when($historyRange->isFiltered(), fn ($q) => $historyRange->apply($q, 'date_dispensed'))
            ->orderByDesc('date_dispensed')
            ->orderByDesc('id');
    }

    // ========== NEAR EXPIRY REPORT ==========
    public function nearExpiryReport(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $nearExpiryFrom = today();
        $nearExpiryThrough = $nearExpiryFrom->copy()->addDays(Inventory::NEAR_EXPIRY_DAYS);
        $inventories = $this->buildNearExpiryQuery($request, $nearExpiryFrom)->get();

        $healthUnits = HealthUnit::orderBy('name')->get();

        return view('reports.near_expiry', compact('inventories', 'healthUnits', 'nearExpiryFrom', 'nearExpiryThrough'));
    }

    public function nearExpiryCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $nearExpiryFrom = today();
        $inventories = $this->buildNearExpiryQuery($request, $nearExpiryFrom)->get();

        $headers = ['ID', 'Batch No.', 'Medicine', 'Available Stock', 'Expiration Date', 'Status'];
        if (is_null(session('health_unit_id'))) {
            $headers[] = 'Facility';
        }

        $rows = $inventories->map(function ($inv) use ($nearExpiryFrom) {
            $status = $inv->expiration_date->isSameDay($nearExpiryFrom) ? 'Expires today' : 'Near Expiry';
            $row = [
                $inv->id,
                $inv->batch_no ?? '',
                $inv->medicine->medicine_name ?? '',
                $inv->available_stock,
                $inv->expiration_date->toDateString(),
                $status,
            ];
            if (is_null(session('health_unit_id'))) {
                $row[] = $inv->healthUnit->name ?? '';
            }

            return $row;
        });

        return $this->streamCsv(
            'near_expiry_report_'.now()->format('Ymd_His').'.csv',
            $headers,
            $rows
        );
    }

    private function buildNearExpiryQuery(Request $request, CarbonInterface $from)
    {
        $rhuFilter = $request->rhu_filter;
        $healthUnitId = session('health_unit_id');

        $batchFilters = $this->parseBatchInput($request, Inventory::class);

        return Inventory::with(['medicine', 'healthUnit'])
            ->when($healthUnitId, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->when(is_null($healthUnitId) && $rhuFilter, fn ($q) => $q->where('health_unit_id', $rhuFilter))
            ->when(trim((string) $request->input('batch_input', '')) !== '', fn ($q) => $q->whereIn('batch_no', $batchFilters))
            ->expiringWithin(from: $from)
            ->fefo();
    }

    // ========== CSV STREAM HELPER ==========
    private function streamCsv($filename, array $headers, $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            SpreadsheetCsv::writeRow($handle, $headers);
            foreach ($rows as $row) {
                SpreadsheetCsv::writeRow($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
