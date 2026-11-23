<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\AllocationConsumption;
use App\Models\DispensingRecord;
use App\Models\Inventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Recipient stock is traceable only for allocations explicitly issued with the ledger enabled. */
class AllocationDispensingService
{
    public function remainingBalances(?array $unitIds = null, ?int $recipientId = null, ?int $medicineId = null, bool $lock = false, ?string $dispensingDate = null, ?int $restoreRecordId = null): Collection
    {
        $query = Allocation::where('dispensing_enabled', true)->whereNotNull('recipient_user_id')
            ->whereNull('destination_health_unit_id')->whereIn('status', ['completed', 'received'])
            ->whereDate('date_allocated', '<=', $dispensingDate ?? today()->toDateString())
            ->when($unitIds !== null, fn ($q) => $q->whereIn('health_unit_id', $unitIds))
            ->when($recipientId !== null, fn ($q) => $q->where('recipient_user_id', $recipientId))
            ->when($medicineId !== null, fn ($q) => $q->where('medicine_id', $medicineId))->orderBy('id');
        $allocations = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        if ($allocations->isEmpty()) {
            return collect();
        }

        $movementQuery = DB::table('stock_movements')->where('owner_type', 'allocations')
            ->whereIn('owner_id', $allocations->keys())->whereNull('reversed_at')->whereNull('reverses_id')
            ->where('allocated_delta', '>', 0);
        $movements = ($lock ? $movementQuery->lockForUpdate() : $movementQuery)->get();
        $batchQuery = Inventory::whereIn('id', $movements->pluck('inventory_id'))->withReceiptDate()->orderBy('id');
        $batches = ($lock ? $batchQuery->lockForUpdate() : $batchQuery)->get()->keyBy('id');
        $consumptionQuery = AllocationConsumption::whereIn('allocation_id', $allocations->keys())->whereNull('reversed_at')
            ->when($restoreRecordId, fn ($query) => $query->where('dispensing_record_id', '!=', $restoreRecordId));
        $balanceKey = fn ($row) => $row->allocation_id.':'.$row->inventory_id;
        $consumed = $lock
            ? $consumptionQuery->lockForUpdate()->get(['allocation_id', 'inventory_id', 'quantity'])->groupBy($balanceKey)
                ->map(fn ($rows) => (int) $rows->sum('quantity'))
            : $consumptionQuery->selectRaw('allocation_id, inventory_id, SUM(quantity) as quantity')->groupBy('allocation_id', 'inventory_id')
                ->get()->keyBy($balanceKey)->map(fn ($row) => (int) $row->quantity);

        return $movements->groupBy(fn ($row) => $row->owner_id.':'.$row->inventory_id)->map(function ($rows, $key) use ($allocations, $batches, $consumed) {
            $movement = $rows->first();
            $allocation = $allocations->get($movement->owner_id);
            $batch = $batches->get($movement->inventory_id);
            if (! $batch || (int) $batch->health_unit_id !== (int) $allocation->health_unit_id
                || (int) $batch->medicine_id !== (int) $allocation->medicine_id
                || ($batch->expiration_date && $batch->expiration_date->lt(today()))) {
                return null;
            }
            $quantity = (int) $rows->sum('allocated_delta') - $consumed->get($key, 0);

            return $quantity > 0 ? ['allocation_id' => (int) $allocation->id, 'inventory_id' => (int) $batch->id,
                'health_unit_id' => (int) $allocation->health_unit_id, 'medicine_id' => (int) $allocation->medicine_id,
                'recipient_user_id' => (int) $allocation->recipient_user_id, 'quantity' => $quantity, 'batch' => $batch] : null;
        })->filter()->sortBy(fn ($row) => $row['batch']->stockRotationKey().':'.sprintf('%020d', $row['allocation_id']))->values();
    }

    /** Use the same FEFO portions for a read-only preview and for the locked stock issue. */
    public function plan(DispensingRecord $record, bool $lock, ?DispensingRecord $prior = null): array
    {
        $restoreRecordId = $prior?->stock_source === 'allocation' ? $prior->id : null;
        $balances = $this->remainingBalances([(int) $record->health_unit_id], (int) $record->dispensed_by,
            (int) $record->medicine_id, $lock, $record->date_dispensed->toDateString(), $restoreRecordId);
        $remaining = (int) $record->quantity_dispensed;
        if ($balances->sum('quantity') < $remaining) {
            throw ValidationException::withMessages(['stock' => 'Insufficient received allocation stock. Confirm receipt of new supplies or select shared RHU stock.']);
        }
        $restored = $restoreRecordId === null ? collect() : AllocationConsumption::where('dispensing_record_id', $restoreRecordId)
            ->whereNull('reversed_at')->get()->groupBy('inventory_id')->map(fn ($rows) => (int) $rows->sum('quantity'));
        $used = [];
        $allocatedRemaining = [];
        foreach ($balances as $balance) {
            if ($remaining === 0) {
                break;
            }
            $batch = $balance['batch'];
            $take = min($remaining, $balance['quantity']);
            $allocatedRemaining[$batch->id] ??= $batch->allocated_stock + $restored->get($batch->id, 0);
            if ($allocatedRemaining[$batch->id] < $take) {
                throw ValidationException::withMessages(['stock' => 'The allocated batch balance needs reconciliation before dispensing.']);
            }
            $allocatedRemaining[$batch->id] -= $take;
            $used[] = ['batch' => $batch, 'qty' => $take, 'allocation_id' => $balance['allocation_id']];
            $remaining -= $take;
        }

        return $used;
    }

    /** Call within the transaction after obtaining a locked plan. */
    public function consume(DispensingRecord $record, array $used): void
    {
        foreach ($used as ['batch' => $batch, 'qty' => $take, 'allocation_id' => $allocationId]) {
            $batch->decrement('allocated_stock', $take);
            $movementId = app(StockMovementService::class)->record($record, $batch, 0, -$take);
            AllocationConsumption::create(['allocation_id' => $allocationId, 'dispensing_record_id' => $record->id,
                'inventory_id' => $batch->id, 'stock_movement_id' => $movementId, 'quantity' => $take]);
        }
    }

    public function inventories(int $healthUnitId, int $recipientId, ?DispensingRecord $record = null): Collection
    {
        $balances = $this->remainingBalances([$healthUnitId], $recipientId);
        // An edit can reuse the current record's stock after its exact reversal.
        if ($record && $record->stock_source === 'allocation' && (int) $record->dispensed_by === $recipientId) {
            foreach (AllocationConsumption::where('dispensing_record_id', $record->id)->whereNull('reversed_at')->get() as $consumption) {
                $batch = Inventory::withReceiptDate()->find($consumption->inventory_id);
                if ($batch && (! $batch->expiration_date || $batch->expiration_date->gte(today()))) {
                    $balances->push(['inventory_id' => $batch->id, 'quantity' => $consumption->quantity, 'batch' => $batch]);
                }
            }
        }

        return $balances->groupBy('inventory_id')->map(function ($rows) {
            $batch = clone $rows->first()['batch'];
            $batch->available_stock = (int) $rows->sum('quantity');

            return $batch;
        })->sortBy(fn ($batch) => $batch->stockRotationKey())->values();
    }
}
