<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\StockLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exact, append-only batch movements. Call within the owner's transaction. */
class StockMovementService
{
    public function record(Model $owner, Inventory $batch, int $availableDelta, int $allocatedDelta = 0): void
    {
        DB::table('stock_movements')->insert([
            'owner_type' => $owner->getTable(), 'owner_id' => $owner->getKey(),
            'inventory_id' => $batch->id, 'available_delta' => $availableDelta,
            'allocated_delta' => $allocatedDelta, 'created_at' => now(),
        ]);
    }

    public function reverse(Model $owner): void
    {
        $query = DB::table('stock_movements')->where('owner_type', $owner->getTable())->where('owner_id', $owner->id);
        $movements = (clone $query)->whereNull('reversed_at')->whereNull('reverses_id')->orderBy('inventory_id')->lockForUpdate()->get();
        if ($movements->isEmpty()) {
            // Old records may be reversed only when their per-batch logs resolve uniquely.
            $this->importLegacy($owner);
            $movements = (clone $query)->whereNull('reversed_at')->whereNull('reverses_id')->orderBy('inventory_id')->lockForUpdate()->get();
        }
        foreach ($movements as $movement) {
            $batch = Inventory::whereKey($movement->inventory_id)->lockForUpdate()->first();
            if (! $batch || $batch->available_stock - $movement->available_delta < 0 || $batch->allocated_stock - $movement->allocated_delta < 0) {
                throw ValidationException::withMessages(['stock' => 'This movement cannot be reversed: its original batch is missing or its stock has already been used.']);
            }
            $batch->update([
                'available_stock' => $batch->available_stock - $movement->available_delta,
                'allocated_stock' => $batch->allocated_stock - $movement->allocated_delta,
            ]);
            DB::table('stock_movements')->where('id', $movement->id)->update(['reversed_at' => now()]);
            DB::table('stock_movements')->insert([
                'owner_type' => $owner->getTable(), 'owner_id' => $owner->id, 'inventory_id' => $batch->id,
                'available_delta' => -$movement->available_delta, 'allocated_delta' => -$movement->allocated_delta,
                'reverses_id' => $movement->id, 'created_at' => now(),
            ]);
        }
        $foreignKey = match ($owner->getTable()) {
            'allocations' => 'allocation_id', 'transfer_requests' => 'transfer_id', default => 'dispensing_record_id'
        };
        StockLog::where($foreignKey, $owner->id)->whereNull('voided_at')->update(['voided_at' => now()]);
    }

    private function importLegacy(Model $owner): void
    {
        $allocation = $owner->getTable() === 'allocations';
        if ($allocation && ! $owner->inventory_id && in_array($owner->status, ['pending', 'rejected'])) {
            return;
        }
        $key = $allocation ? 'allocation_id' : 'dispensing_record_id';
        $logs = StockLog::where($key, $owner->id)->whereNull('voided_at')->orderBy('id')->get();
        $expected = (int) ($allocation ? $owner->quantity_allocated : $owner->quantity_dispensed);
        $sourceQuantity = 0;
        foreach ($logs as $log) {
            $isSource = (int) $log->health_unit_id === (int) $owner->health_unit_id;
            $matches = Inventory::where('medicine_id', $owner->medicine_id)->where('health_unit_id', $log->health_unit_id)->where('batch_no', $log->batch_no);
            if ($log->inventory_id) {
                $matches->whereKey($log->inventory_id);
            } elseif (preg_match('/Exp: ([A-Za-z]+ \d{1,2}, \d{4})/', $log->remarks ?? '', $match)) {
                $matches->whereDate('expiration_date', Carbon::parse($match[1])->toDateString());
            }
            $batches = $matches->lockForUpdate()->get();
            if ($batches->count() !== 1) {
                throw ValidationException::withMessages(['stock' => 'Historical batch details are ambiguous. Reconcile the original movement before reversing this record.']);
            }
            $quantity = (int) $log->quantity_added;
            $this->record($owner, $batches->first(), $isSource ? -$quantity : $quantity, $allocation && ! $owner->destination_health_unit_id ? $quantity : 0);
            if ($isSource) {
                $sourceQuantity += $quantity;
            }
        }
        if ($sourceQuantity !== $expected || ($allocation && $owner->destination_health_unit_id && $logs->where('health_unit_id', $owner->destination_health_unit_id)->sum('quantity_added') !== $expected)) {
            throw ValidationException::withMessages(['stock' => 'The historical stock movements are incomplete. Reconcile them before reversing this record.']);
        }
    }
}
