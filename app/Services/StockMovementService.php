<?php

namespace App\Services;

use App\Models\DispensingRecord;
use App\Models\Inventory;
use App\Models\StockLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exact, append-only batch movements. Call within the owner's transaction. */
class StockMovementService
{
    public function record(Model $owner, Inventory $batch, int $availableDelta, int $allocatedDelta = 0, ?string $method = null, ?string $reason = null): int
    {
        return DB::table('stock_movements')->insertGetId([
            'owner_type' => $owner->getTable(), 'owner_id' => $owner->getKey(),
            'inventory_id' => $batch->id, 'available_delta' => $availableDelta,
            'allocated_delta' => $allocatedDelta, 'created_at' => now(),
            'performed_by' => session('user_id'), 'reason' => $reason,
            'method' => $method ?? ($availableDelta < 0 || ($owner->getTable() === 'dispensing_records' && $allocatedDelta < 0)
                ? 'FEFO with FIFO receipt-date ties' : 'Exact batch movement'),
        ]);
    }

    public function reverse(Model $owner, string $reason = 'Stock record corrected or cancelled'): void
    {
        if ($owner->getTable() === 'allocations') {
            DB::table('allocations')->where('id', $owner->id)->lockForUpdate()->first();
            if (DB::table('allocation_consumptions')->where('allocation_id', $owner->id)->whereNull('reversed_at')->exists()) {
                throw ValidationException::withMessages(['stock' => 'This allocation has been dispensed. Reverse its dispensing records before changing or returning the allocation.']);
            }
        } elseif ($owner->getTable() === 'dispensing_records' && $owner->stock_source === 'allocation') {
            $allocationIds = DB::table('allocation_consumptions')->where('dispensing_record_id', $owner->id)
                ->whereNull('reversed_at')->pluck('allocation_id')->unique()->sort()->values();
            DB::table('allocations')->whereIn('id', $allocationIds)->orderBy('id')->lockForUpdate()->get();
        }
        $query = $this->activeMovements($owner)->orderBy('inventory_id')->lockForUpdate();
        $movements = $query->get();
        if ($movements->isEmpty()) {
            // Old records may be reversed only when their per-batch logs resolve uniquely.
            $this->importLegacy($owner);
            $movements = $query->get();
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
                'performed_by' => session('user_id'), 'reason' => $reason, 'method' => 'Exact original batch reversal',
            ]);
        }
        if ($owner->getTable() === 'dispensing_records') {
            DB::table('allocation_consumptions')->where('dispensing_record_id', $owner->id)->whereNull('reversed_at')
                ->update(['reversed_at' => now(), 'updated_at' => now()]);
        }
        $foreignKey = match ($owner->getTable()) {
            'allocations' => 'allocation_id', 'transfer_requests' => 'transfer_id', default => 'dispensing_record_id'
        };
        StockLog::where($foreignKey, $owner->id)->whereNull('voided_at')->update(['voided_at' => now()]);
    }

    private function activeMovements(Model $owner)
    {
        return DB::table('stock_movements')->where('owner_type', $owner->getTable())->where('owner_id', $owner->id)
            ->whereNull('reversed_at')->whereNull('reverses_id');
    }

    /** Available units that a correction would restore, keyed by inventory ID. */
    public function dispensingRestoration(DispensingRecord $record): Collection
    {
        $movements = $this->activeMovements($record)->get();
        if ($movements->isNotEmpty()) {
            return $movements->groupBy('inventory_id')->map(fn ($rows) => -(int) $rows->sum('available_delta'));
        }

        return $this->legacyMovements($record, lock: false)->groupBy('batch.id')
            ->map(fn ($rows) => $rows->sum(fn ($row) => ($row['is_source'] ? 1 : -1) * $row['log']->quantity_added));
    }

    private function importLegacy(Model $owner): void
    {
        foreach ($this->legacyMovements($owner, lock: true) as $row) {
            ['batch' => $batch, 'is_source' => $isSource, 'log' => $log] = $row;
            $quantity = (int) $log->quantity_added;
            $movementId = $this->record($owner, $batch, $isSource ? -$quantity : $quantity,
                $owner->getTable() === 'allocations' && ! $owner->destination_health_unit_id ? $quantity : 0);
            DB::table('stock_movements')->where('id', $movementId)->update([
                'performed_by' => $log->added_by, 'reason' => $log->remarks, 'method' => null,
            ]);
        }
    }

    private function legacyMovements(Model $owner, bool $lock): Collection
    {
        $allocation = $owner->getTable() === 'allocations';
        if ($allocation && ! $owner->inventory_id && in_array($owner->status, ['pending', 'rejected'])) {
            return collect();
        }
        $key = $allocation ? 'allocation_id' : 'dispensing_record_id';
        $logs = StockLog::where($key, $owner->id)->whereNull('voided_at')->orderBy('id')->get();
        $expected = (int) ($allocation ? $owner->quantity_allocated : $owner->quantity_dispensed);
        $sourceQuantity = 0;
        $resolved = collect();
        foreach ($logs as $log) {
            $isSource = (int) $log->health_unit_id === (int) $owner->health_unit_id;
            $matches = Inventory::where('medicine_id', $owner->medicine_id)->where('health_unit_id', $log->health_unit_id)->where('batch_no', $log->batch_no);
            if ($log->inventory_id) {
                $matches->whereKey($log->inventory_id);
            } elseif (preg_match('/Exp: ([A-Za-z]+ \d{1,2}, \d{4})/', $log->remarks ?? '', $match)) {
                $matches->whereDate('expiration_date', Carbon::parse($match[1])->toDateString());
            }
            $batches = ($lock ? $matches->lockForUpdate() : $matches)->get();
            if ($batches->count() !== 1) {
                throw ValidationException::withMessages(['stock' => 'Historical batch details are ambiguous. Reconcile the original movement before reversing this record.']);
            }
            $resolved->push(['batch' => $batches->first(), 'is_source' => $isSource, 'log' => $log]);
            if ($isSource) {
                $sourceQuantity += (int) $log->quantity_added;
            }
        }
        if ($sourceQuantity !== $expected || ($allocation && $owner->destination_health_unit_id && $logs->where('health_unit_id', $owner->destination_health_unit_id)->sum('quantity_added') !== $expected)) {
            throw ValidationException::withMessages(['stock' => 'The historical stock movements are incomplete. Reconcile them before reversing this record.']);
        }

        return $resolved;
    }
}
