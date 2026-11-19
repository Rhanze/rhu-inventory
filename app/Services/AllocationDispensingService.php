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
    public function remainingBalances(?array $unitIds = null, ?int $recipientId = null, ?int $medicineId = null, bool $lock = false, ?string $dispensingDate = null): Collection
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

        $movements = DB::table('stock_movements')->where('owner_type', 'allocations')
            ->whereIn('owner_id', $allocations->keys())->whereNull('reversed_at')->whereNull('reverses_id')
            ->where('allocated_delta', '>', 0)->get();
        $batchQuery = Inventory::whereIn('id', $movements->pluck('inventory_id'))->withReceiptDate()->orderBy('id');
        $batches = ($lock ? $batchQuery->lockForUpdate() : $batchQuery)->get()->keyBy('id');
        $consumed = AllocationConsumption::whereIn('allocation_id', $allocations->keys())->whereNull('reversed_at')
            ->selectRaw('allocation_id, inventory_id, SUM(quantity) as quantity')->groupBy('allocation_id', 'inventory_id')
            ->get()->keyBy(fn ($row) => $row->allocation_id.':'.$row->inventory_id);

        return $movements->groupBy(fn ($row) => $row->owner_id.':'.$row->inventory_id)->map(function ($rows, $key) use ($allocations, $batches, $consumed) {
            $movement = $rows->first();
            $allocation = $allocations->get($movement->owner_id);
            $batch = $batches->get($movement->inventory_id);
            if (! $batch || (int) $batch->health_unit_id !== (int) $allocation->health_unit_id
                || (int) $batch->medicine_id !== (int) $allocation->medicine_id
                || ($batch->expiration_date && $batch->expiration_date->lt(today()))) {
                return null;
            }
            $quantity = (int) $rows->sum('allocated_delta') - (int) ($consumed->get($key)?->quantity ?? 0);

            return $quantity > 0 ? ['allocation_id' => (int) $allocation->id, 'inventory_id' => (int) $batch->id,
                'health_unit_id' => (int) $allocation->health_unit_id, 'medicine_id' => (int) $allocation->medicine_id,
                'recipient_user_id' => (int) $allocation->recipient_user_id, 'quantity' => $quantity, 'batch' => $batch] : null;
        })->filter()->sortBy(fn ($row) => $row['batch']->stockRotationKey().':'.sprintf('%020d', $row['allocation_id']))->values();
    }

    /** Call within the dispensing transaction; allocation locks serialize consumption and reversal. */
    public function consume(DispensingRecord $record, int $recipientId): array
    {
        $balances = $this->remainingBalances([(int) $record->health_unit_id], $recipientId, (int) $record->medicine_id, true, $record->date_dispensed->toDateString());
        $remaining = (int) $record->quantity_dispensed;
        if ($balances->sum('quantity') < $remaining) {
            throw ValidationException::withMessages(['stock' => 'Insufficient received allocation stock. Confirm receipt of new supplies or select shared RHU stock.']);
        }
        $used = [];
        foreach ($balances as $balance) {
            if ($remaining === 0) {
                break;
            }
            $batch = $balance['batch'];
            $take = min($remaining, $balance['quantity']);
            if ($batch->allocated_stock < $take) {
                throw ValidationException::withMessages(['stock' => 'The allocated batch balance needs reconciliation before dispensing.']);
            }
            $batch->decrement('allocated_stock', $take);
            $movementId = app(StockMovementService::class)->record($record, $batch, 0, -$take);
            AllocationConsumption::create(['allocation_id' => $balance['allocation_id'], 'dispensing_record_id' => $record->id,
                'inventory_id' => $batch->id, 'stock_movement_id' => $movementId, 'quantity' => $take]);
            $used[] = ['batch' => $batch, 'qty' => $take, 'allocation_id' => $balance['allocation_id']];
            $remaining -= $take;
        }

        return $used;
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
