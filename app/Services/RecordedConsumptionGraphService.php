<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Graphs saved medicine use without treating unassociated medicines as disease evidence. */
class RecordedConsumptionGraphService
{
    public function build(int $unitId, ?string $barangay, ?int $actorId, array $comparisonAnalysis,
        ?string $fromDate = null, ?string $toDate = null, ?int $diseaseId = null): array
    {
        $type = $comparisonAnalysis['period_type'];
        $query = app(DiseaseConsumptionTrendService::class)->recordsQuery($unitId, $barangay, $actorId);
        if ($diseaseId !== null) {
            $query->whereExists(fn ($mapping) => $mapping->selectRaw('1')->from('disease_medicine_map')
                ->join('diseases', 'diseases.id', '=', 'disease_medicine_map.disease_id')
                ->whereColumn('disease_medicine_map.medicine_id', 'dispensing_records.medicine_id')
                ->where('diseases.is_active', true)->where('diseases.id', $diseaseId));
        }
        $saved = (clone $query)->selectRaw('MIN(date_dispensed) AS first_date, MAX(date_dispensed) AS last_date')->first();
        $explicit = $fromDate !== null || $toDate !== null;
        $fallback = ! $explicit && $saved->first_date !== null
            && ! collect($comparisonAnalysis['medicine_signals'])->contains(fn ($medicine) => $medicine['total_quantity'] > 0);
        $historical = $explicit || $fallback;
        if ($historical) {
            $through = CarbonImmutable::parse($toDate ?? ($fallback ? $saved->last_date : CarbonImmutable::today()->toDateString()))
                ->min(CarbonImmutable::today())->toDateString();
            $from = $fromDate ?? $saved->first_date;
            if ($fallback && $from !== null) {
                $from = max($from, CarbonImmutable::parse($through)->subYearsWithOverflow(10)->toDateString());
            }
            if ($from !== null && CarbonImmutable::parse($through)->greaterThan(CarbonImmutable::parse($from)->addYears(10))) {
                throw ValidationException::withMessages(['date_from' => 'Choose ten years or less for historical consumption analysis.']);
            }
            $window = ['from' => $from, 'to' => $through];
        } else {
            $window = $comparisonAnalysis['window'];
        }
        $windowQuery = (clone $query)->whereDate('date_dispensed', '<=', $window['to']);
        if ($window['from'] !== null) {
            $windowQuery->whereDate('date_dispensed', '>=', $window['from']);
        }
        $rows = $window['from'] === null ? collect() : $windowQuery
            ->with(['medicine' => fn ($medicine) => $medicine->withTrashed()->select('id', 'medicine_name', 'dosage', 'dosage_form', 'unit')])
            ->orderBy('date_dispensed')->orderBy('id')->get(['id', 'medicine_id', 'date_dispensed', 'quantity_dispensed']);
        $firstDates = (clone $query)->whereIn('medicine_id', $rows->pluck('medicine_id')->unique())
            ->select('medicine_id')->selectRaw('MIN(date_dispensed) AS first_date')->groupBy('medicine_id')->pluck('first_date', 'medicine_id');
        $mappings = DB::table('disease_medicine_map as mapping')->join('diseases', 'diseases.id', '=', 'mapping.disease_id')
            ->where('diseases.is_active', true)->whereIn('mapping.medicine_id', $rows->pluck('medicine_id')->unique())
            ->when($diseaseId !== null, fn ($q) => $q->where('diseases.id', $diseaseId))
            ->select('mapping.medicine_id', 'diseases.id as disease_id', 'diseases.name')->distinct()->orderBy('diseases.name')->get();
        $comparisonMedicines = collect($comparisonAnalysis['medicine_signals'])->keyBy('id');
        $periods = $this->periods($window, $type);
        $comparisonStart = $comparisonAnalysis['period_start'];
        $medicines = $rows->groupBy('medicine_id')->map(function ($records, $id) use ($historical, $type, $periods, $window, $firstDates, $mappings, $comparisonMedicines, $comparisonStart) {
            $medicine = $records->first()->medicine;
            $totals = $records->groupBy(fn ($row) => $this->start(CarbonImmutable::parse($row->date_dispensed), $type)->toDateString());
            $first = $this->start(CarbonImmutable::parse($firstDates[$id]), $type)->toDateString();
            $history = array_map(function ($bounds) use ($totals, $first, $window) {
                $records = $totals->get($bounds['start'], collect());

                return $bounds + ['included_start' => max($bounds['start'], $window['from']),
                    'included_end' => min($bounds['end'], $window['to']),
                    'quantity' => $bounds['start'] < $first ? null : (int) $records->sum('quantity_dispensed'),
                    'transactions' => $records->count()];
            }, $periods);
            $comparison = $comparisonMedicines->get((int) $id);
            $diseases = $mappings->where('medicine_id', $id)->pluck('name')->unique()->values()->all();

            return ['id' => (int) $id, 'name' => $medicine->medicine_name, 'dosage' => $medicine->dosage,
                'dosage_form' => $medicine->dosage_form, 'unit' => $medicine->unit ?: 'units', 'diseases' => $diseases,
                'status' => $historical ? 'historical' : ($comparison['status'] ?? 'unassociated'),
                'current_quantity' => $historical ? null : ($comparison['current_quantity'] ?? (int) $totals->get($comparisonStart, collect())->sum('quantity_dispensed')),
                'baseline_average' => $historical ? null : ($comparison['baseline_average'] ?? null),
                'change_percent' => $historical ? null : ($comparison['change_percent'] ?? null),
                'total_quantity' => (int) $records->sum('quantity_dispensed'), 'transactions' => $records->count(), 'history' => $history,
                'transactions_data' => $records->map(fn ($record) => ['date' => $record->date_dispensed->toDateString(),
                    'quantity' => (int) $record->quantity_dispensed, 'record_id' => (int) $record->id])->values()->all()];
        })->sortBy(fn ($medicine) => mb_strtolower($medicine['name'].' '.$medicine['dosage'].' '.$medicine['dosage_form']))->values();
        $signals = $mappings->groupBy('disease_id')->map(fn ($maps, $id) => ['id' => (int) $id,
            'name' => $maps->first()->name, 'medicines' => $medicines->whereIn('id', $maps->pluck('medicine_id'))->values()->all()])->values()->all();
        $preferred = collect($comparisonAnalysis['medicine_signals'])->first(fn ($medicine) => $medicine['transactions'] > 0 && $medicines->contains('id', $medicine['id']));
        $preferredId = $preferred['id'] ?? $medicines->sortByDesc('transactions')->first()['id'] ?? null;
        $preferredMedicine = $medicines->firstWhere('id', $preferredId);
        $sparse = $preferredMedicine && count(array_filter($preferredMedicine['history'], fn ($point) => $point['transactions'] > 0)) < 2;

        return ['uses_saved_history' => $fallback, 'analysis' => array_replace($comparisonAnalysis, [
            'historical' => $historical, 'window' => $window, 'period_start' => $window['from'], 'period_end' => $window['to'],
            'medicine_signals' => $medicines->all(), 'signals' => $signals,
            'total_transactions' => $rows->count(), 'preferred_medicine_id' => $preferredId,
            'default_mode' => $historical && $sparse ? 'transactions' : 'periods',
        ])];
    }

    private function start(CarbonImmutable $date, string $type): CarbonImmutable
    {
        return $type === 'monthly' ? $date->startOfMonth() : $date->startOfWeek(CarbonImmutable::MONDAY);
    }

    private function next(CarbonImmutable $date, string $type): CarbonImmutable
    {
        return $type === 'monthly' ? $date->addMonth() : $date->addWeek();
    }

    private function periods(array $window, string $type): array
    {
        if ($window['from'] === null || $window['from'] > $window['to']) {
            return [];
        }
        $periods = [];
        $cursor = $this->start(CarbonImmutable::parse($window['from']), $type);
        $last = $this->start(CarbonImmutable::parse($window['to']), $type);
        while ($cursor <= $last) {
            $periods[] = ['start' => $cursor->toDateString(), 'end' => $this->next($cursor, $type)->subDay()->toDateString()];
            $cursor = $this->next($cursor, $type);
        }

        return $periods;
    }
}
