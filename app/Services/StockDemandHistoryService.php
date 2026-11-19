<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\StockLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Recorded terminal releases, supplemented by saved transactions with missing logs. */
class StockDemandHistoryService
{
    public function events(?array $unitIds, string $from, string $through): Collection
    {
        if ($unitIds === []) {
            return collect();
        }
        $logs = $this->demandLogs()
            ->when($unitIds !== null, fn ($q) => $q->whereIn('health_unit_id', $unitIds))
            ->whereDate('date_added', '>=', $from)->whereDate('date_added', '<=', $through)
            ->get(['id','health_unit_id','medicine_id','date_added','quantity_added','added_by','dispensing_record_id','allocation_id','transfer_id','movement_type','remarks']);
        $events = $logs->map(fn ($log) => $this->event($log->health_unit_id, $log->medicine_id,
            $log->date_added, $log->quantity_added, 'stock_log', $log->id));
        $legacy = $logs->whereNull('dispensing_record_id')->whereNull('allocation_id')->whereNull('transfer_id');

        $dispensing = DispensingRecord::qualifying()
            ->when($unitIds !== null, fn ($q) => $q->whereIn('health_unit_id', $unitIds))
            ->whereDate('date_dispensed', '>=', $from)->whereDate('date_dispensed', '<=', $through)
            ->get(['id','health_unit_id','medicine_id','date_dispensed','quantity_dispensed','dispensed_by']);
        $allocations = $this->qualifyingAllocations(Allocation::query())
            ->when($unitIds !== null, fn ($q) => $q->whereIn('health_unit_id', $unitIds))
            ->whereDate('date_allocated', '>=', $from)->whereDate('date_allocated', '<=', $through)
            ->get(['id','health_unit_id','medicine_id','date_allocated','quantity_allocated','allocated_by']);
        foreach ([[$dispensing,'dispensing_record_id','date_dispensed','quantity_dispensed','dispensed_by','dispensing'],
            [$allocations,'allocation_id','date_allocated','quantity_allocated','allocated_by','allocation']] as [$records,$key,$date,$quantity,$actor,$source]) {
            $linked = $this->demandLogs()->whereIn($key, $records->pluck('id'))
                ->get(['id',$key,'health_unit_id','medicine_id','quantity_added']);
            foreach ($records as $record) {
                $covered = (int) $linked->where($key, $record->id)->where('health_unit_id', $record->health_unit_id)
                    ->where('medicine_id', $record->medicine_id)->sum('quantity_added');
                $missing = max(0, (int) $record->$quantity - $covered);
                if ($missing === 0) {
                    continue;
                }
                // An unlinked legacy row with matching identity may already represent this issue.
                // Preserve that evidence rather than inventing another release for ambiguous matches.
                $legacyMatch = $legacy->contains(fn ($log) => ($log->movement_type === $source
                        || ($log->movement_type === null && str_contains(strtolower($log->remarks ?? ''), $source === 'dispensing' ? 'dispens' : 'allocat')))
                    && (int) $log->health_unit_id === (int) $record->health_unit_id
                    && (int) $log->medicine_id === (int) $record->medicine_id
                    && $log->date_added->toDateString() === $record->$date->toDateString()
                    && ($log->added_by === null || (int) $log->added_by === (int) $record->$actor));
                if (!$legacyMatch) {
                    $events->push($this->event($record->health_unit_id, $record->medicine_id,
                        $record->$date, $missing, $source, $record->id));
                }
            }
        }

        return $events->values();
    }

    /** Apply the same owner validity to events and linked quantities already covered by logs. */
    private function demandLogs(): Builder
    {
        return StockLog::demandReleases()
            ->where(fn ($query) => $query->whereNull('dispensing_record_id')
                ->orWhereHas('dispensingRecord', fn ($record) => $record->qualifying()
                    ->whereColumn('dispensing_records.health_unit_id', 'stock_logs.health_unit_id')
                    ->whereColumn('dispensing_records.medicine_id', 'stock_logs.medicine_id')))
            ->where(fn ($query) => $query->whereNull('allocation_id')
                ->orWhereHas('allocation', fn ($allocation) => $this->qualifyingAllocations($allocation)
                    ->whereColumn('allocations.health_unit_id', 'stock_logs.health_unit_id')
                    ->whereColumn('allocations.medicine_id', 'stock_logs.medicine_id')));
    }

    private function qualifyingAllocations(Builder $query): Builder
    {
        return $query->where('quantity_allocated', '>', 0)
            ->whereNull('destination_health_unit_id')->whereNull('recipient_user_id')
            ->whereIn('status', ['completed', 'approved', 'received'])
            ->where(fn ($state) => $state->whereDoesntHave('stockLogs')
                ->orWhereHas('stockLogs', fn ($log) => $log->activeForReporting()))
            ->where(function ($state) {
                $originalMovements = fn ($movement) => $movement->selectRaw('1')->from('stock_movements')
                    ->where('owner_type', 'allocations')->whereColumn('owner_id', 'allocations.id')->whereNull('reverses_id');
                $state->whereNotExists($originalMovements)->orWhereExists(function ($movement) use ($originalMovements) {
                    $originalMovements($movement)->whereNull('reversed_at');
                });
            })
            ->whereDoesntHave('allocatedBy', fn ($user) => $user->where('role', 'midwife')
                ->whereColumn('users.health_unit_id', 'allocations.health_unit_id'));
    }

    private function event(int $unitId, int $medicineId, $date, int $quantity, string $source, int $id): object
    {
        return (object) ['health_unit_id'=>$unitId,'medicine_id'=>$medicineId,
            'date_added'=>CarbonImmutable::parse($date)->startOfDay(),'quantity_added'=>$quantity,
            'event_source'=>$source,'event_id'=>$id];
    }
}
