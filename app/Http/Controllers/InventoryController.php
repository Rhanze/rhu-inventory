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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->when($request->expiry_filter === 'near_expiry', fn ($q) => $q->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>=', now())
                ->whereDate('expiration_date', '<=', now()->addDays(90))
            )
            ->when($request->expiry_filter === 'monitor', fn ($q) => $q->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>', now()->addDays(90))
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

        $search = $request->search;
        $rhuFilter = $request->rhu_filter;
        $facilityType = $request->facility_type;
        $storageLoc = $request->storage_location;
        $category = $request->category;
        $batchInput = $request->batch_input;
        $expiryFilter = $request->expiry_filter;
        $healthUnitId = session('health_unit_id');
        $isPhoAdmin = is_null($healthUnitId);
        $phoId = $isPhoAdmin ? HealthUnit::phoId() : null;

        $query = Inventory::with(['medicine', 'healthUnit'])
            ->when($healthUnitId, function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId);
            })
            ->when($isPhoAdmin && $rhuFilter === 'pho' && $phoId, function ($q) use ($phoId) {
                $q->where('health_unit_id', $phoId);
            })
            ->when($isPhoAdmin && $rhuFilter && $rhuFilter !== 'pho', function ($q) use ($rhuFilter) {
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

        $query = $this->applyBatchAndExpiryFilters($query, $request);

        $inventories = $query
            ->orderBy('id', 'desc')
            ->paginate(config('pagination.inventory', 20))
            ->appends([
                'search' => $search,
                'rhu_filter' => $rhuFilter,
                'facility_type' => $facilityType,
                'storage_location' => $storageLoc,
                'category' => $category,
                'batch_input' => $batchInput,
                'expiry_filter' => $expiryFilter,
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
            'allocated_stock' => 'required|integer|min:0',
            'batch_no' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
            'storage_location' => 'nullable|string|max:255',
            'pack_type' => 'nullable|string|max:50',
            'pieces_per_pack' => 'nullable|integer|min:1',
        ]);

        $inventory = Inventory::create([
            'medicine_id' => $request->medicine_id,
            'health_unit_id' => session('health_unit_id'),
            'available_stock' => $request->available_stock,
            'allocated_stock' => $request->allocated_stock,
            'batch_no' => $request->batch_no,
            'expiration_date' => $request->expiration_date,
            'storage_location' => $request->storage_location,
            'pack_type' => $request->pack_type,
            'pieces_per_pack' => $request->pieces_per_pack,
        ]);

        $medicine = Medicine::find($inventory->medicine_id);

        if ($request->available_stock > 0) {
            StockLog::create([
                'medicine_id' => $inventory->medicine_id,
                'health_unit_id' => $inventory->health_unit_id,
                'quantity_added' => $request->available_stock,
                'batch_no' => $inventory->batch_no,
                'storage_location' => $inventory->storage_location,
                'date_added' => now()->toDateString(),
                'added_by' => session('user_id'),
                'remarks' => 'Added — Manual inventory entry (Inventory #'.$inventory->id.')',
            ]);
        }

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
            $inventory = $this->findInventoryForCurrentUser($id);
            $inventory = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            $request->validate([
                'available_stock' => 'required|integer|min:0',
                'allocated_stock' => 'required|integer|min:0',
                'batch_no' => 'nullable|string|max:100',
                'expiration_date' => 'nullable|date',
                'storage_location' => 'nullable|string|max:255',
                'pack_type' => 'nullable|string|max:50',
                'pieces_per_pack' => 'nullable|integer|min:1',
            ]);

            $oldAvailable = (int) $inventory->available_stock;
            $newAvailable = (int) $request->available_stock;
            $delta = $newAvailable - $oldAvailable;

            $inventory->update([
                'available_stock' => $request->available_stock,
                'allocated_stock' => $request->allocated_stock,
                'batch_no' => $request->batch_no,
                'expiration_date' => $request->expiration_date,
                'storage_location' => $request->storage_location,
                'pack_type' => $request->pack_type,
                'pieces_per_pack' => $request->pieces_per_pack,
            ]);

            $medicine = Medicine::find($inventory->medicine_id);

            if ($delta !== 0) {
                $remarksPrefix = $delta > 0 ? 'Added' : 'Released';
                StockLog::create([
                    'medicine_id' => $inventory->medicine_id,
                    'health_unit_id' => $inventory->health_unit_id,
                    'quantity_added' => abs($delta),
                    'batch_no' => $inventory->batch_no,
                    'storage_location' => $inventory->storage_location,
                    'date_added' => now()->toDateString(),
                    'added_by' => session('user_id'),
                    'remarks' => $remarksPrefix.' — Inventory adjusted ('.
                                          ($delta > 0 ? '+' : '-').abs($delta).' units, '.
                                          'Inventory #'.$inventory->id.')',
                ]);
            }

            ActivityLogger::log(
                'Inventory',
                'Update',
                'Updated inventory for '.($medicine->medicine_name ?? 'medicine').
                ' | Available: '.$inventory->available_stock.
                ', Allocated: '.$inventory->allocated_stock.
                ($inventory->storage_location ? ' | Storage: '.$inventory->storage_location : '').
                ($inventory->pack_type && $inventory->pieces_per_pack
                    ? ' | Packaging: '.$inventory->pieces_per_pack.' per '.$inventory->pack_type
                    : '')
            );

            return redirect()->route('inventory.index')->with('success', 'Inventory record updated successfully.');
        });
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        return DB::transaction(function () use ($id) {
            $inventory = $this->findInventoryForCurrentUser($id);
            $inventory = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
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
        $healthUnitId = session('health_unit_id');
        $isPhoAdmin = is_null($healthUnitId);
        $phoId = $isPhoAdmin ? HealthUnit::phoId() : null;

        $query = Inventory::with(['medicine', 'healthUnit'])
            ->when($healthUnitId, function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId);
            })
            ->when($isPhoAdmin && $rhuFilter === 'pho' && $phoId, function ($q) use ($phoId) {
                $q->where('health_unit_id', $phoId);
            })
            ->when($isPhoAdmin && $rhuFilter && $rhuFilter !== 'pho', function ($q) use ($rhuFilter) {
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

        $query = $this->applyBatchAndExpiryFilters($query, $request);

        $inventories = $query->orderByDesc('id')->get();

        $filename = 'inventory_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($inventories) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
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

                fputcsv($handle, [
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

        $nextShipmentNo = 'DOH-'.now()->format('Y').'-'.str_pad(
            (DohShipment::whereYear('received_date', now()->year)->count() + 1),
            4, '0', STR_PAD_LEFT
        );

        return view('inventory.pho_receive_create', compact('medicines', 'nextShipmentNo'));
    }

    /** Store new shipment */
    public function storePhoReceive(Request $request)
    {
        if ($redirect = $this->guardPhoAdmin()) {
            return $redirect;
        }

        $validated = $request->validate([
            'shipment_no' => 'nullable|string|max:50',
            'reference_no' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:150',
            'received_date' => 'required|date',
            'remarks' => 'nullable|string|max:2000',

            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.batch_no' => 'nullable|string|max:100',
            'items.*.expiration_date' => 'nullable|date',
            'items.*.storage_location' => 'nullable|string|max:255',
        ], [
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
            $shipment = DB::transaction(function () use ($validated, $phoId) {
                HealthUnit::whereKey($phoId)->lockForUpdate()->firstOrFail();

                $shipment = DohShipment::create([
                    'shipment_no' => $validated['shipment_no'] ?? null,
                    'reference_no' => $validated['reference_no'] ?? null,
                    'supplier' => $validated['supplier'] ?? 'Department of Health',
                    'received_date' => $validated['received_date'],
                    'received_by' => session('user_id'),
                    'remarks' => $validated['remarks'] ?? null,
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

                return $shipment;
            });
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('error', 'Failed to save shipment: '.$e->getMessage());
        }

        ActivityLogger::log(
            'Inventory',
            'PHO Receive',
            'Recorded DOH shipment #'.$shipment->id.
            ($shipment->shipment_no ? ' ('.$shipment->shipment_no.')' : '').
            ' with '.count($validated['items']).' item(s).'
        );

        return redirect()->route('inventory.pho_receive.show', $shipment->id)
            ->with('success', 'DOH shipment recorded. Stock added to PHO inventory.');
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

        $shipment = DohShipment::with('items.medicine')->findOrFail($id);
        $phoId = HealthUnit::phoId();

        if (! $phoId) {
            return back()->with('error', 'PHO record not found.');
        }

        try {
            DB::transaction(function () use ($shipment, $phoId) {
                $shipment = DohShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
                foreach ($shipment->items as $item) {
                    $inv = Inventory::where('medicine_id', $item->medicine_id)
                        ->where('health_unit_id', $phoId)
                        ->where('batch_no', $item->batch_no)
                        ->where('expiration_date', $item->expiration_date)
                        ->lockForUpdate()->first();

                    if (! $inv || $inv->available_stock < $item->quantity) {
                        throw new \RuntimeException('Cannot reverse shipment: batch stock has already been used.');
                    }
                    $inv->decrement('available_stock', $item->quantity);

                    StockLog::create([
                        'medicine_id' => $item->medicine_id,
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

                $shipment->items()->delete();
                $shipment->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Delete failed: '.$e->getMessage());
        }

        ActivityLogger::log(
            'Inventory',
            'PHO Receive',
            'Deleted DOH shipment #'.$shipment->id.
            ($shipment->shipment_no ? ' ('.$shipment->shipment_no.')' : '').
            ' and reversed inventory.'
        );

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

            fputcsv($handle, [
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
                fputcsv($handle, [
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

    private function findInventoryForCurrentUser($id)
    {
        $query = Inventory::query();

        if (session('health_unit_id')) {
            $query->where('health_unit_id', session('health_unit_id'));
        }

        return $query->findOrFail($id);
    }
}
