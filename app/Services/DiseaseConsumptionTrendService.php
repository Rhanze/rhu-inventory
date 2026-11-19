<?php

namespace App\Services;

use App\Models\DispensingRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Disease-related consumption signals, with evidence kept in each medicine's own units. */
class DiseaseConsumptionTrendService
{
    public function analyze(int $healthUnitId, ?string $barangay = null, string $periodType = 'weekly', ?int $dispenserId = null, ?string $fromDate = null, ?string $toDate = null, ?int $diseaseId = null): array
    {
        if (! in_array($periodType, ['weekly', 'monthly'], true)) {
            throw new \InvalidArgumentException('Consumption periods must be weekly or monthly.');
        }
        $barangay = $barangay !== null ? trim($barangay) : null;
        if ($barangay === '') {
            throw new \InvalidArgumentException('Choose a named barangay or use the RHU scope.');
        }

        $historical = $fromDate !== null || $toDate !== null;
        $today = CarbonImmutable::today();
        $currentStart = $this->periodStart($today, $periodType);
        $latestStart = $this->offset($currentStart, -1, $periodType);
        $baselineStart = $this->offset($currentStart, -5, $periodType);
        $query = $this->recordsQuery($healthUnitId, $barangay, $dispenserId)->whereExists(function ($maps) use ($diseaseId) {
            $maps->selectRaw('1')->from('disease_medicine_map')
                ->join('diseases', 'diseases.id', '=', 'disease_medicine_map.disease_id')
                ->whereColumn('disease_medicine_map.medicine_id', 'dispensing_records.medicine_id')->where('diseases.is_active', true)
                ->when($diseaseId !== null, fn ($query) => $query->where('diseases.id', $diseaseId));
        });
        $window = $historical ? $this->historicalWindow($query, $periodType, $fromDate, $toDate) : [
            'from' => $baselineStart->toDateString(), 'to' => $currentStart->subDay()->toDateString(),
        ];
        $windowQuery = (clone $query)->whereDate('date_dispensed', '<=', $window['to']);
        if ($window['from'] !== null) {
            $windowQuery->whereDate('date_dispensed', '>=', $window['from']);
        }

        $mappingQuery = DB::table('disease_medicine_map as mapping')
            ->join('diseases', 'diseases.id', '=', 'mapping.disease_id')
            ->join('medicines', 'medicines.id', '=', 'mapping.medicine_id')
            ->where(fn ($medicine) => $medicine->whereNull('medicines.category')
                ->orWhereRaw('LOWER(medicines.category) <> ?', ['vaccine']))
            ->where('diseases.is_active', true)->when($diseaseId !== null, fn ($query) => $query->where('diseases.id', $diseaseId));
        if ($historical) {
            $mappingQuery->whereIn('medicines.id', (clone $windowQuery)->select('medicine_id'));
        } else {
            $mappingQuery->whereNull('medicines.deleted_at')->where('medicines.is_dispensable', true);
        }
        $mappings = $mappingQuery->select('mapping.disease_id', 'diseases.name as disease_name',
            'mapping.medicine_id', 'medicines.medicine_name', 'medicines.dosage', 'medicines.dosage_form', 'medicines.unit')
            ->distinct()->orderBy('diseases.name')->orderBy('medicines.medicine_name')->orderBy('mapping.medicine_id')->get();
        $medicineIds = $mappings->pluck('medicine_id')->unique()->values();
        $rows = ($historical && $window['from'] === null) ? collect()
            : $windowQuery->whereIn('medicine_id', $medicineIds)->get(['medicine_id', 'date_dispensed', 'quantity_dispensed']);
        $firstDates = (clone $query)->whereIn('medicine_id', $medicineIds)
            ->when(! $historical, fn ($q) => $q->whereDate('date_dispensed', '<', $currentStart->toDateString()))
            ->select('medicine_id')->selectRaw('MIN(date_dispensed) AS first_date')->groupBy('medicine_id')->pluck('first_date', 'medicine_id');
        $savedCounts = ($historical ? clone $windowQuery : clone $query)->whereIn('medicine_id', $medicineIds)
            ->select('medicine_id')->selectRaw('COUNT(*) AS record_count')->groupBy('medicine_id')->pluck('record_count', 'medicine_id');

        $periods = $this->periods($window, $periodType);
        $totals = [];
        foreach ($rows as $row) {
            $key = $this->periodStart(CarbonImmutable::parse($row->date_dispensed), $periodType)->toDateString();
            $totals[$row->medicine_id][$key]['quantity'] = ($totals[$row->medicine_id][$key]['quantity'] ?? 0) + $row->quantity_dispensed;
            $totals[$row->medicine_id][$key]['transactions'] = ($totals[$row->medicine_id][$key]['transactions'] ?? 0) + 1;
        }

        $medicineSignals = [];
        foreach ($mappings->unique('medicine_id') as $medicine) {
            $id = (int) $medicine->medicine_id;
            $first = isset($firstDates[$id]) ? $this->periodStart(CarbonImmutable::parse($firstDates[$id]), $periodType) : null;
            $latest = $totals[$id][$latestStart->toDateString()]['quantity'] ?? 0;
            $baseline = null;
            $change = null;
            $status = $historical ? 'historical' : 'insufficient';
            if (! $historical && isset($totals[$id]) && $first && $first->lessThanOrEqualTo($baselineStart)) {
                $baselineTotal = 0;
                for ($index = 0; $index < 4; $index++) {
                    $baselineTotal += $totals[$id][$this->offset($baselineStart, $index, $periodType)->toDateString()]['quantity'] ?? 0;
                }
                $baseline = $baselineTotal / 4;
                $status = 'typical';
                if ($baseline > 0) {
                    $change = ($latest - $baseline) / $baseline * 100;
                    if ($latest >= $baseline * 1.5) {
                        $status = 'elevated';
                    }
                } elseif ($latest > 0) {
                    $status = 'new';
                }
            }
            $history = [];
            foreach ($periods as $bounds) {
                $key = $bounds['start'];
                $history[] = $bounds + [
                    'included_start' => max($bounds['start'], $window['from']),
                    'included_end' => min($bounds['end'], $window['to']),
                    'quantity' => $first && $key >= $first->toDateString() ? ($totals[$id][$key]['quantity'] ?? 0) : null,
                    'transactions' => $totals[$id][$key]['transactions'] ?? 0,
                ];
            }
            $medicineSignals[$id] = [
                'id' => $id, 'name' => $medicine->medicine_name, 'dosage' => $medicine->dosage,
                'dosage_form' => $medicine->dosage_form, 'unit' => $medicine->unit ?: 'units',
                'status' => $status, 'current_quantity' => $historical ? null : $latest,
                'baseline_average' => $baseline, 'change_percent' => $change,
                'total_quantity' => (int) array_sum(array_column($history, 'quantity')),
                'transactions' => (int) ($savedCounts[$id] ?? 0), 'history' => $history,
            ];
        }

        $signals = [];
        $overview = ['diseases_reviewed' => 0, 'medicines_reviewed' => count($medicineSignals),
            'elevated' => 0, 'new' => 0, 'typical' => 0, 'insufficient' => 0, 'historical' => 0];
        foreach ($mappings->groupBy('disease_id') as $diseaseId => $diseaseMappings) {
            $medicines = $diseaseMappings->unique('medicine_id')->map(fn ($map) => $medicineSignals[(int) $map->medicine_id])->values()->all();
            $statuses = array_column($medicines, 'status');
            $status = $historical ? 'historical' : (in_array('elevated', $statuses, true) ? 'elevated'
                : (in_array('new', $statuses, true) ? 'new' : (in_array('typical', $statuses, true) ? 'typical' : 'insufficient')));
            $signals[] = ['id' => (int) $diseaseId, 'name' => $diseaseMappings->first()->disease_name,
                'status' => $status, 'medicines' => $medicines, 'medicines_reviewed' => count($medicines),
                'elevated_medicines' => count(array_filter($statuses, fn ($value) => $value === 'elevated')),
                'new_medicines' => count(array_filter($statuses, fn ($value) => $value === 'new')),
                'transactions' => array_sum(array_column($medicines, 'transactions'))];
            $overview[$status]++;
        }
        $priority = ['elevated' => 0, 'new' => 1, 'insufficient' => 2, 'typical' => 3, 'historical' => 4];
        usort($signals, fn ($left, $right) => ($priority[$left['status']] <=> $priority[$right['status']]) ?: strcmp($left['name'], $right['name']));
        $overview['diseases_reviewed'] = count($signals);

        return ['health_unit_id' => $healthUnitId, 'scope' => $barangay !== null ? 'area' : ($dispenserId !== null ? 'personal' : 'rhu'),
            'location' => $barangay, 'period_type' => $periodType, 'historical' => $historical,
            'period_start' => $historical ? $window['from'] : $latestStart->toDateString(),
            'period_end' => $historical ? $window['to'] : $currentStart->subDay()->toDateString(),
            'baseline_start' => $historical ? null : $baselineStart->toDateString(),
            'baseline_end' => $historical ? null : $latestStart->subDay()->toDateString(),
            'window' => $window, 'threshold_percent' => 50, 'signals' => $signals, 'overview' => $overview,
            'medicine_signals' => array_values($medicineSignals), 'total_transactions' => (int) $savedCounts->sum(),
            'comparison_transactions' => $rows->count()];
    }

