<?php

namespace App\Services;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DiseaseReport;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class DiseaseAnalysisService
{
    /** Build a reporting grid, OLS trend, and Holt forecast for one disease and RHU. */
    public function analyze(int $diseaseId, ?int $healthUnitId, string $periodType = 'weekly', int $periods = 12): array
    {
        $series = app(DiseasePeriodService::class)->series($diseaseId, $healthUnitId, $periodType, $periods);
        $values = array_column($series['periods'], 'cases');
        $horizon = count($series['future_periods']);
        $regression = app(DiseaseRegressionModel::class)->fit($values, $horizon);
        $holt = app(HoltDiseaseModel::class)->fit($values, $horizon);
        $observed = array_filter($values, fn ($v) => $v !== null);
        $forecast = [];
        foreach ($series['future_periods'] as $index => $period) {
            $forecast[] = $period + ['regression' => $regression['predictions'][$index], 'holt' => $holt['predictions'][$index]];
        }

        return $series + ['regression' => $regression, 'holt' => $holt, 'forecast' => $forecast,
            'direction' => $regression['direction'], 'observed_total' => array_sum($observed),
            'baseline' => count($observed) ? array_sum($observed) / count($observed) : null];
    }

    public function trendFor(int $diseaseId, ?int $healthUnitId, int $weeks = 12): array
    {
        $analysis = $this->analyze($diseaseId, $healthUnitId, 'weekly', $weeks);
        $values = array_column($analysis['periods'], 'cases');

        return $analysis + ['this_week' => null, 'last_week' => end($values), 'weekly_cases' => $values,
            'reported_this_week' => false, 'weeks' => count($values)];
    }

    /**
     * Top diseases by total cases in the last N weeks.
     *
     * @return array<int, array{disease: Disease, total_cases: int, trend: array}>
     */
    public function topDiseases(int $healthUnitId, int $weeks = 12, int $limit = 3): array
    {
        $since = now()->subWeeks($weeks)->startOfDay()->toDateString();

        $rows = DiseaseReport::confirmedPeriods('weekly')
            ->select('disease_id', DB::raw('SUM(case_count) as total_cases'))
            ->where('health_unit_id', $healthUnitId)
            ->where('report_date', '>=', $since)
            ->groupBy('disease_id')
            ->orderByDesc('total_cases')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $disease = Disease::find($row->disease_id);
            if (! $disease) {
                continue;
            }

            $result[] = [
                'disease' => $disease,
                'total_cases' => (int) $row->total_cases,
                'trend' => $this->trendFor($row->disease_id, $healthUnitId),
            ];
        }

        return $result;
    }

    /**
     * Stock recommendations for the RHU.
     * A recommendation fires when:
     *   - a top disease is RISING, AND
     *   - the mapped medicine's total stock is below effectiveMinStock
     */
    public function recommendations(int $healthUnitId, int $topLimit = 3): array
    {
        $recommendations = [];

        foreach ($this->topDiseases($healthUnitId, 12, $topLimit) as $item) {
            if ($item['trend']['direction'] !== 'rising') {
                continue;
            }

            $disease = $item['disease'];

            $maps = DiseaseMedicineMap::where('disease_id', $disease->id)
                ->with('medicine')
                ->orderBy('priority')
                ->get();

            foreach ($maps as $map) {
                if (! $map->medicine) {
                    continue;
                }

                $stock = (int) Inventory::where('health_unit_id', $healthUnitId)
                    ->where('medicine_id', $map->medicine_id)
                    ->sum('available_stock');

                $threshold = $map->medicine->effectiveMinStock();

                if ($stock < $threshold) {
                    $recommendations[] = [
                        'disease' => $disease,
                        'medicine' => $map->medicine,
                        'current_stock' => $stock,
                        'threshold' => $threshold,
                        'priority' => $map->priority,
                        'reason' => 'Rising '.$disease->name.' cases',
                    ];
                }
            }
        }

        return $recommendations;
    }
}
