<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\StockLog;
use App\Support\HistoricalDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reconciled, currently corrected business-date balances; never invented opening stock. */
class InventoryHistoricalBalanceService
{
    public function summarize(Request $request, HistoricalDateRange $range): array
    {
        $filters = $request->validate([
            'medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'rhu_filter' => ['nullable', 'string', 'regex:/^(all|pho|[0-9]+)$/'],
        ]);
        $asOf = min($range->to ?? today()->toDateString(), today()->toDateString());
        if ($range->from !== null && $range->from > today()->toDateString()) {
            throw ValidationException::withMessages(['date_from' => 'Choose today or an earlier start date for recorded stock balances.']);
        }
        $unitId = session('health_unit_id');
        if ($unitId === null && ! empty($filters['rhu_filter']) && $filters['rhu_filter'] !== 'all') {
            $unitId = $filters['rhu_filter'] === 'pho' ? (HealthUnit::phoId() ?? -1) : (int) $filters['rhu_filter'];
        }
        $medicineId = $filters['medicine_id'] ?? null;
        $batchNumber = ($filters['batch_no'] ?? '') !== '' ? $filters['batch_no'] : null;
        $batches = DB::table('inventory')->when($unitId !== null, fn ($q) => $q->where('health_unit_id', $unitId))
            ->when($medicineId !== null, fn ($q) => $q->where('medicine_id', $medicineId))
            ->when($batchNumber !== null, fn ($q) => $q->where('batch_no', $batchNumber))->get()->keyBy('id');
        $logs = StockLog::activeForReporting()
            ->where(fn ($record) => $record->whereNull('dispensing_record_id')
                ->orWhereHas('dispensingRecord', fn ($dispensing) => $dispensing->qualifying()))
            ->where(fn ($record) => $record->whereNull('allocation_id')
                ->orWhereHas('allocation', fn ($allocation) => $this->qualifyingAllocation($allocation)))
            ->when($unitId !== null, fn ($q) => $q->where('health_unit_id', $unitId))
            ->when($medicineId !== null, fn ($q) => $q->where('medicine_id', $medicineId))
            ->when($batchNumber !== null, fn ($q) => $q->where(fn ($q) => $q
                ->whereIn('inventory_id', $batches->keys())->orWhere('batch_no', $batchNumber)))->toBase()->get();
        $ledger = DB::table('stock_movements')->whereIn('inventory_id', $batches->keys())
            ->whereNull('reverses_id')->whereNull('reversed_at')->get();
        $owners = [];
        foreach (['dispensing_records', 'allocations', 'transfer_requests'] as $table) {
            $ownerIds = $ledger->where('owner_type', $table)->pluck('owner_id');
            $foreignKey = match ($table) { 'dispensing_records' => 'dispensing_record_id', 'allocations' => 'allocation_id', default => 'transfer_id' };
            $ownerIds = $ownerIds->merge($logs->pluck($foreignKey))->filter()->unique();
            $owners[$table] = DB::table($table)->whereIn('id', $ownerIds)->get()->keyBy('id');
        }
        // Historical logs without an inventory ID must identify exactly one batch.
        $batchKeys = $batches->groupBy(fn ($batch) => $this->batchKey($batch));
        $resolvedLogs = [];
        $invalidMedicines = [];
        $invalidFacilities = [];
        foreach ($logs as $log) {
            $batch = $log->inventory_id !== null ? $batches->get($log->inventory_id)
                : (($matches = $batchKeys->get($this->batchKey($log)))?->count() === 1 ? $matches->first() : null);
            if (! $batch || (int) $batch->medicine_id !== (int) $log->medicine_id
                || (int) $batch->health_unit_id !== (int) $log->health_unit_id) {
                $invalidMedicines[(int) $log->medicine_id] = 'Incomplete or ambiguous batch history';
                $invalidFacilities[$log->health_unit_id.':'.$log->medicine_id] = 'Incomplete or ambiguous batch history';
                continue;
            }
            $resolvedLogs[(int) $log->id] = ['log' => $log, 'batch_id' => (int) $batch->id];
        }
        $logsByOwner = [];
        foreach ($resolvedLogs as $id => $entry) {
            $log = $entry['log'];
            $logsByOwner['stock_logs:'.$id.':'.$entry['batch_id']][$id] = $entry;
            foreach (['dispensing_records' => 'dispensing_record_id', 'allocations' => 'allocation_id', 'transfer_requests' => 'transfer_id'] as $table => $key) {
                if ($log->$key !== null) {
                    $logsByOwner[$table.':'.$log->$key.':'.$entry['batch_id']][$id] = $entry;
                }
            }
        }
        $events = [];
        $represented = [];
        $batchErrors = [];
        foreach ($ledger as $movement) {
            $batchId = (int) $movement->inventory_id;
            $batch = $batches->get($batchId);
            $matching = collect($logsByOwner[$movement->owner_type.':'.$movement->owner_id.':'.$batchId] ?? []);
            foreach ($matching as $id => $entry) {
                $represented[$id] = true;
            }
            $date = null;
            if ($movement->owner_type === 'stock_logs') {
                $date = $matching->first()['log']->date_added ?? null;
            } elseif ($movement->owner_type === 'transfer_requests') {
                $owner = $owners['transfer_requests']->get($movement->owner_id);
                if ($owner && (int) $owner->medicine_id === (int) $batch->medicine_id
                    && in_array((int) $batch->health_unit_id, [(int) $owner->from_health_unit_id, (int) $owner->to_health_unit_id], true)) {
                    $date = $movement->created_at;
                }
            } elseif (in_array($movement->owner_type, ['dispensing_records', 'allocations'], true)) {
                $owner = $owners[$movement->owner_type]->get($movement->owner_id);
                $unitMatches = $owner && ((int) $owner->health_unit_id === (int) $batch->health_unit_id
                    || ($movement->owner_type === 'allocations' && (int) $owner->destination_health_unit_id === (int) $batch->health_unit_id));
                if ($owner && $owner->deleted_at === null && $unitMatches && (int) $owner->medicine_id === (int) $batch->medicine_id) {
                    $dates = $matching->pluck('log.date_added')->map(fn ($date) => substr((string) $date, 0, 10))->unique();
                    if ($dates->count() > 1) {
                        $batchErrors[$batchId] = 'Conflicting movement dates';
                    }
                    $date = $dates->first() ?? ($movement->owner_type === 'dispensing_records' ? $owner->date_dispensed : $owner->date_allocated);
                }
            }
            if (! $this->validDate($date)) {
                $batchErrors[$batchId] = 'Incomplete dated movement history';
                continue;
            }
            $events[$batchId][] = [substr($date, 0, 10), (int) $movement->available_delta, (int) $movement->allocated_delta];
        }
        foreach ($resolvedLogs as $id => $entry) {
            if (isset($represented[$id])) {
                continue;
            }
            $log = $entry['log'];
            $batchId = $entry['batch_id'];
            $delta = $this->logDelta($log, $owners);
            if ($delta === null || ! $this->validDate($log->date_added)) {
                $batchErrors[$batchId] = 'Incomplete dated movement history';
                continue;
            }
            $events[$batchId][] = [substr($log->date_added, 0, 10), $delta[0], $delta[1]];
        }
        $dispensing = $this->transactionTotals('dispensing_records', 'date_dispensed', 'quantity_dispensed',
            'dispensing_record_id', $range, $asOf, $unitId, $medicineId, $batchNumber, $batches->keys()->all());
        $allocations = $this->transactionTotals('allocations', 'date_allocated', 'quantity_allocated',
            'allocation_id', $range, $asOf, $unitId, $medicineId, $batchNumber, $batches->keys()->all());
        $medicineIds = $batches->pluck('medicine_id')->merge($logs->pluck('medicine_id'))
            ->merge($dispensing->keys())->merge($allocations->keys())->unique()->values();
        $facilityNames = DB::table('health_units')->pluck('name', 'id');
        $catalog = DB::table('medicines')->whereIn('id', $medicineIds)->get()->keyBy('id');
        $rows = $medicineIds->map(function ($id) use ($batches, $logs, $events, $batchErrors, $invalidMedicines, $invalidFacilities, $facilityNames, $catalog, $dispensing, $allocations, $asOf) {
            $medicine = $catalog->get($id);
            $medicineBatches = $batches->where('medicine_id', $id);
            $complete = $medicineBatches->isNotEmpty() && ! isset($invalidMedicines[$id]);
            $reason = $invalidMedicines[$id] ?? ($medicineBatches->isEmpty() ? 'No recorded inventory history' : null);
            $available = $reserved = 0;
            foreach ($medicineBatches as $batch) {
                $balance = $this->batchBalance($batch, $events[(int) $batch->id] ?? [], $asOf, $batchErrors[(int) $batch->id] ?? null);
                if (! $balance['complete']) {
                    $complete = false;
                    $reason ??= $balance['reason'];
                }
                $available += $balance['available'];
                $reserved += $balance['reserved'];
            }
            $facilityIds = $medicineBatches->pluck('health_unit_id')
                ->merge($logs->where('medicine_id', $id)->pluck('health_unit_id'))
                ->merge($dispensing->get($id)?->facilities->keys() ?? [])
                ->merge($allocations->get($id)?->facilities->keys() ?? [])->unique();
            $facilities = $facilityIds->map(function ($facilityId) use ($id, $medicineBatches, $events, $batchErrors,
                $invalidFacilities, $facilityNames, $dispensing, $allocations, $asOf) {
                $facilityBatches = $medicineBatches->where('health_unit_id', $facilityId);
                $reason = $invalidFacilities[$facilityId.':'.$id] ?? ($facilityBatches->isEmpty() ? 'No recorded inventory history' : null);
                $complete = $reason === null;
                $available = $reserved = 0;
                foreach ($facilityBatches as $batch) {
                    $balance = $this->batchBalance($batch, $events[(int) $batch->id] ?? [], $asOf, $batchErrors[(int) $batch->id] ?? null);
                    $complete = $complete && $balance['complete'];
                    $reason ??= $balance['reason'];
                    $available += $balance['available'];
                    $reserved += $balance['reserved'];
                }
                $dispensed = $dispensing->get($id)?->facilities->get($facilityId);
                $allocated = $allocations->get($id)?->facilities->get($facilityId);

                return [
                    'health_unit_id' => (int) $facilityId, 'facility_name' => $facilityNames->get($facilityId, 'Unknown facility'),
                    'closing_available' => $complete ? $available : null, 'closing_reserved' => $complete ? $reserved : null,
                    'balance_complete' => $complete, 'reason' => $reason,
                    'dispensed_units' => (int) ($dispensed?->quantity ?? 0), 'dispensing_records' => (int) ($dispensed?->records ?? 0),
                    'allocated_units' => (int) ($allocated?->quantity ?? 0), 'allocation_records' => (int) ($allocated?->records ?? 0),
                ];
            })->sortBy('facility_name')->values()->all();

            return (object) [
                'medicine_id' => (int) $id, 'medicine_name' => $medicine->medicine_name ?? 'Medicine #'.$id,
                'facilities' => $facilities,
                'dosage' => $medicine->dosage ?? null, 'unit' => $medicine->unit ?? 'units',
                'closing_available' => $complete ? $available : null, 'closing_reserved' => $complete ? $reserved : null,
                'balance_complete' => $complete, 'reason' => $reason,
                'dispensed_units' => (int) ($dispensing->get($id)?->quantity ?? 0),
                'dispensing_records' => (int) ($dispensing->get($id)?->records ?? 0),
                'allocated_units' => (int) ($allocations->get($id)?->quantity ?? 0),
                'allocation_records' => (int) ($allocations->get($id)?->records ?? 0),
            ];
        })->sortBy(fn ($row) => mb_strtolower($row->medicine_name).' '.$row->dosage)->values();

        return ['as_of' => $asOf, 'period_label' => $range->to !== null && $range->to > $asOf ? ($range->from ?? 'First saved date').' to '.$asOf : ($range->isFiltered() ? $range->label() : 'All saved dates through '.$asOf), 'rows' => $rows];
    }

