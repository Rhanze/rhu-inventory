<?php

namespace App\Services;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use Illuminate\Support\Collection;

class StockSummaryService
{
    /** One usable-stock total per facility/medicine, including demand-only stockouts. */
    public function inventories(?array $unitIds = null, bool $includeRecipientStock = false): Collection
    {
        $batches = Inventory::with(['medicine', 'healthUnit'])->when($unitIds !== null, fn ($q) => $q->whereIn('health_unit_id', $unitIds))->get();
        $groups = $batches->groupBy(fn ($row) => $row->health_unit_id.':'.$row->medicine_id);
        $recipientBalances = $includeRecipientStock
            ? app(AllocationDispensingService::class)->remainingBalances($unitIds)->groupBy(fn ($row) => $row['health_unit_id'].':'.$row['medicine_id'])
            : collect();
        $demand = app(StockDemandHistoryService::class)->events($unitIds,
            today()->subWeeks(config('forecasting.history_weeks', 12))->toDateString(), today()->subDay()->toDateString());
        $medicines = Medicine::whereIn('id', $demand->pluck('medicine_id'))->get()->keyBy('id');
        $units = HealthUnit::whereIn('id', $demand->pluck('health_unit_id'))->get()->keyBy('id');
        foreach ($demand as $row) {
            $key = $row->health_unit_id.':'.$row->medicine_id;
            if (! $groups->has($key) && $medicines->has($row->medicine_id) && $units->has($row->health_unit_id)) {
                $empty = new Inventory(['health_unit_id' => $row->health_unit_id, 'medicine_id' => $row->medicine_id, 'available_stock' => 0, 'allocated_stock' => 0]);
                $empty->setRelation('medicine', $medicines[$row->medicine_id])->setRelation('healthUnit', $units[$row->health_unit_id]);
                $groups->put($key, collect([$empty]));
            }
        }

        return $groups->map(function ($rows, $key) use ($includeRecipientStock, $recipientBalances) {
            $summary = clone $rows->first();
            $summary->shared_available_stock = (int) $rows->filter(fn ($row) => ! $row->expiration_date || $row->expiration_date->gte(today()))->sum('available_stock');
            $summary->recipient_available_stock = $includeRecipientStock ? (int) $recipientBalances->get($key, collect())->sum('quantity') : null;
            $summary->available_stock = $summary->shared_available_stock + ($summary->recipient_available_stock ?? 0);
            $summary->allocated_stock = $rows->sum('allocated_stock');
            $summary->batch_no = null;
            $summary->expiration_date = null;

            return $summary;
        })->filter(fn ($row) => $row->medicine && $row->healthUnit)->values();
    }
}
