<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\StockLog;
use App\Support\HistoricalDateRange;
use App\Support\StockLogBatchFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryHistoryService
{
    public function records(Request $request, HistoricalDateRange $range, bool $paginate = true, bool $stockLogs = false)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'medicine_id' => ['nullable', 'integer', 'min:1', 'exists:medicines,id'],
            'type' => ['nullable', 'string', 'in:addition,release,reversal,reservation'],
            'action' => ['nullable', 'string', 'in:addition,dispensing,allocation,transfer,reservation,reversal,adjustment'],
            'rhu_filter' => ['nullable', 'string', 'regex:/^(all|pho|[0-9]+)$/'],
            'status' => ['nullable', 'in:active,cancelled,reversed,reversal,all'],
            'source' => ['nullable', 'in:stock_logs'],
            'batch_input' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
            'movement_type' => ['nullable', 'in:addition,receipt,transfer_in,adjustment_in,dispensing,allocation,transfer_out,adjustment_out'],
            'record_kind' => ['nullable', 'in:allocation,dispensing'],
        ]);
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? ($type === 'reversal' ? 'reversal' : ($stockLogs ? 'active' : 'all'));
        $recordKind = $filters['record_kind'] ?? null;
        $unitId = session('health_unit_id');
        if ($unitId === null && ! empty($filters['rhu_filter']) && $filters['rhu_filter'] !== 'all') {
            $unitId = $filters['rhu_filter'] === 'pho' ? (HealthUnit::phoId() ?? -1) : (int) $filters['rhu_filter'];
        }
        $excludePho = $stockLogs && $unitId === null && empty($filters['rhu_filter']) ? HealthUnit::phoId() : null;

        $logs = StockLog::query()->select([
            DB::raw("'log' as event_source"), 'stock_logs.id as event_id', 'medicine_id', 'health_unit_id',
            'inventory_id', 'quantity_added', 'batch_no', 'storage_location', 'date_added', 'added_by',
            'remarks', 'movement_type', 'voided_at', 'dispensing_record_id', 'allocation_id', 'transfer_id',
            DB::raw('NULL as available_delta'), DB::raw('NULL as allocated_delta'), DB::raw('NULL as method'),
            DB::raw('NULL as recorded_at'),
        ])->when($unitId !== null, fn ($query) => $query->where('stock_logs.health_unit_id', $unitId))
            ->when($excludePho !== null, fn ($query) => $query->where('stock_logs.health_unit_id', '!=', $excludePho))
            ->when(! empty($filters['medicine_id']), fn ($query) => $query->where('stock_logs.medicine_id', $filters['medicine_id']))
            ->when(! empty($filters['batch_no']), fn ($query) => $query->where('stock_logs.batch_no', $filters['batch_no']));
        if ($recordKind === null) {
            $range->apply($logs, 'stock_logs.date_added');
        }
        if ($status === 'active') {
            $logs->activeForReporting();
        }
        if ($type === 'addition') {
            $logs->additions();
        } elseif ($type === 'release') {
            $logs->releases();
        } elseif ($type !== null) {
            $logs->whereRaw('1 = 0');
        }

        // Keep distinct reservations and reversals alongside logged business movements.
        $reservation = "movement.owner_type = 'transfer_requests' AND movement.available_delta < 0 AND movement.allocated_delta > 0";
        $ledgerDate = "CASE WHEN movement.reverses_id IS NOT NULL OR movement.reversed_at IS NOT NULL
            OR ({$reservation})
            THEN movement.created_at
            ELSE COALESCE(ledger_dispensing.date_dispensed, ledger_allocation.date_allocated, movement.created_at) END";
        $ledger = DB::table('stock_movements as movement')->join('inventory as batch', 'batch.id', '=', 'movement.inventory_id')
            ->leftJoin('dispensing_records as ledger_dispensing', fn ($join) => $join
                ->on('ledger_dispensing.id', '=', 'movement.owner_id')->where('movement.owner_type', 'dispensing_records'))
            ->leftJoin('allocations as ledger_allocation', fn ($join) => $join
                ->on('ledger_allocation.id', '=', 'movement.owner_id')->where('movement.owner_type', 'allocations'))
            ->selectRaw("'ledger' as event_source, movement.id as event_id, batch.medicine_id, batch.health_unit_id,
                movement.inventory_id, CASE WHEN movement.available_delta <> 0 THEN ABS(movement.available_delta)
                    ELSE ABS(movement.allocated_delta) END as quantity_added,
                batch.batch_no, batch.storage_location, {$ledgerDate} as date_added, movement.performed_by as added_by,
                movement.reason as remarks,
                CASE WHEN movement.reverses_id IS NOT NULL THEN 'reversal'
                    WHEN {$reservation} THEN 'reservation'
                    WHEN movement.available_delta > 0 THEN 'addition' ELSE 'release' END as movement_type,
                movement.reversed_at as voided_at,
                CASE WHEN movement.owner_type = 'dispensing_records' THEN movement.owner_id END as dispensing_record_id,
                CASE WHEN movement.owner_type = 'allocations' THEN movement.owner_id END as allocation_id,
                CASE WHEN movement.owner_type = 'transfer_requests' THEN movement.owner_id END as transfer_id,
                movement.available_delta, movement.allocated_delta, movement.method, movement.created_at as recorded_at")
            ->where(function ($unlogged) use ($reservation) {
                $unlogged->whereNotNull('movement.reverses_id')
                    ->orWhereRaw('('.$reservation.')')
                    ->orWhereNotExists(function ($existing) {
                        $existing->selectRaw('1')->from('stock_logs as existing')
                            ->where(fn ($owner) => $this->matchingOwner($owner, 'movement', 'existing'))
                            ->where(fn ($state) => $state->where(fn ($active) => $active->whereNull('existing.voided_at')->whereNull('movement.reversed_at'))
                                ->orWhere(fn ($voided) => $voided->whereNotNull('existing.voided_at')->whereNotNull('movement.reversed_at')))
                            ->where(function ($matchingBatch) {
                                $matchingBatch->whereColumn('existing.inventory_id', 'movement.inventory_id')
                                    ->orWhere(fn ($legacy) => $legacy->whereNull('existing.inventory_id')
                                        ->whereColumn('existing.health_unit_id', 'batch.health_unit_id')
                                        ->whereColumn('existing.medicine_id', 'batch.medicine_id')
                                        ->where(fn ($number) => $number->whereColumn('existing.batch_no', 'batch.batch_no')
                                            ->orWhere(fn ($blank) => $blank->whereNull('existing.batch_no')->whereNull('batch.batch_no'))));
                            });
                    });
            })
            ->when($unitId !== null, fn ($query) => $query->where('batch.health_unit_id', $unitId))
            ->when($excludePho !== null, fn ($query) => $query->where('batch.health_unit_id', '!=', $excludePho))
            ->when(! empty($filters['medicine_id']), fn ($query) => $query->where('batch.medicine_id', $filters['medicine_id']))
            ->when(! empty($filters['batch_no']), fn ($query) => $query->where('batch.batch_no', $filters['batch_no']));
        if ($recordKind === null && $range->from !== null) {
            $ledger->whereRaw('DATE('.$ledgerDate.') >= ?', [$range->from]);
        }
        if ($recordKind === null && $range->to !== null) {
            $ledger->whereRaw('DATE('.$ledgerDate.') <= ?', [$range->to]);
        }
        if ($type !== null) {
            $ledger->whereRaw(match ($type) {
                'reversal' => 'movement.reverses_id IS NOT NULL',
                'reservation' => 'movement.reverses_id IS NULL AND ('.$reservation.')',
                'addition' => 'movement.reverses_id IS NULL AND movement.available_delta > 0',
                'release' => 'movement.reverses_id IS NULL AND movement.available_delta <= 0 AND NOT ('.$reservation.')',
            });
        }

        $logEvents = $logs->toBase();
        if (DB::connection()->getDriverName() === 'mysql') {
            // Older installations can mix utf8mb4 collations across these tables.
            $logEvents = $this->normalizeText($logEvents);
            $ledger = $this->normalizeText($ledger);
        }
        $events = DB::query()->fromSub($logEvents->unionAll($ledger), 'events');
        if ($stockLogs && ($filters['source'] ?? null) === 'stock_logs') {
            $events->where('events.event_source', 'log');
        }
        $action = "CASE WHEN events.movement_type = 'reversal' THEN 'reversal'
            WHEN events.movement_type = 'reservation' THEN 'reservation'
            WHEN events.dispensing_record_id IS NOT NULL OR events.movement_type = 'dispensing'
                OR (events.movement_type IS NULL AND (LOWER(events.remarks) LIKE 'dispensed%'
                    OR LOWER(events.remarks) LIKE 'released%dispensed%')) THEN 'dispensing'
            WHEN events.allocation_id IS NOT NULL OR events.movement_type = 'allocation'
                OR (events.movement_type IS NULL AND (LOWER(events.remarks) LIKE 'allocated%'
                    OR LOWER(events.remarks) LIKE 'released%allocated%')) THEN 'allocation'
            WHEN events.transfer_id IS NOT NULL OR events.movement_type IN ('transfer_in', 'transfer_out') THEN 'transfer'
            WHEN events.movement_type IN ('adjustment_in', 'adjustment_out') THEN 'adjustment'
            WHEN events.movement_type IN ('addition', 'receipt')
                OR (events.movement_type IS NULL AND (events.remarks IS NULL OR LOWER(events.remarks) LIKE 'added%'
                    OR LOWER(events.remarks) LIKE 'received%' OR LOWER(events.remarks) LIKE 'stock%')) THEN 'addition'
            ELSE 'adjustment' END";
        $query = $events->leftJoin('medicines', 'medicines.id', '=', 'events.medicine_id')
            ->leftJoin('health_units as facility', 'facility.id', '=', 'events.health_unit_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'events.added_by')
            ->leftJoin('transfer_requests as transfer', 'transfer.id', '=', 'events.transfer_id')
            ->leftJoin('allocations as allocation', 'allocation.id', '=', 'events.allocation_id')
            ->leftJoin('dispensing_records as dispensing', 'dispensing.id', '=', 'events.dispensing_record_id')
            ->leftJoin('health_units as transfer_from', 'transfer_from.id', '=', 'transfer.from_health_unit_id')
            ->leftJoin('health_units as transfer_to', 'transfer_to.id', '=', 'transfer.to_health_unit_id')
            ->leftJoin('health_units as allocation_from', 'allocation_from.id', '=', 'allocation.health_unit_id')
            ->leftJoin('health_units as allocation_to', 'allocation_to.id', '=', 'allocation.destination_health_unit_id')
            ->select('events.*', 'medicines.medicine_name', 'medicines.dosage', 'medicines.dosage_form', 'medicines.unit', 'facility.name as facility_name', 'actor.name as actor_name', 'transfer.status as transfer_status',
                'allocation.date_allocated as allocation_date', 'dispensing.date_dispensed as dispensing_date')
            ->selectRaw('events.date_added as stock_effective_date, '.$action.' as event_type')
            ->selectRaw('COALESCE(transfer_from.name, allocation_from.name) as source_name,
                COALESCE(transfer_to.name, allocation_to.name, allocation.destination) as destination_name');

        match ($status) {
            'active' => $query->whereNull('events.voided_at')->where(fn ($movement) => $movement
                ->whereNull('events.movement_type')->orWhere('events.movement_type', '!=', 'reversal'))
                ->where(fn ($transfer) => $transfer->whereNull('transfer.status')->orWhere('transfer.status', '!=', 'cancelled')),
            'cancelled' => $query->where('transfer.status', 'cancelled'),
            'reversed' => $query->whereNotNull('events.voided_at'),
            'reversal' => $query->where('events.movement_type', 'reversal'),
            default => null,
        };
        if ($recordKind !== null) {
            $owner = $recordKind === 'allocation' ? 'allocation' : 'dispensing';
            $quantity = $recordKind === 'allocation' ? 'quantity_allocated' : 'quantity_dispensed';
            $date = $recordKind === 'allocation' ? 'date_allocated' : 'date_dispensed';
            $query->whereColumn('events.health_unit_id', $owner.'.health_unit_id')
                ->whereColumn('events.medicine_id', $owner.'.medicine_id')
                ->whereNull($owner.'.deleted_at')->where($owner.'.'.$quantity, '>', 0)->where('events.quantity_added', '>', 0);
            $qualifying = $recordKind === 'dispensing' ? DispensingRecord::qualifying()
                : Allocation::where(fn ($rows) => $rows->whereDoesntHave('stockLogs')
                    ->orWhereHas('stockLogs', fn ($log) => $log->activeForReporting()))
                    ->where(function ($rows) {
                        $original = fn ($movement) => $movement->selectRaw('1')->from('stock_movements')
                            ->where('owner_type', 'allocations')->whereColumn('owner_id', 'allocations.id')->whereNull('reverses_id');
                        $rows->whereNotExists($original)->orWhereExists(fn ($movement) => $original($movement)->whereNull('reversed_at'));
                    });
            $query->whereIn($owner.'.id', $qualifying->select('id'));
            $range->apply($query, $owner.'.'.$date);
            if ($recordKind === 'allocation') {
                $query->whereIn('allocation.status', ['awaiting_receipt', 'completed', 'approved', 'received'])
                    ->where(fn ($row) => $row->where('events.available_delta', '<', 0)
                        ->orWhere(fn ($log) => $log->where('events.event_source', 'log')
                            ->where(fn ($type) => $type->whereNull('events.movement_type')->orWhere('events.movement_type', 'allocation'))));
            }
        }
        if (! empty($filters['category'])) {
            $query->where('medicines.category', $filters['category']);
        }
        if (! empty($filters['movement_type'])) {
            $query->where('events.movement_type', $filters['movement_type']);
        }

        if (! empty($filters['action'])) {
            if ($filters['action'] === 'reservation') {
                $query->where('events.movement_type', 'reservation');
            } else {
                $query->whereRaw('('.$action.') = ?', [$filters['action']]);
            }
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($search) use ($term, $filters) {
                $search->where('medicines.medicine_name', 'like', $term)->orWhere('medicines.dosage', 'like', $term)
                    ->orWhere('actor.name', 'like', $term)->orWhere('actor.username', 'like', $term)->orWhere('events.remarks', 'like', $term)
                    ->orWhere('events.batch_no', 'like', $term)->orWhere('facility.name', 'like', $term)
                    ->orWhere('transfer_from.name', 'like', $term)->orWhere('transfer_to.name', 'like', $term)
                    ->orWhere('allocation_from.name', 'like', $term)->orWhere('allocation_to.name', 'like', $term)
                    ->orWhere('allocation.destination', 'like', $term);
                if (preg_match('/\A(allocation|dispensing|transfer)\s*#?\s*([0-9]+)\z/iD', trim($filters['search']), $reference)) {
                    $column = match (strtolower($reference[1])) {
                        'allocation' => 'allocation_id', 'dispensing' => 'dispensing_record_id', 'transfer' => 'transfer_id',
                    };
                    $search->orWhere('events.'.$column, (int) $reference[2]);
                }
            });
        }
        StockLogBatchFilter::apply($query, 'events.batch_no', $filters['batch_input'] ?? null);
        $query->orderByDesc('events.date_added')->orderByDesc('events.event_id')->orderBy('events.event_source');
        $records = $paginate ? $query->paginate(20)->withQueryString() : $query->get();
        $rows = $paginate ? $records->getCollection() : $records;
        $methods = $this->pageMethods($rows);

        $rows->transform(function ($row) use ($methods) {
                if ($row->event_source === 'log') {
                    $movement = $methods->get($this->movementKey($row));
                    $row->method = $movement?->method;
                    $row->recorded_at = $movement?->created_at;
                }
                $row->event_label = match ($row->event_type) {
                    'addition' => 'Stock received', 'dispensing' => 'Dispensing', 'allocation' => 'Allocation',
                    'transfer' => 'Transfer', 'reservation' => 'Reservation', 'reversal' => 'Reversal',
                    default => 'Stock adjustment',
                };
                $log = new StockLog((array) $row);
                $row->direction = $row->event_source === 'log' ? $log->movement_direction
                    : (in_array($row->movement_type, ['addition', 'release'], true) ? $row->movement_type : null);
                $row->mechanism = match (true) {
                    $row->movement_type === 'reversal' => 'Stock reversal',
                    $row->movement_type === 'reservation' => 'Stock reservation',
                    $row->transfer_id !== null => 'Transfer #'.$row->transfer_id,
                    $row->allocation_id !== null => 'Allocation #'.$row->allocation_id,
                    $row->dispensing_record_id !== null => 'Dispensing #'.$row->dispensing_record_id,
                    default => 'Stock entry or inventory adjustment',
                };

                $row->status_label = match (true) {
                    $row->transfer_status === 'cancelled' && $row->movement_type === 'reversal' => 'Cancellation reversal',
                    $row->transfer_status === 'cancelled' => 'Cancelled',
                    $row->movement_type === 'reversal' => 'Reversal',
                    $row->voided_at !== null => 'Reversed or corrected',
                    default => 'Active',
                };

                return $row;
            });

        return $records;
    }

    private function pageMethods(Collection $rows): Collection
    {
        $logs = $rows->where('event_source', 'log')->whereNotNull('inventory_id');
        if ($logs->isEmpty()) {
            return collect();
        }
        $movements = DB::table('stock_movements')->whereNull('reverses_id')
            ->whereIn('inventory_id', $logs->pluck('inventory_id')->unique())
            ->where(function ($owner) use ($logs) {
                $owner->where(fn ($allocation) => $allocation->where('owner_type', 'allocations')->whereIn('owner_id', $logs->pluck('allocation_id')->filter()))
                    ->orWhere(fn ($dispensing) => $dispensing->where('owner_type', 'dispensing_records')->whereIn('owner_id', $logs->pluck('dispensing_record_id')->filter()))
                    ->orWhere(fn ($transfer) => $transfer->where('owner_type', 'transfer_requests')->whereIn('owner_id', $logs->pluck('transfer_id')->filter()))
                    ->orWhere(fn ($entry) => $entry->where('owner_type', 'stock_logs')->whereIn('owner_id', $logs->pluck('event_id')));
            })->orderByDesc('id')->get();

        return $movements->unique(fn ($movement) => $this->movementKey($movement))
            ->keyBy(fn ($movement) => $this->movementKey($movement));
    }

    private function movementKey(object $row): string
    {
        $ownerType = $row->owner_type ?? match (true) {
            $row->allocation_id !== null => 'allocations',
            $row->dispensing_record_id !== null => 'dispensing_records',
            $row->transfer_id !== null => 'transfer_requests',
            default => ($row->event_source ?? '') === 'log' ? 'stock_logs' : '',
        };
        $ownerId = $row->owner_id ?? ($row->allocation_id ?? $row->dispensing_record_id ?? $row->transfer_id
            ?? ($ownerType === 'stock_logs' ? $row->event_id : null));

        return $ownerType.':'.$ownerId.':'.$row->inventory_id.':'.($row->reversed_at ?? $row->voided_at ?? '');
    }

    private function matchingOwner(Builder $query, string $movement, string $log): void
    {
        $query->where(fn ($owner) => $owner->where($movement.'.owner_type', 'allocations')->whereColumn($movement.'.owner_id', $log.'.allocation_id'))
            ->orWhere(fn ($owner) => $owner->where($movement.'.owner_type', 'dispensing_records')->whereColumn($movement.'.owner_id', $log.'.dispensing_record_id'))
            ->orWhere(fn ($owner) => $owner->where($movement.'.owner_type', 'transfer_requests')->whereColumn($movement.'.owner_id', $log.'.transfer_id'))
            ->orWhere(fn ($owner) => $owner->where($movement.'.owner_type', 'stock_logs')->whereColumn($movement.'.owner_id', $log.'.id'));
    }

    private function normalizeText(Builder $query): Builder
    {
        $columns = ['event_source', 'event_id', 'medicine_id', 'health_unit_id', 'inventory_id',
            'quantity_added', 'batch_no', 'storage_location', 'date_added', 'added_by', 'remarks',
            'movement_type', 'voided_at', 'dispensing_record_id', 'allocation_id', 'transfer_id',
            'available_delta', 'allocated_delta', 'method', 'recorded_at'];
        $textColumns = ['event_source', 'batch_no', 'storage_location', 'remarks', 'movement_type', 'method'];

        return DB::query()->fromSub($query, 'normalized_events')->select(array_map(
            fn ($column) => in_array($column, $textColumns, true)
                ? DB::raw('CONVERT(normalized_events.'.$column.' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS '.$column)
                : 'normalized_events.'.$column,
            $columns,
        ));
    }
}
