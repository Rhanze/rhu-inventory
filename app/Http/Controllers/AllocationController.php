<?php

namespace App\Http\Controllers;

use App\Helpers\ActivityLogger;
use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\StockLog;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AllocationController extends Controller
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

        $search = $request->search;
        $rhuFilter = $request->rhu_filter;
        $category = $request->category;
        $batchInput = $request->batch_input;
        $expiryFilter = $request->expiry_filter;
        $healthUnitId = session('health_unit_id');

        $allocations = Allocation::with(['medicine', 'allocatedBy', 'recipient', 'healthUnit', 'destinationHealthUnit', 'inventory'])
            ->when($healthUnitId, function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId);
            })
            ->when(is_null($healthUnitId) && $rhuFilter, function ($q) use ($rhuFilter) {
                $q->where('health_unit_id', $rhuFilter);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('medicine', function ($mq) use ($search) {
                        $mq->where('medicine_name', 'like', "%{$search}%");
                    })
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhereHas('recipient', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('allocatedBy', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            ->orderByDesc('id')
            ->paginate(config('pagination.inventory', 20))
            ->appends([
                'search' => $search,
                'rhu_filter' => $rhuFilter,
                'category' => $category,
                'batch_input' => $batchInput,
                'expiry_filter' => $expiryFilter,
            ]);

        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();

        return view('allocation.index', compact('allocations', 'healthUnits'));
    }

    public function create()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('allocation.index')
                ->with('error', 'Allocation creation is only available at the RHU level.');
        }

        $inventories = Inventory::with('medicine')
            ->where('health_unit_id', session('health_unit_id'))
            ->where('available_stock', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', now()->toDateString());
            })
            ->orderBy('medicine_id')
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id')
            ->get();

        $priorDestinations = Allocation::where('health_unit_id', session('health_unit_id'))
            ->whereNotNull('destination')
            ->where('destination', '!=', '')
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('destination')
            ->unique()
            ->values();

        $recipients = User::where('health_unit_id', session('health_unit_id'))
            ->orderBy('name')
            ->get();

        return view('allocation.create', compact('inventories', 'priorDestinations', 'recipients'));
    }

    public function store(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (is_null(session('health_unit_id'))) {
            return redirect()->route('allocation.index')
                ->with('error', 'Allocation creation is only available at the RHU level.');
        }

        $request->validate([
            'inventory_id' => 'required|exists:inventory,id',
            'destination' => 'required|string|max:255',
            'quantity_allocated' => 'required|integer|min:1',
            'date_allocated' => 'required|date',
            'recipient_user_id' => ['nullable', Rule::exists('users', 'id')->where('health_unit_id', session('health_unit_id'))->where('is_active', true)],
        ]);

        // Identify the medicine via the chosen row — actual batches used come
        // from FEFO logic below, ignoring the batch the user picked.
        $requestedRow = Inventory::with('medicine')
            ->where('health_unit_id', session('health_unit_id'))
            ->findOrFail($request->inventory_id);

        $medicine = $requestedRow->medicine;

        if (! $medicine) {
            return back()->withInput()->with('error', 'Selected inventory has no medicine record.');
        }

        $recipientId = $request->filled('recipient_user_id') ? (int) $request->recipient_user_id : null;
        $status = $recipientId ? 'awaiting_receipt' : 'completed';

        $allocation = null;

        try {
            DB::transaction(function () use ($request, $medicine, &$allocation, $recipientId, $status) {
                // ── FEFO: pull earliest-expiring batches ──
                $batches = Inventory::availableFefo(
                    session('health_unit_id'),
                    $medicine->id
                )->lockForUpdate()->get();

                $totalAvailable = $batches->sum('available_stock');

                if ($totalAvailable < $request->quantity_allocated) {
                    throw new \Exception(
                        'Insufficient stock. Available across all unexpired batches: '.
                        $totalAvailable.' unit(s). Requested: '.$request->quantity_allocated.'.'
                    );
                }

                $remaining = (int) $request->quantity_allocated;
                $usedBatches = [];
                $primaryBatch = null;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($batch->available_stock, $remaining);
                    if ($take <= 0) {
                        continue;
                    }

                    $batch->update([
                        'available_stock' => $batch->available_stock - $take,
                        'allocated_stock' => $batch->allocated_stock + $take,
                    ]);

                    if ($primaryBatch === null) {
                        $primaryBatch = $batch;
                    }

                    $usedBatches[] = ['batch' => $batch, 'qty' => $take];
                    $remaining -= $take;
                }

                $allocation = Allocation::create([
                    'medicine_id' => $medicine->id,
                    'inventory_id' => $primaryBatch->id,
                    'health_unit_id' => session('health_unit_id'),
                    'destination' => $request->destination,
                    'quantity_allocated' => $request->quantity_allocated,
                    'date_allocated' => $request->date_allocated,
                    'allocated_by' => session('user_id'),
                    'recipient_user_id' => $recipientId,
                    'status' => $status,
                ]);

                // One StockLog row per FEFO batch used
                foreach ($usedBatches as $used) {
                    $batch = $used['batch'];
                    $take = $used['qty'];
                    app(StockMovementService::class)->record($allocation, $batch, -$take, $allocation->destination_health_unit_id ? 0 : $take);
                    if ($allocation->destination_health_unit_id) {
                        $destinationBatch = Inventory::where('medicine_id', $batch->medicine_id)->where('health_unit_id', $allocation->destination_health_unit_id)->where('batch_no', $batch->batch_no)->where('expiration_date', $batch->expiration_date)->lockForUpdate()->firstOrFail();
                        app(StockMovementService::class)->record($allocation, $destinationBatch, $take);
                    }
                    $batchNote = $batch->batch_no ? ', Batch: '.$batch->batch_no : '';
                    $batchNote .= $batch->expiration_date
                        ? ', Exp: '.$batch->expiration_date->format('M d, Y')
                        : '';

                    StockLog::create([
                        'medicine_id' => $medicine->id,
                        'health_unit_id' => session('health_unit_id'),
                        'quantity_added' => $take,
                        'batch_no' => $batch->batch_no,
                        'storage_location' => $batch->storage_location,
                        'date_added' => $request->date_allocated,
                        'added_by' => session('user_id'),
                        'remarks' => 'Released — Allocated to '.$request->destination.
                                              ' (Allocation #'.$allocation->id.$batchNote.')'.
                                              ($recipientId ? ' — pending recipient confirmation' : ''),
                        'allocation_id' => $allocation->id,
                        'movement_type' => $allocation->destination_health_unit_id ? 'transfer_out' : 'allocation',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($recipientId) {
            $qtyLabel = $allocation->quantity_allocated.' '.
                (($allocation->quantity_allocated == 1) ? 'unit' : 'units');

            app(NotificationService::class)->notifyUser(
                $recipientId,
                session('health_unit_id'),
                'allocation_to_user',
                'Stock Allocated to You',
                $qtyLabel.' of '.($medicine->medicine_name ?? 'medicine').
                    ' — for: '.$request->destination.'. Please confirm receipt.',
                'warning',
                route('notifications.index'),
                ['allocation_id' => $allocation->id],
                null,
                0
            );
        }

        ActivityLogger::log(
            'Allocation',
            'Create',
            'Allocated '.$allocation->quantity_allocated.
            ' unit(s) of '.($medicine->medicine_name ?? 'medicine').
            ' to '.$request->destination.
            ($recipientId ? ' — awaiting recipient confirmation' : '').'.'
        );

        return redirect()->route('allocation.index')
            ->with('success', $recipientId
                ? 'Allocation recorded. Awaiting recipient confirmation.'
                : 'Allocation recorded successfully.');
    }

    public function edit($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $allocation = $this->findAllocationForCurrentUser($id);

        $inventories = Inventory::with('medicine')
            ->where('health_unit_id', $allocation->health_unit_id)
            ->orderByDesc('id')
            ->get();

        $priorDestinations = Allocation::where('health_unit_id', $allocation->health_unit_id)
            ->whereNotNull('destination')
            ->where('destination', '!=', '')
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('destination')
            ->unique()
            ->values();

        $recipients = User::where('health_unit_id', $allocation->health_unit_id)
            ->orderBy('name')
            ->get();

        return view('allocation.edit', compact('allocation', 'inventories', 'priorDestinations', 'recipients'));
    }

    public function update(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $request->validate([
            'inventory_id' => 'required|exists:inventory,id',
            'destination' => 'required|string|max:255',
            'quantity_allocated' => 'required|integer|min:1',
            'date_allocated' => 'required|date',
            'recipient_user_id' => ['nullable', Rule::exists('users', 'id')->where('health_unit_id', session('health_unit_id'))->where('is_active', true)],
        ]);

        $allocation = $this->findAllocationForCurrentUser($id);

        $newRow = Inventory::with('medicine')
            ->where('health_unit_id', $allocation->health_unit_id)
            ->findOrFail($request->inventory_id);

        $medicine = $newRow->medicine;

        $oldQty = $allocation->quantity_allocated;
        $oldDestination = $allocation->destination ?? 'Unknown destination';

        try {
            DB::transaction(function () use ($request, $allocation, $medicine) {
                $allocation = Allocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
                if ($allocation->destination_health_unit_id || in_array($allocation->status, ['pending', 'rejected'])) {
                    throw new \RuntimeException('Reverse a provincial allocation before replacing it; approve a supply request before editing its stock.');
                }
                app(StockMovementService::class)->reverse($allocation);

                $batches = Inventory::availableFefo(
                    $allocation->health_unit_id,
                    $medicine->id
                )->lockForUpdate()->get();

                $totalAvailable = $batches->sum('available_stock');

                if ($totalAvailable < $request->quantity_allocated) {
                    throw new \Exception(
                        'Insufficient stock. Available across all unexpired batches: '.
                        $totalAvailable.' unit(s). Requested: '.$request->quantity_allocated.'.'
                    );
                }

                $remaining = (int) $request->quantity_allocated;
                $usedBatches = [];
                $primaryBatch = null;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($batch->available_stock, $remaining);
                    if ($take <= 0) {
                        continue;
                    }

                    $batch->update([
                        'available_stock' => $batch->available_stock - $take,
                        'allocated_stock' => $batch->allocated_stock + $take,
                    ]);

                    if ($primaryBatch === null) {
                        $primaryBatch = $batch;
                    }

                    $usedBatches[] = ['batch' => $batch, 'qty' => $take];
                    $remaining -= $take;
                }

                $allocation->update([
                    'medicine_id' => $medicine->id,
                    'inventory_id' => $primaryBatch->id,
                    'destination' => $request->destination,
                    'quantity_allocated' => $request->quantity_allocated,
                    'date_allocated' => $request->date_allocated,
                    'allocated_by' => session('user_id'),
                    'recipient_user_id' => $request->filled('recipient_user_id') ? (int) $request->recipient_user_id : null,
                ]);

                StockLog::where('allocation_id', $allocation->id)->whereNull('voided_at')->update(['voided_at' => now()]);

                foreach ($usedBatches as $used) {
                    $batch = $used['batch'];
                    $take = $used['qty'];
                    app(StockMovementService::class)->record($allocation, $batch, -$take, $allocation->destination_health_unit_id ? 0 : $take);
                    if ($allocation->destination_health_unit_id) {
                        $destinationBatch = Inventory::where('medicine_id', $batch->medicine_id)->where('health_unit_id', $allocation->destination_health_unit_id)->where('batch_no', $batch->batch_no)->where('expiration_date', $batch->expiration_date)->lockForUpdate()->firstOrFail();
                        app(StockMovementService::class)->record($allocation, $destinationBatch, $take);
                    }
                    $batchNote = $batch->batch_no ? ', Batch: '.$batch->batch_no : '';
                    $batchNote .= $batch->expiration_date
                        ? ', Exp: '.$batch->expiration_date->format('M d, Y')
                        : '';

                    StockLog::create([
                        'medicine_id' => $medicine->id,
                        'health_unit_id' => $allocation->health_unit_id,
                        'quantity_added' => $take,
                        'batch_no' => $batch->batch_no,
                        'storage_location' => $batch->storage_location,
                        'date_added' => $request->date_allocated,
                        'added_by' => session('user_id'),
                        'remarks' => 'Released — Allocated to '.$request->destination.
                                              ' (Allocation #'.$allocation->id.$batchNote.', updated)',
                        'allocation_id' => $allocation->id,
                        'movement_type' => $allocation->destination_health_unit_id ? 'transfer_out' : 'allocation',
                    ]);
                }
            });
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        ActivityLogger::log(
            'Allocation',
            'Update',
            'Updated allocation from '.$oldQty.' unit(s) for '.$oldDestination.
            ' to '.$allocation->quantity_allocated.' unit(s) for '.$request->destination.'.'
        );

        return redirect()->route('allocation.index')->with('success', 'Allocation updated successfully.');
    }

    public function destroy($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $allocation = $this->findAllocationForCurrentUser($id);

        try {
            DB::transaction(function () use ($allocation) {
                $allocation = Allocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
                app(StockMovementService::class)->reverse($allocation);
                $allocation->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        ActivityLogger::log('Allocation', 'Delete', 'Reversed and deleted allocation #'.$id.'.');

        return redirect()->route('allocation.index')->with('success', 'Allocation deleted; original batch movements reversed.');
    }

    public function createProvincialAllocation()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (! is_null(session('health_unit_id'))) {
            return redirect()->route('allocation.index')
                ->with('error', 'Only the Provincial Health Office can perform provincial allocations.');
        }

        $medicines = Medicine::orderBy('medicine_name')->get();
        $healthUnits = HealthUnit::rhuOnly()->orderBy('name')->get();

        return view('allocation.province', compact('medicines', 'healthUnits'));
    }

    public function storeProvincialAllocation(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        if (! is_null(session('health_unit_id'))) {
            return redirect()->route('allocation.index')
                ->with('error', 'Only the Provincial Health Office can perform provincial allocations.');
        }

        $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'destination_health_unit_id' => 'required|exists:health_units,id',
            'quantity_allocated' => 'required|integer|min:1',
            'date_allocated' => 'required|date',
            'batch_no' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
        ]);

        $medicine = Medicine::findOrFail($request->medicine_id);
        $destinationRhuId = (int) $request->destination_health_unit_id;
        $qty = (int) $request->quantity_allocated;

        $destination = HealthUnit::rhuOnly()->find($destinationRhuId);
        if (! $destination) {
            return back()->withInput()->withErrors([
                'destination_health_unit_id' => 'The selected destination must be a Rural Health Unit.',
            ]);
        }

        $phoId = HealthUnit::phoId();
        if (! $phoId) {
            return back()->withInput()
                ->with('error', 'Provincial Health Office record not found in the database.');
        }

        try {
            DB::transaction(function () use ($request, $medicine, $destinationRhuId, $destination, $phoId, $qty) {
                HealthUnit::whereKey($destinationRhuId)->lockForUpdate()->firstOrFail();
                // ── FEFO: pull earliest-expiring PHO batches ──
                $query = Inventory::where('health_unit_id', $phoId)
                    ->where('medicine_id', $medicine->id)
                    ->where('available_stock', '>', 0)
                    ->where(function ($q) {
                        $q->whereNull('expiration_date')
                            ->orWhereDate('expiration_date', '>=', now()->toDateString());
                    });

                // Allow optional narrowing by batch/expiry, but still FEFO-ordered
                if ($request->filled('batch_no')) {
                    $query->where('batch_no', $request->batch_no);
                }
                if ($request->filled('expiration_date')) {
                    $query->where('expiration_date', $request->expiration_date);
                }

                $batches = $query->fefo()->lockForUpdate()->get();

                $totalAvailable = $batches->sum('available_stock');

                if ($totalAvailable < $qty) {
                    throw new \Exception(
                        'PHO has only '.$totalAvailable.' unit(s) of '.$medicine->medicine_name.
                        ' available across unexpired batches. Requested: '.$qty.'.'
                    );
                }

                $remaining = $qty;
                $usedBatches = [];
                $primaryBatch = null;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($batch->available_stock, $remaining);
                    if ($take <= 0) {
                        continue;
                    }

                    $batch->decrement('available_stock', $take);

                    // Mirror into destination RHU inventory
                    $destInventory = Inventory::firstOrCreate(
                        [
                            'medicine_id' => $medicine->id,
                            'health_unit_id' => $destinationRhuId,
                            'batch_no' => $batch->batch_no,
                            'expiration_date' => $batch->expiration_date,
                        ],
                        [
                            'available_stock' => 0,
                            'allocated_stock' => 0,
                            'storage_location' => $batch->storage_location,
                            'pack_type' => $batch->pack_type,
                            'pieces_per_pack' => $batch->pieces_per_pack,
                        ]
                    );

                    $destInventory->increment('available_stock', $take);

                    if ($primaryBatch === null) {
                        $primaryBatch = $batch;
                    }

                    $usedBatches[] = ['batch' => $batch, 'qty' => $take];
                    $remaining -= $take;
                }

                $allocation = Allocation::create([
                    'medicine_id' => $medicine->id,
                    'inventory_id' => $primaryBatch->id,
                    'health_unit_id' => $phoId,
                    'destination_health_unit_id' => $destinationRhuId,
                    'destination' => null,
                    'quantity_allocated' => $qty,
                    'date_allocated' => $request->date_allocated,
                    'allocated_by' => session('user_id'),
                    'status' => 'completed',
                ]);

                foreach ($usedBatches as $used) {
                    $batch = $used['batch'];
                    $take = $used['qty'];
                    app(StockMovementService::class)->record($allocation, $batch, -$take, $allocation->destination_health_unit_id ? 0 : $take);
                    if ($allocation->destination_health_unit_id) {
                        $destinationBatch = Inventory::where('medicine_id', $batch->medicine_id)->where('health_unit_id', $allocation->destination_health_unit_id)->where('batch_no', $batch->batch_no)->where('expiration_date', $batch->expiration_date)->lockForUpdate()->firstOrFail();
                        app(StockMovementService::class)->record($allocation, $destinationBatch, $take);
                    }
                    $batchNote = $batch->batch_no ? ', Batch: '.$batch->batch_no : '';
                    $batchNote .= $batch->expiration_date
                        ? ', Exp: '.$batch->expiration_date->format('M d, Y')
                        : '';

                    StockLog::create([
                        'medicine_id' => $medicine->id,
                        'health_unit_id' => $phoId,
                        'quantity_added' => $take,
                        'batch_no' => $batch->batch_no,
                        'storage_location' => $batch->storage_location,
                        'date_added' => $request->date_allocated,
                        'added_by' => session('user_id'),
                        'remarks' => 'Released — Provincial allocation to '.
                                              $destination->name.
                                              ' (Allocation #'.$allocation->id.$batchNote.')',
                        'allocation_id' => $allocation->id,
                        'movement_type' => $allocation->destination_health_unit_id ? 'transfer_out' : 'allocation',
                    ]);

                    StockLog::create([
                        'medicine_id' => $medicine->id,
                        'health_unit_id' => $destinationRhuId,
                        'quantity_added' => $take,
                        'batch_no' => $batch->batch_no,
                        'storage_location' => $batch->storage_location,
                        'date_added' => $request->date_allocated,
                        'added_by' => session('user_id'),
                        'remarks' => 'Added — Provincial allocation from PHO'.
                                              ' (Allocation #'.$allocation->id.$batchNote.')',
                        'allocation_id' => $allocation->id,
                        'movement_type' => 'transfer_in',
                    ]);
                }
            });
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        ActivityLogger::log(
            'Allocation',
            'Provincial Allocation',
            'PHO allocated '.$qty.' unit(s) of '.$medicine->medicine_name.
            ' to '.$destination->name.'.'
        );

        return redirect()->route('allocation.index')
            ->with('success', 'Provincial allocation recorded. Stock transferred from PHO to '.$destination->name.'.');
    }

    // ═════════════════════════════════════════════════════════════
    // SUPPLY REQUEST APPROVAL
    // ═════════════════════════════════════════════════════════════

    public function approveSupplyRequest($id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $allocation = Allocation::where('health_unit_id', session('health_unit_id'))
            ->where('status', 'pending')
            ->whereNull('inventory_id')
            ->whereNull('destination_health_unit_id')
            ->findOrFail($id);

        $medicine = $allocation->medicine;

        if (! $medicine) {
            return back()->with('error', 'Supply request is missing a medicine record.');
        }

        try {
            DB::transaction(function () use ($allocation, $medicine) {
                $allocation = Allocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
                if ($allocation->status !== 'pending' || $allocation->inventory_id) {
                    throw new \RuntimeException('This request has already been processed.');
                }
                // FEFO deduction across batches
                $batches = Inventory::availableFefo(
                    $allocation->health_unit_id,
                    $medicine->id
                )->lockForUpdate()->get();

                $totalAvailable = $batches->sum('available_stock');
                $needed = (int) $allocation->quantity_allocated;

                if ($totalAvailable < $needed) {
                    throw new \Exception(
                        'Not enough stock to fulfil this request. Available: '.$totalAvailable.
                        ', requested: '.$needed.'.'
                    );
                }

                $remaining = $needed;
                $usedBatches = [];
                $primaryBatch = null;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($batch->available_stock, $remaining);
                    if ($take <= 0) {
                        continue;
                    }

                    $batch->update([
                        'available_stock' => $batch->available_stock - $take,
                        'allocated_stock' => $batch->allocated_stock + $take,
                    ]);

                    if ($primaryBatch === null) {
                        $primaryBatch = $batch;
                    }

                    $usedBatches[] = ['batch' => $batch, 'qty' => $take];
                    $remaining -= $take;
                }

                $allocation->update([
                    'status' => 'approved',
                    'inventory_id' => $primaryBatch->id,
                ]);

                foreach ($usedBatches as $used) {
                    $batch = $used['batch'];
                    $take = $used['qty'];
                    app(StockMovementService::class)->record($allocation, $batch, -$take, $allocation->destination_health_unit_id ? 0 : $take);
                    if ($allocation->destination_health_unit_id) {
                        $destinationBatch = Inventory::where('medicine_id', $batch->medicine_id)->where('health_unit_id', $allocation->destination_health_unit_id)->where('batch_no', $batch->batch_no)->where('expiration_date', $batch->expiration_date)->lockForUpdate()->firstOrFail();
                        app(StockMovementService::class)->record($allocation, $destinationBatch, $take);
                    }
                    $batchNote = $batch->batch_no ? ', Batch: '.$batch->batch_no : '';
                    $batchNote .= $batch->expiration_date
                        ? ', Exp: '.$batch->expiration_date->format('M d, Y')
                        : '';

                    StockLog::create([
                        'medicine_id' => $allocation->medicine_id,
                        'health_unit_id' => $allocation->health_unit_id,
                        'quantity_added' => $take,
                        'batch_no' => $batch->batch_no,
                        'storage_location' => $batch->storage_location,
                        'date_added' => now()->toDateString(),
                        'added_by' => session('user_id'),
                        'remarks' => 'Released — Supply request #'.$allocation->id.
                                              ' approved and stock reserved'.$batchNote,
                        'allocation_id' => $allocation->id,
                        'movement_type' => $allocation->destination_health_unit_id ? 'transfer_out' : 'allocation',
                    ]);
                }
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        app(NotificationService::class)
            ->notifySupplyRequestDecision($allocation->fresh(['medicine']));

        ActivityLogger::log(
            'Supply Request', 'Approve',
            'Approved request #'.$allocation->id.' for '.$allocation->quantity_allocated.
            ' unit(s) of '.$medicine->medicine_name.'.'
        );

        return back()->with('success',
            'Request approved. Stock has been reserved and the midwife has been notified.');
    }

    public function rejectSupplyRequest(Request $request, $id)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $allocation = Allocation::where('health_unit_id', session('health_unit_id'))
            ->where('status', 'pending')
            ->whereNull('inventory_id')
            ->whereNull('destination_health_unit_id')
            ->findOrFail($id);

        $reason = trim((string) $request->rejection_reason);

        DB::transaction(function () use ($allocation, $reason) {
            $allocation = Allocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            abort_unless($allocation->status === 'pending' && ! $allocation->inventory_id, 409);
            $allocation->update([
                'status' => 'rejected',
                'destination' => ($allocation->destination ?? '').
                    ' — REJECTED'.($reason !== '' ? ': '.$reason : ''),
            ]);

        });

        app(NotificationService::class)
            ->notifySupplyRequestDecision($allocation->fresh(['medicine']));

        ActivityLogger::log(
            'Supply Request', 'Reject',
            'Rejected request #'.$allocation->id.' for '.$allocation->quantity_allocated.
            ' unit(s) of '.($allocation->medicine->medicine_name ?? 'medicine').
            ($reason !== '' ? ' — Reason: '.$reason : '').'.'
        );

        return back()->with('success', 'Request rejected. The midwife has been notified.');
    }

    // ═════════════════════════════════════════════════════════════
    // RECIPIENT CONFIRMS RECEIPT
    // ═════════════════════════════════════════════════════════════

    public function markReceived($id)
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }

        $allocation = Allocation::findOrFail($id);

        if ((int) $allocation->recipient_user_id !== (int) session('user_id')) {
            return back()->with('error', 'You are not the recipient of this allocation.');
        }

        if ($allocation->status !== 'awaiting_receipt') {
            return back()->with('error', 'This allocation is not awaiting receipt.');
        }

        $allocation->update(['status' => 'completed']);

        Notification::where('allocation_id', $allocation->id)
            ->where('user_id', session('user_id'))
            ->where('type', 'allocation_to_user')
            ->update(['is_read' => true]);

        ActivityLogger::log(
            'Allocation', 'Received',
            'Confirmed receipt of allocation #'.$allocation->id.' — '.
            $allocation->quantity_allocated.' unit(s) of '.
            ($allocation->medicine->medicine_name ?? 'medicine').'.'
        );

        return back()->with('success', 'Receipt confirmed. Allocation marked as completed.');
    }

    public function exportCsv(Request $request)
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $search = $request->search;
        $rhuFilter = $request->rhu_filter;
        $category = $request->category;
        $healthUnitId = session('health_unit_id');

        $allocations = Allocation::with(['medicine', 'allocatedBy', 'recipient', 'healthUnit', 'destinationHealthUnit', 'inventory'])
            ->when($healthUnitId, function ($q) use ($healthUnitId) {
                $q->where('health_unit_id', $healthUnitId);
            })
            ->when(is_null($healthUnitId) && $rhuFilter, function ($q) use ($rhuFilter) {
                $q->where('health_unit_id', $rhuFilter);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('medicine', function ($mq) use ($search) {
                        $mq->where('medicine_name', 'like', "%{$search}%");
                    })
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhereHas('recipient', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('allocatedBy', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($category, function ($q) use ($category) {
                $q->whereHas('medicine', fn ($mq) => $mq->where('category', $category));
            })
            ->orderByDesc('id')
            ->get();

        $filename = 'allocations_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($allocations) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'Medicine', 'Batch No.', 'Destination',
                'Quantity Allocated', 'Date Allocated', 'Status', 'Recipient', 'Source RHU',
            ]);

            foreach ($allocations as $allocation) {
                $destination = $allocation->destination_health_unit_id
                    ? ($allocation->destinationHealthUnit->name ?? 'N/A').' (RHU)'
                    : ($allocation->destination ?? 'N/A');

                fputcsv($handle, [
                    $allocation->id,
                    $allocation->medicine->medicine_name ?? '',
                    $allocation->inventory->batch_no ?? '',
                    $destination,
                    $allocation->quantity_allocated,
                    $allocation->date_allocated,
                    $allocation->status ?? '—',
                    $allocation->recipient->name ?? '',
                    $allocation->healthUnit->name ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function resolveAllocationInventory(Allocation $allocation): ?Inventory
    {
        if ($allocation->inventory_id) {
            $inv = Inventory::find($allocation->inventory_id);
            if ($inv) {
                return $inv;
            }
        }

        $rhuId = $allocation->destination_health_unit_id ?: $allocation->health_unit_id;

        return Inventory::where('health_unit_id', $rhuId)
            ->where('medicine_id', $allocation->medicine_id)
            ->first();
    }

    private function findAllocationForCurrentUser($id)
    {
        $query = Allocation::query();

        if (session('health_unit_id')) {
            $query->where('health_unit_id', session('health_unit_id'));
        }

        return $query->findOrFail($id);
    }
}