    /** Shared scope for saved-consumption history and exports; callers apply their own exact date range. */
    public function recordsQuery(int $healthUnitId, ?string $barangay = null, ?int $dispenserId = null)
    {
        return DispensingRecord::qualifying()->where('health_unit_id', $healthUnitId)
            ->whereHas('medicine', fn ($medicine) => $medicine->nonVaccine())
            ->whereDate('date_dispensed', '<=', CarbonImmutable::today()->toDateString())
            ->when($barangay !== null, fn ($q) => $q->forBarangays([$barangay]))
            ->when($barangay === null && $dispenserId !== null, fn ($q) => $q->where('dispensed_by', $dispenserId));
    }

    private function historicalWindow($query, string $type, ?string $fromDate, ?string $toDate): array
    {
        $to = CarbonImmutable::parse($toDate ?? CarbonImmutable::today()->toDateString())->min(CarbonImmutable::today())->toDateString();
        $from = $fromDate ?? (clone $query)->whereDate('date_dispensed', '<=', $to)->min('date_dispensed');
        if ($from !== null) {
            $from = CarbonImmutable::parse($from)->toDateString();
            if ($from <= $to && CarbonImmutable::parse($to)->greaterThan(CarbonImmutable::parse($from)->addYears(10))) {
                throw ValidationException::withMessages(['date_from' => 'Choose ten years or less for historical consumption analysis.']);
            }
        }

        return ['from' => $from, 'to' => $to];
    }

    private function periods(array $window, string $type): array
    {
        if ($window['from'] === null || $window['from'] > $window['to']) {
            return [];
        }
        $periods = [];
        $cursor = $this->periodStart(CarbonImmutable::parse($window['from']), $type);
        $last = $this->periodStart(CarbonImmutable::parse($window['to']), $type);
        while ($cursor->lessThanOrEqualTo($last)) {
            $periods[] = ['start' => $cursor->toDateString(), 'end' => $this->offset($cursor, 1, $type)->subDay()->toDateString()];
            $cursor = $this->offset($cursor, 1, $type);
        }

        return $periods;
    }

    private function periodStart(CarbonImmutable $date, string $type): CarbonImmutable
    {
        return $type === 'weekly' ? $date->startOfWeek(CarbonImmutable::MONDAY) : $date->startOfMonth();
    }

    private function offset(CarbonImmutable $date, int $offset, string $type): CarbonImmutable
    {
        return $type === 'weekly' ? $date->addWeeks($offset) : $date->addMonths($offset);
    }
}
