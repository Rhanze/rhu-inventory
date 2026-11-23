<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\DohShipment;
use App\Models\DohShipmentItem;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Services\InventoryHistoryService;
use App\Services\InventoryHistoricalBalanceService;
use App\Services\StockMovementService;
use App\Support\HistoricalDateRange;
use App\Support\InventoryHistoryPeriod;
use App\Support\SpreadsheetCsv;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
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

    private function guardPhoAdmin()
    {
        if (! session()->has('user_id')
            || session('role') !== 'admin'
            || ! is_null(session('health_unit_id'))) {
            return redirect()->route('dashboard')
                ->with('error', 'Only PHO admin can manage DOH shipments.');
        }

        return null;
    }

    private function parseBatchInput(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $expanded = [];

        foreach ($parts as $part) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
                $s = (int) $m[1];
                $e = (int) $m[2];
                if ($s > $e) {
                    [$s, $e] = [$e, $s];
                }
                if ($e - $s > 1000) {
                    $e = $s + 1000;
                }
                for ($i = $s; $i <= $e; $i++) {
                    $expanded[] = (string) $i;
                }
            } else {
                $expanded[] = $part;
            }
        }

        return array_values(array_unique($expanded));
    }

    private function applyBatchAndExpiryFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('batch_input'), function ($q) use ($request) {
                $batches = $this->parseBatchInput($request->batch_input);
                if (empty($batches)) {
                    return;
                }

                $q->where(function ($sub) use ($batches) {
                    foreach ($batches as $b) {
                        $sub->orWhere('batch_no', $b)
                            ->orWhere('batch_no', 'like', '%-'.$b)
                            ->orWhere('batch_no', 'like', '%-0'.$b)
                            ->orWhere('batch_no', 'like', '%-00'.$b);
                    }
                });
            })
            ->when($request->expiry_filter === 'expired', fn ($q) => $q->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<', now())
            )
            ->when($request->expiry_filter === 'near_expiry', fn ($q) => $q->expiringWithin())
            ->when($request->expiry_filter === 'monitor', fn ($q) => $q->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>', today()->addDays(Inventory::NEAR_EXPIRY_DAYS))
                ->whereDate('expiration_date', '<=', now()->addDays(180))
            )
            ->when($request->expiry_filter === 'good', fn ($q) => $q->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>', now()->addDays(180))
            )
            ->when($request->expiry_filter === 'no_expiry', fn ($q) => $q->whereNull('expiration_date')
            )
            ->when($request->expiry_filter === 'not_expired', fn ($q) => $q->where(function ($sub) {
                $sub->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', now());
            })
            );
    }

    public function index(Request $request)
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }

        if ($request->input('view') === 'history') {
            $request->validate(['summary_page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);
            $historyPeriod = InventoryHistoryPeriod::apply($request);
            $historyRange = HistoricalDateRange::fromRequest($request);
            $movements = app(InventoryHistoryService::class)->records($request, $historyRange);
            $stockHistory = app(InventoryHistoricalBalanceService::class)->summarize($request, $historyRange);
            $healthUnits = HealthUnit::orderBy('name')->get();
            $unitId = session('health_unit_id');
            if ($unitId === null && $request->filled('rhu_filter') && $request->input('rhu_filter') !== 'all') {
                $unitId = $request->rhu_filter === 'pho' ? (HealthUnit::phoId() ?? -1) : (int) $request->rhu_filter;
            }
            $historyMedicines = Medicine::withTrashed()->where(function ($medicines) use ($unitId) {
                foreach (['inventory', 'stock_logs', 'dispensing_records', 'allocations'] as $table) {
                    $medicines->orWhereIn('id', DB::table($table)->select('medicine_id')
                        ->when($unitId !== null, fn ($query) => $query->where('health_unit_id', $unitId)));
                }
            })->orderBy('medicine_name')->orderBy('dosage')->get(['id', 'medicine_name', 'dosage']);
            $stockRows = collect($stockHistory['rows'])->values();
            $stockPage = max(1, (int) $request->input('summary_page', 1));
            $stockSummary = new LengthAwarePaginator($stockRows->slice(($stockPage - 1) * 20, 20)->values(),
                $stockRows->count(), 20, $stockPage, [
                    'path' => $request->url(), 'pageName' => 'summary_page', 'query' => $request->query(),
                ]);

            $historyFacility = $unitId === null ? null : $healthUnits->firstWhere('id', $unitId);

            return view('inventory.history', compact('movements', 'historyRange', 'healthUnits',
                'historyPeriod', 'historyMedicines', 'stockHistory', 'stockSummary', 'unitId', 'historyFacility'));
        }

        $search = $request->search;
        $rhuFilter = $request->rhu_filter;
        $facilityType = $request->facility_type;
        $storageLoc = $request->storage_location;
        $category = $request->category;
        $batchInput = $request->batch_input;
        $expiryFilter = $request->expiry_filter;
        $stockLevel = $request->validate(['stock_level' => 'nullable|in:low,overstock,available'])['stock_level'] ?? null;
        $healthUnitId = session('health_unit_id');
        $isPhoAdmin = is_null($healthUnitId);
        $phoId = $isPhoAdmin ? HealthUnit::phoId() : null;

        $query = Inventory::with(['medicine', 'healthUnit'])
            ->when($healthUnitId, function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId);
            })
            ->when($isPhoAdmin && $rhuFilter === 'pho', function ($q) use ($phoId) {
                $q->where('health_unit_id', $phoId ?? -1);
            })
            ->when($isPhoAdmin && $rhuFilter && ! in_array($rhuFilter, ['pho', 'all'], true), function ($q) use ($rhuFilter) {
                $q->where('health_unit_id', $rhuFilter);
            })
            ->when($isPhoAdmin && ! $rhuFilter && $phoId, function ($q) use ($phoId) {
                $q->where('health_unit_id', '!=', $phoId);
            })
            ->when($facilityType, function ($q) use ($facilityType) {
                $q->whereHas('healthUnit', function ($sub) use ($facilityType) {
                    $sub->where('facility_type', $facilityType);
                });
            })
            ->when($storageLoc, function ($q) use ($storageLoc) {
                $q->where('storage_location', 'LIKE', '%'.$storageLoc.'%');
            })
            ->when($search, function ($query, $search) {
                $query->whereHas('medicine', function ($sub) use ($search) {
                    $sub->where('medicine_name', 'like', "%{$search}%");
                });
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            });

        $query = $this->applyBatchAndExpiryFilters($query, $request)
            ->when($stockLevel, fn ($query) => $query->stockLevel($stockLevel));

        $query->when($request->expiry_filter === 'near_expiry', fn ($q) => $q->fefo(), fn ($q) => $q->orderByDesc('id'));
        if ($request->boolean('print')) {
            return view('inventory.print', ['inventories' => $query->lazy()]);
        }

        $inventories = $query
            ->paginate(config('pagination.inventory', 20))
            ->appends([
                'search' => $search,
                'rhu_filter' => $rhuFilter,
                'facility_type' => $facilityType,
                'storage_location' => $storageLoc,
                'category' => $category,
                'batch_input' => $batchInput,
                'expiry_filter' => $expiryFilter,
                'stock_level' => $stockLevel,
            ]);

        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();

        return view('inventory.index', compact('inventories', 'healthUnits'));
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

        if (session('role') !== 'admin') {
            return redirect()->route('inventory.index')
                ->with('error', 'Only administrators can manually add inventory records.');
        }

        $medicines = Medicine::orderBy('medicine_name')->get();
        $healthUnit = HealthUnit::find(session('health_unit_id'));

        return view('inventory.create', compact('medicines', 'healthUnit'));
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

        if (session('role') !== 'admin') {
            return redirect()->route('inventory.index')
                ->with('error', 'Only administrators can manually add inventory records.');
        }

        $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'available_stock' => 'required|integer|min:0',
            'allocated_stock' => 'sometimes|required|integer|in:0',
            'batch_no' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
            'storage_location' => 'nullable|string|max:255',
            'pack_type' => 'nullable|string|max:50',
            'pieces_per_pack' => 'nullable|integer|min:1',
            'remarks' => 'nullable|string|max:2000',
            'date_added' => 'nullable|date|before_or_equal:today',
        ], ['allocated_stock.in' => 'New inventory opens as available stock. Use an allocation or transfer to reserve units.']);

        $inventory = DB::transaction(function () use ($request) {
            $inventory = Inventory::create([
                'medicine_id' => $request->medicine_id,
                'health_unit_id' => session('health_unit_id'),
                'available_stock' => $request->available_stock,
                'allocated_stock' => 0,
                'batch_no' => $request->batch_no,
                'expiration_date' => $request->expiration_date,
                'storage_location' => $request->storage_location,
                'pack_type' => $request->pack_type,
                'pieces_per_pack' => $request->pieces_per_pack,
            ]);

            if ($request->available_stock > 0) {
                StockLog::create([
                    'medicine_id' => $inventory->medicine_id,
                    'inventory_id' => $inventory->id,
                    'movement_type' => 'addition',
                    'health_unit_id' => $inventory->health_unit_id,
                    'quantity_added' => $request->available_stock,
                    'batch_no' => $inventory->batch_no,
                    'storage_location' => $inventory->storage_location,
                    'date_added' => $request->input('date_added') ?: now()->toDateString(),
                    'added_by' => session('user_id'),
                    'remarks' => 'Added — Manual inventory entry (Inventory #'.$inventory->id.')'.
                        ($request->filled('remarks') ? ' | Reason '.$request->remarks : ''),
                ]);
            }
            return $inventory;
        });

        $medicine = Medicine::find($inventory->medicine_id);

        ActivityLogger::log(
            'Inventory',
            'Create',
            'Added inventory for '.($medicine->medicine_name ?? 'medicine').
            ' | Available: '.$inventory->available_stock.
            ', Allocated: '.$inventory->allocated_stock.
            ($inventory->storage_location ? ' | Storage: '.$inventory->storage_location : '').
            ($inventory->pack_type && $inventory->pieces_per_pack
                ? ' | Packaging: '.$inventory->pieces_per_pack.' per '.$inventory->pack_type
                : '')
        );

        return redirect()->route('inventory.index')->with('success', 'Inventory record added successfully.');
    }

    public function edit($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $inventory = $this->findInventoryForCurrentUser($id);
        $medicines = Medicine::orderBy('medicine_name')->get();
        $healthUnit = HealthUnit::find(session('health_unit_id'));

        return view('inventory.edit', compact('inventory', 'medicines', 'healthUnit'));
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        return DB::transaction(function () use ($request, $id) {
            $inventory = $this->findInventoryForCurrentUser($id, lock: true);
            $validated = $request->validate([
                'batch_no' => 'nullable|string|max:100',
                'expiration_date' => 'nullable|date',
                'storage_location' => 'nullable|string|max:255',
                'pack_type' => 'nullable|string|max:50',
                'pieces_per_pack' => 'nullable|integer|min:1',
            ]);

            $inventory->fill($validated);
            if ($inventory->isDirty(['batch_no', 'expiration_date', 'pack_type', 'pieces_per_pack'])
                && ($inventory->allocated_stock > 0
                    || DB::table('stock_movements')->where('inventory_id', $inventory->id)->lockForUpdate()->first()
                    || StockLog::where('inventory_id', $inventory->id)->lockForUpdate()->first())) {
                return back()->withErrors(['batch_no' => 'This batch already has stock history. Record a documented correction to preserve its lot, expiry and packaging history.']);
            }
            $inventory->save();

            ActivityLogger::log(
                'Inventory',
                'Update',
                'Updated inventory for '.($inventory->medicine?->medicine_name ?? 'medicine').
                ' | Available: '.$inventory->available_stock.
                ', Allocated: '.$inventory->allocated_stock.
                ($inventory->storage_location ? ' | Storage: '.$inventory->storage_location : '').
                ($inventory->pack_type && $inventory->pieces_per_pack
                    ? ' | Packaging: '.$inventory->pieces_per_pack.' per '.$inventory->pack_type
                    : ''),
                $inventory->health_unit_id
            );

            return redirect()->route('inventory.index')->with('success', 'Inventory record updated successfully.');
        }, 3);
    }

    public function adjust(Request $request, $id, StockMovementService $movements)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        return DB::transaction(function () use ($request, $id, $movements) {
            $inventory = $this->findInventoryForCurrentUser($id, lock: true);
            $validated = $request->validate([
                'available_stock' => 'required|integer|between:0,2147483647',
                'expected_available_stock' => 'required|integer|between:0,2147483647',
                'remarks' => 'required|string|max:255',
            ]);

            $before = (int) $inventory->available_stock;
            if ((int) $validated['expected_available_stock'] !== $before) {
                throw ValidationException::withMessages([
                    'available_stock' => 'The stock balance changed. Review the current balance before adjusting it.',
                ]);
            }

            $after = (int) $validated['available_stock'];
            $delta = $after - $before;
            if ($delta === 0) {
                return back()->with('success', 'The available stock already matches the entered quantity.');
            }

            $reason = $validated['remarks'];
            $inventory->update(['available_stock' => $after]);
            $log = StockLog::create([
                'medicine_id' => $inventory->medicine_id,
                'inventory_id' => $inventory->id,
                'movement_type' => $delta > 0 ? 'adjustment_in' : 'adjustment_out',
                'health_unit_id' => $inventory->health_unit_id,
                'quantity_added' => abs($delta),
                'batch_no' => $inventory->batch_no,
                'storage_location' => $inventory->storage_location,
                'date_added' => today()->toDateString(),
                'added_by' => session('user_id'),
                'remarks' => 'Inventory adjusted from '.$before.' to '.$after.' units. Reason: '.$reason,
            ]);
            $movements->record($log, $inventory, $delta, method: 'Manual stock adjustment', reason: $reason);
            ActivityLogger::log(
                'Inventory', 'Adjust Stock',
                'Adjusted inventory #'.$inventory->id.' from '.$before.' to '.$after.' units. Reason: '.$reason,
                $inventory->health_unit_id
            );

            return redirect()->route('inventory.index')->with('success', 'Stock adjustment recorded.');
        }, 3);
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        return DB::transaction(function () use ($id) {
            $inventory = $this->findInventoryForCurrentUser($id, lock: true);
            $medicine = $inventory->medicine;
            if ($inventory->allocated_stock > 0 || TransferRequest::where('inventory_id', $inventory->id)->where('status', 'accepted')->exists()) {
                return back()->with('error', 'Cannot delete a batch with reserved stock or an accepted transfer.');
            }

            $allocationCount = Allocation::where('medicine_id', $inventory->medicine_id)
                ->where('health_unit_id', $inventory->health_unit_id)
                ->count();

            if ($allocationCount > 0) {
                return redirect()->route('inventory.index')
                    ->with('error', 'Cannot delete this inventory record because it has existing allocations.');
            }

            $dispensingCount = DispensingRecord::where('medicine_id', $inventory->medicine_id)
                ->where('health_unit_id', $inventory->health_unit_id)
                ->count();

            if ($dispensingCount > 0) {
                return redirect()->route('inventory.index')
                    ->with('error', 'Cannot delete this inventory record because it has dispensing records.');
            }

            $qtyAtDelete = (int) $inventory->available_stock;
            $batchAtDelete = $inventory->batch_no;
            $locAtDelete = $inventory->storage_location;

            $inventory->delete();

            if ($qtyAtDelete > 0) {
                StockLog::create([
                    'medicine_id' => $inventory->medicine_id,
                    'inventory_id' => $inventory->id,
                    'movement_type' => 'adjustment_out',
                    'health_unit_id' => $inventory->health_unit_id,
                    'quantity_added' => $qtyAtDelete,
                    'batch_no' => $batchAtDelete,
                    'storage_location' => $locAtDelete,
                    'date_added' => now()->toDateString(),
                    'added_by' => session('user_id'),
                    'remarks' => 'Released — Inventory record deleted ('.
                                          $qtyAtDelete.' units removed, Inventory #'.$id.')',
                ]);
            }

            ActivityLogger::log(
                'Inventory',
                'Delete',
                'Deleted inventory for '.($medicine->medicine_name ?? 'medicine').'.'
            );

            return redirect()->route('inventory.index')->with('success', 'Inventory record deleted successfully.');
        });
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $search = $request->search;
        $rhuFilter = $request->rhu_filter;
        $facilityType = $request->facility_type;
        $storageLoc = $request->storage_location;
        $category = $request->category;
        $stockLevel = $request->validate(['stock_level' => 'nullable|in:low,overstock,available'])['stock_level'] ?? null;
        $healthUnitId = session('health_unit_id');
        $isPhoAdmin = is_null($healthUnitId);
        $phoId = $isPhoAdmin ? HealthUnit::phoId() : null;

        $query = Inventory::with(['medicine', 'healthUnit'])
            ->when($healthUnitId, function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId);
            })
            ->when($isPhoAdmin && $rhuFilter === 'pho', function ($q) use ($phoId) {
                $q->where('health_unit_id', $phoId ?? -1);
            })
            ->when($isPhoAdmin && $rhuFilter && ! in_array($rhuFilter, ['pho', 'all'], true), function ($q) use ($rhuFilter) {
                $q->where('health_unit_id', $rhuFilter);
            })
            ->when($isPhoAdmin && ! $rhuFilter && $phoId, function ($q) use ($phoId) {
                $q->where('health_unit_id', '!=', $phoId);
            })
            ->when($facilityType, function ($q) use ($facilityType) {
                $q->whereHas('healthUnit', function ($sub) use ($facilityType) {
                    $sub->where('facility_type', $facilityType);
                });
            })
            ->when($storageLoc, function ($q) use ($storageLoc) {
                $q->where('storage_location', 'LIKE', '%'.$storageLoc.'%');
            })
            ->when($search, function ($query, $search) {
                $query->whereHas('medicine', function ($q) use ($search) {
                    $q->where('medicine_name', 'like', "%{$search}%")
                        ->orWhere('dosage', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            });

        $query = $this->applyBatchAndExpiryFilters($query, $request)
            ->when($stockLevel, fn ($query) => $query->stockLevel($stockLevel));

        $inventories = $query
            ->when($request->expiry_filter === 'near_expiry', fn ($q) => $q->fefo(), fn ($q) => $q->orderByDesc('id'))
            ->get();

        $filename = 'inventory_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($inventories) {
            $handle = fopen('php://output', 'w');

            SpreadsheetCsv::writeRow($handle, [
                'ID',
                'Medicine',
                'Batch No.',
                'Storage Location',
                'Packaging',
                'Available Stock',
                'Allocated Stock',
                'Expiration Date',
                'Facility Type',
                'RHU',
            ]);

            foreach ($inventories as $inventory) {
                $packaging = '';
                if ($inventory->pack_type && $inventory->pieces_per_pack) {
                    $packaging = $inventory->pieces_per_pack.' per '.$inventory->pack_type;
                } elseif ($inventory->pack_type) {
                    $packaging = $inventory->pack_type;
                }

                SpreadsheetCsv::writeRow($handle, [
                    $inventory->id,
                    $inventory->medicine->medicine_name ?? '',
                    $inventory->batch_no ?? '',
                    $inventory->storage_location ?? '',
                    $packaging,
                    $inventory->available_stock,
                    $inventory->allocated_stock,
                    $inventory->expiration_date,
                    $inventory->healthUnit->facility_type ?? '',
                    $inventory->healthUnit->name ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  PHO — DOH SHIPMENT RECORDS
    // ═══════════════════════════════════════════════════════════

    /** Index — list of shipments */
    public function phoReceive(Request $request)
    {
        if ($redirect = $this->guardPhoAdmin()) {
            return $redirect;
        }

        $search = $request->search;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $shipments = DohShipment::with(['items.medicine', 'receivedByUser'])
            ->withCount('items')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('shipment_no', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('received_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('received_date', '<=', $dateTo))
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends([
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);

        return view('inventory.pho_receive', compact('shipments', 'search', 'dateFrom', 'dateTo'));
    }

    /** Create form */
    public function phoReceiveCreate()
    {
        if ($redirect = $this->guardPhoAdmin()) {
            return $redirect;
        }

        $medicines = Medicine::orderBy('medicine_name')->get();

        $submissionToken = (string) Str::uuid();

        return view('inventory.pho_receive_create', compact('medicines', 'submissionToken'));
    }

    /** Store new shipment */
    public function storePhoReceive(Request $request)
    {
        if ($redirect = $this->guardPhoAdmin()) {
            return $redirect;
        }

        $validated = $request->validate([
            'submission_token' => 'required|uuid:4',
            'shipment_no' => 'nullable|string|max:50',
            'reference_no' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:150',
            'received_date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:2000',

            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiration_date' => 'nullable|date',
            'items.*.storage_location' => 'nullable|string|max:255',
        ], [
            'submission_token.required' => 'Reload the shipment form to get a new submission token.',
            'items.required' => 'Add at least one item to the shipment.',
            'items.min' => 'Add at least one item to the shipment.',
            'items.*.medicine_id.required' => 'Please select a medicine for every row.',
            'items.*.medicine_id.exists' => 'One of the selected medicines no longer exists.',
            'items.*.quantity.required' => 'Quantity is required for every item.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ]);

        $phoId = HealthUnit::phoId();
        if (! $phoId) {
            return back()->withInput()
                ->with('error', 'Provincial Health Office record not found in the database.');
        }

        try {
            [$shipment, $posted] = DB::transaction(function () use ($validated, $phoId) {
                HealthUnit::whereKey($phoId)->lockForUpdate()->firstOrFail();
                $submissionToken = hash('sha256', $phoId.':'.session('user_id').':'.strtolower($validated['submission_token']));
                $previousId = DB::table('doh_shipment_submissions')->where('token', $submissionToken)->lockForUpdate()->value('shipment_id');
                if ($previousId !== null) {
                    return [DohShipment::whereKey($previousId)->lockForUpdate()->first(), false];
                }

                $shipmentNo = Str::lower(trim($validated['shipment_no'] ?? ''));
                $referenceNo = Str::lower(trim($validated['reference_no'] ?? ''));
                $deliveryKey = $this->shipmentDeliveryKey($shipmentNo, $referenceNo);

                if ($deliveryKey !== null) {
                    $previous = DohShipment::where('delivery_key', $deliveryKey)->lockForUpdate()->first();
                    $previous ??= DohShipment::whereNull('delivery_key')
                        ->whereRaw('LOWER(TRIM(shipment_no)) = ?', [$shipmentNo])
                        ->whereRaw('LOWER(TRIM(reference_no)) = ?', [$referenceNo])
                        ->orderBy('id')->lockForUpdate()->first();

                    if ($previous) {
                        $this->validateReceiptRetry($previous, $validated);
                        DB::table('doh_shipment_submissions')->insert(['token' => $submissionToken, 'shipment_id' => $previous->id]);

                        return [$previous, false];
                    }

                    if (DB::table('doh_shipment_submissions')->where('token', $this->reversedDeliveryToken($deliveryKey))
                        ->lockForUpdate()->first()) {
                        return [null, false];
                    }
                }

                $shipment = DohShipment::create([
                    'shipment_no' => $validated['shipment_no'] ?? null,
                    'reference_no' => $validated['reference_no'] ?? null,
                    'supplier' => $validated['supplier'] ?? 'Department of Health',
                    'received_date' => $validated['received_date'],
                    'received_by' => session('user_id'),
                    'remarks' => $validated['remarks'] ?? null,
                    'delivery_key' => $deliveryKey,
                ]);

                foreach ($validated['items'] as $row) {
                    $medicine = Medicine::findOrFail($row['medicine_id']);

                    DohShipmentItem::create([
                        'shipment_id' => $shipment->id,
                        'medicine_id' => $medicine->id,
                        'quantity' => $row['quantity'],
                        'batch_no' => $row['batch_no'] ?? null,
                        'expiration_date' => $row['expiration_date'] ?? null,
                        'storage_location' => $row['storage_location'] ?? null,
                    ]);

                    $inventory = Inventory::firstOrCreate(
                        [
                            'medicine_id' => $medicine->id,
                            'health_unit_id' => $phoId,
                            'batch_no' => $row['batch_no'] ?? null,
                            'expiration_date' => $row['expiration_date'] ?? null,
                        ],
                        [
                            'available_stock' => 0,
                            'allocated_stock' => 0,
                            'storage_location' => $row['storage_location'] ?? null,
                        ]
                    );

                    if (! empty($row['storage_location'])) {
                        $inventory->update(['storage_location' => $row['storage_location']]);
                    }

                    $inventory->increment('available_stock', $row['quantity']);

                    StockLog::create([
                        'medicine_id' => $medicine->id,
                        'inventory_id' => $inventory->id,
                        'movement_type' => 'receipt',
                        'health_unit_id' => $phoId,
                        'quantity_added' => $row['quantity'],
                        'batch_no' => $inventory->batch_no,
                        'storage_location' => $inventory->storage_location,
                        'date_added' => $validated['received_date'],
                        'added_by' => session('user_id'),
                        'remarks' => 'Added — DOH delivery'.
                                              ($shipment->reference_no ? ' (Ref: '.$shipment->reference_no.')' : '').
                                              ' [Shipment #'.$shipment->id.']',
                    ]);
                }

                DB::table('doh_shipment_submissions')->insert(['token' => $submissionToken, 'shipment_id' => $shipment->id]);

                ActivityLogger::log(
                    'Inventory',
                    'PHO Receive',
                    'Recorded DOH shipment #'.$shipment->id.
                    ($shipment->shipment_no ? ' ('.$shipment->shipment_no.')' : '').
                    ' with '.count($validated['items']).' item(s).'
                );

                return [$shipment, true];
            }, 3);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', $this->operationErrorMessage($e, 'Unable to save the shipment. Please try again.'));
        }

        if (! $posted) {
            return $shipment
                ? redirect()->route('inventory.pho_receive.show', $shipment->id)->with('success', 'This shipment was already recorded.')
                : redirect()->route('inventory.pho_receive')->with('error', 'This shipment was already reversed. Use the shipment and reference numbers for a separate delivery.');
        }

        return redirect()->route('inventory.pho_receive.show', $shipment->id)
            ->with('success', 'DOH shipment recorded. Stock added to PHO inventory.');
    }

    private function shipmentDeliveryKey(?string $shipmentNo, ?string $referenceNo): ?string
    {
        $shipmentNo = Str::lower(trim($shipmentNo ?? ''));
        $referenceNo = Str::lower(trim($referenceNo ?? ''));

        return $shipmentNo !== '' && $referenceNo !== ''
            ? hash('sha256', json_encode([$shipmentNo, $referenceNo], JSON_THROW_ON_ERROR))
            : null;
    }

    private function reversedDeliveryToken(string $deliveryKey): string
    {
        // This key identifies a delivery across form tokens and receiving users.
        return hash('sha256', 'reversed-doh-delivery:'.$deliveryKey);
    }

    private function validateReceiptRetry(DohShipment $shipment, array $validated): void
    {
        $lines = fn ($items) => collect($items)->map(fn ($item) => [
            (int) $item['medicine_id'],
            $item['batch_no'] ?? '',
            isset($item['expiration_date']) ? Carbon::parse($item['expiration_date'])->toDateString() : '',
            (int) $item['quantity'],
        ])->sort()->values()->all();

        if ($shipment->received_date->toDateString() !== Carbon::parse($validated['received_date'])->toDateString()
            || $lines($shipment->items()->lockForUpdate()->get()) !== $lines($validated['items'])) {
            throw ValidationException::withMessages([
                'reference_no' => 'These shipment and reference numbers are already recorded with a different received date or item list. Review the existing receipt before submitting a separate delivery.',
            ]);
        }
    }

    /** View a single shipment */
    public function showDohShipment($id)
    {
        if ($redirect = $this->guardPhoAdmin()) {
            return $redirect;
        }

        $shipment = DohShipment::with(['items.medicine', 'receivedByUser'])
            ->findOrFail($id);

        return view('inventory.pho_receive_show', compact('shipment'));
    }

    /** Delete a shipment and reverse its inventory */
    public function destroyDohShipment($id)
    {
        if ($redirect = $this->guardPhoAdmin()) {
            return $redirect;
        }

        $shipment = DohShipment::findOrFail($id);
        $phoId = HealthUnit::phoId();

        if (! $phoId) {
            return back()->with('error', 'PHO record not found.');
        }

        try {
            DB::transaction(function () use ($shipment, $phoId) {
                HealthUnit::whereKey($phoId)->lockForUpdate()->firstOrFail();
                $shipment = DohShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
                foreach ($shipment->items as $item) {
                    $inv = Inventory::where('medicine_id', $item->medicine_id)
                        ->where('health_unit_id', $phoId)
                        ->where('batch_no', $item->batch_no)
                        ->where('expiration_date', $item->expiration_date)
                        ->lockForUpdate()->first();

                    if (! $inv || $inv->available_stock < $item->quantity) {
                        throw new \DomainException('Cannot reverse shipment: batch stock has already been used.');
                    }
                    $inv->decrement('available_stock', $item->quantity);

                    StockLog::create([
                        'medicine_id' => $item->medicine_id,
                        'inventory_id' => $inv->id,
                        'movement_type' => 'adjustment_out',
                        'health_unit_id' => $phoId,
                        'quantity_added' => $item->quantity,
                        'batch_no' => $item->batch_no,
                        'storage_location' => $item->storage_location,
                        'date_added' => now()->toDateString(),
                        'added_by' => session('user_id'),
                        'remarks' => 'Released — DOH shipment deleted'.
                                              ($shipment->reference_no ? ' (Ref: '.$shipment->reference_no.')' : '').
                                              ' [Shipment #'.$shipment->id.']',
                    ]);
                }

                $deliveryKey = $shipment->delivery_key ?? $this->shipmentDeliveryKey($shipment->shipment_no, $shipment->reference_no);
                if ($deliveryKey !== null) {
                    DB::table('doh_shipment_submissions')->upsert([[
                        'token' => $this->reversedDeliveryToken($deliveryKey),
                        'shipment_id' => $shipment->id,
                    ]], ['token'], ['shipment_id']);
                }

                ActivityLogger::log(
                    'Inventory',
                    'PHO Receive',
                    'Deleted DOH shipment #'.$shipment->id.
                    ($shipment->shipment_no ? ' ('.$shipment->shipment_no.')' : '').
                    ' and reversed inventory.'
                );
                $shipment->items()->delete();
                $shipment->delete();
            }, 3);
        } catch (\Throwable $e) {
            return back()->with('error', $this->operationErrorMessage($e, 'Unable to delete the shipment. Please try again.'));
        }

        return redirect()->route('inventory.pho_receive')
            ->with('success', 'Shipment deleted and inventory reversed.');
    }

    /** Export DOH shipments to CSV with filters */
    public function exportDohShipmentsCsv(Request $request)
    {
        if ($redirect = $this->guardPhoAdmin()) {
            return $redirect;
        }

        $search = $request->search;
        $shipmentNo = $request->shipment_no;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $shipments = DohShipment::with(['items.medicine', 'receivedByUser'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('shipment_no', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%");
                });
            })
            ->when($shipmentNo, function ($q) use ($shipmentNo) {
                $nums = array_filter(array_map('trim', explode(',', $shipmentNo)));
                if (empty($nums)) {
                    return;
                }
                $q->where(function ($sub) use ($nums) {
                    foreach ($nums as $n) {
                        $sub->orWhere('shipment_no', 'like', "%{$n}%");
                    }
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('received_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('received_date', '<=', $dateTo))
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get();

        $filename = 'doh_shipments_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($shipments) {
            $handle = fopen('php://output', 'w');

            SpreadsheetCsv::writeRow($handle, [
                'Shipment No.',
                'Reference No.',
                'Supplier',
                'Date Received',
                'Received By',
                'Items Count',
                'Total Units',
                'Remarks',
            ]);

            foreach ($shipments as $ship) {
                SpreadsheetCsv::writeRow($handle, [
                    $ship->shipment_no ?? '',
                    $ship->reference_no ?? '',
                    $ship->supplier ?? '',
                    $ship->received_date,
                    $ship->receivedByUser->name ?? '',
                    $ship->items->count(),
                    (int) $ship->items->sum('quantity'),
                    $ship->remarks ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function findInventoryForCurrentUser($id, bool $lock = false): Inventory
    {
        abort_if(is_null(session('health_unit_id')) && session('role') !== 'admin', 403);
        $query = Inventory::query();

        if (session('health_unit_id')) {
            $query->where('health_unit_id', session('health_unit_id'));
        }

        $inventory = $query->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
        abort_if($inventory->healthUnit?->facility_type === 'Provincial Health Office', 403);

        return $inventory;
    }
}