    private function batchKey(object $row): string
    {
        return $row->health_unit_id.':'.$row->medicine_id.':'.($row->batch_no ?? '');
    }

    private function validDate(?string $date): bool
    {
        return $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $date) === 1 && substr($date, 0, 10) <= today()->toDateString();
    }

    private function logDelta(object $log, array $owners): ?array
    {
        $quantity = (int) $log->quantity_added;
        if ($quantity <= 0) {
            return null;
        }
        if (in_array($log->movement_type, ['addition', 'receipt', 'transfer_in', 'adjustment_in'], true)) {
            return [$quantity, 0];
        }
        if (in_array($log->movement_type, ['transfer_out', 'adjustment_out'], true)) {
            return [-$quantity, 0];
        }
        if ($log->movement_type === 'dispensing') {
            $record = $log->dispensing_record_id !== null ? $owners['dispensing_records']->get($log->dispensing_record_id) : null;
            if ($record && $record->stock_source === 'allocation') {
                return [0, -$quantity];
            }
            return [-$quantity, 0];
        }
        // Old allocations can move both available and reserved stock; their exact ledger is required.
        return null;
    }

    private function batchBalance(object $batch, array $events, string $asOf, ?string $error): array
    {
        $result = ['complete' => false, 'reason' => $error ?? 'Incomplete stock history', 'available' => 0, 'reserved' => 0];
        if ($error !== null || $events === []) {
            return $result;
        }
        $daily = [];
        foreach ($events as [$date, $available, $reserved]) {
            $daily[$date] ??= [0, 0];
            $daily[$date][0] += $available;
            $daily[$date][1] += $reserved;
        }
        ksort($daily);
        if ($asOf < array_key_first($daily)) {
            $result['reason'] = 'Before recorded stock history';
            return $result;
        }
        $runningAvailable = $runningReserved = $closingAvailable = $closingReserved = 0;
        foreach ($daily as $date => [$available, $reserved]) {
            $runningAvailable += $available;
            $runningReserved += $reserved;
            if ($runningAvailable < 0 || $runningReserved < 0) {
                return $result;
            }
            if ($date <= $asOf) {
                $closingAvailable = $runningAvailable;
                $closingReserved = $runningReserved;
            }
        }
        // Soft deletion retains stale row quantities while its removal is separately logged.
        $expectedAvailable = $batch->deleted_at === null ? (int) $batch->available_stock : 0;
        $expectedReserved = $batch->deleted_at === null ? (int) $batch->allocated_stock : 0;
        if ($runningAvailable !== $expectedAvailable || $runningReserved !== $expectedReserved) {
            return $result;
        }

        return ['complete' => true, 'reason' => null,
            'available' => $batch->expiration_date !== null && substr($batch->expiration_date, 0, 10) < $asOf ? 0 : $closingAvailable,
            'reserved' => $closingReserved];
    }

    private function transactionTotals(string $table, string $date, string $quantity, string $foreignKey,
        HistoricalDateRange $range, string $asOf, ?int $unitId, ?int $medicineId, ?string $batchNumber, array $batchIds): Collection
    {
        $query = DB::table($table.' as records')->whereNull('records.deleted_at')->where('records.'.$quantity, '>', 0)
            ->whereDate('records.'.$date, '<=', $asOf)
            ->when($range->from !== null, fn ($q) => $q->whereDate('records.'.$date, '>=', $range->from))
            ->when($unitId !== null, fn ($q) => $q->where('records.health_unit_id', $unitId))
            ->when($medicineId !== null, fn ($q) => $q->where('records.medicine_id', $medicineId))
            ->when(session('role') === 'midwife', fn ($q) => $q->where('records.'.($table === 'allocations' ? 'allocated_by' : 'dispensed_by'), session('user_id')));
        if ($table === 'allocations') {
            $query->whereIn('records.status', ['awaiting_receipt', 'completed', 'approved', 'received']);
            $qualifying = $this->qualifyingAllocation(Allocation::query());
        } else {
            $qualifying = DispensingRecord::qualifying();
        }
        $query->whereIn('records.id', $qualifying->select('id'));
        if ($batchNumber === null) {
            return $this->medicineTotals($query->selectRaw('records.medicine_id, records.health_unit_id, SUM(records.'.$quantity.') as quantity, COUNT(*) as records')
                ->groupBy('records.medicine_id', 'records.health_unit_id')->get());
        }
        $withoutLedger = (clone $query)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_movements as existing_movement')
            ->where('existing_movement.owner_type', $table)->whereColumn('existing_movement.owner_id', 'records.id')
            ->whereNull('existing_movement.reversed_at')->whereNull('existing_movement.reverses_id'));
        $ledgerQuantity = $table === 'dispensing_records' ? '-movement.available_delta - movement.allocated_delta' : '-movement.available_delta';
        $ledgerTotals = (clone $query)->join('stock_movements as movement', fn ($join) => $join
            ->on('movement.owner_id', '=', 'records.id')->where('movement.owner_type', $table))
            ->join('inventory as movement_batch', 'movement_batch.id', '=', 'movement.inventory_id')
            ->whereColumn('movement_batch.health_unit_id', 'records.health_unit_id')
            ->whereColumn('movement_batch.medicine_id', 'records.medicine_id')
            ->whereNull('movement.reverses_id')->whereNull('movement.reversed_at')->whereIn('movement.inventory_id', $batchIds)
            ->whereRaw('('.$ledgerQuantity.') > 0')
            ->selectRaw('records.medicine_id, records.health_unit_id, SUM('.$ledgerQuantity.') as quantity, COUNT(DISTINCT records.id) as records')
            ->groupBy('records.medicine_id', 'records.health_unit_id')->get()->keyBy(fn ($row) => $row->health_unit_id.':'.$row->medicine_id);
        $logged = (clone $withoutLedger)->join('stock_logs as logs', 'logs.'.$foreignKey, '=', 'records.id')
            ->whereNull('logs.voided_at')->where('logs.quantity_added', '>', 0)
            ->whereColumn('logs.health_unit_id', 'records.health_unit_id')->whereColumn('logs.medicine_id', 'records.medicine_id')
            ->where(fn ($q) => $q->whereIn('logs.inventory_id', $batchIds)->orWhere('logs.batch_no', $batchNumber))
            ->selectRaw('records.medicine_id, records.health_unit_id, SUM(logs.quantity_added) as quantity, COUNT(DISTINCT records.id) as records')
            ->groupBy('records.medicine_id', 'records.health_unit_id')->get()->keyBy(fn ($row) => $row->health_unit_id.':'.$row->medicine_id);
        $unlogged = (clone $withoutLedger)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_logs as existing')
            ->whereColumn('existing.'.$foreignKey, 'records.id')->whereNull('existing.voided_at'));
        if ($table === 'dispensing_records') {
            $unlogged->where('records.batch_no', $batchNumber)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('inventory as declared_batch')
                    ->whereColumn('declared_batch.health_unit_id', 'records.health_unit_id')
                    ->whereColumn('declared_batch.medicine_id', 'records.medicine_id')
                    ->where('declared_batch.batch_no', $batchNumber)->groupBy('declared_batch.health_unit_id', 'declared_batch.medicine_id')
                    ->havingRaw('COUNT(*) = 1'));
        } else {
            $unlogged->whereIn('records.inventory_id', $batchIds);
        }
        foreach ($unlogged->selectRaw('records.medicine_id, records.health_unit_id, SUM(records.'.$quantity.') as quantity, COUNT(*) as records')
            ->groupBy('records.medicine_id', 'records.health_unit_id')->get() as $row) {
            if ($existing = $logged->get($row->health_unit_id.':'.$row->medicine_id)) {
                $existing->quantity += $row->quantity;
                $existing->records += $row->records;
            } else {
                $logged->put($row->health_unit_id.':'.$row->medicine_id, $row);
            }
        }

        foreach ($ledgerTotals as $row) {
            if ($existing = $logged->get($row->health_unit_id.':'.$row->medicine_id)) {
                $existing->quantity += $row->quantity;
                $existing->records += $row->records;
            } else {
                $logged->put($row->health_unit_id.':'.$row->medicine_id, $row);
            }
        }

        return $this->medicineTotals($logged);
    }

    private function qualifyingAllocation($query)
    {
        return $query->where(fn ($rows) => $rows->whereDoesntHave('stockLogs')
            ->orWhereHas('stockLogs', fn ($log) => $log->activeForReporting()))
            ->where(function ($rows) {
                $original = fn ($movement) => $movement->selectRaw('1')->from('stock_movements')
                    ->where('owner_type', 'allocations')->whereColumn('owner_id', 'allocations.id')->whereNull('reverses_id');
                $rows->whereNotExists($original)->orWhereExists(fn ($movement) => $original($movement)->whereNull('reversed_at'));
            });
    }

    private function medicineTotals(Collection $rows): Collection
    {
        return $rows->groupBy('medicine_id')->map(fn ($facilities) => (object) [
            'quantity' => $facilities->sum('quantity'), 'records' => $facilities->sum('records'),
            'facilities' => $facilities->keyBy('health_unit_id'),
        ]);
    }
}
