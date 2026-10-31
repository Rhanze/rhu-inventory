<?php

namespace App\Services;

use App\Models\StockLog;
use Carbon\CarbonImmutable;

class StockTrendService
{
    public function __construct(private StockTrendModel $model) {}

    public function analyze(array $unitIds): array
    {
        $weeks = max(4, min(52, (int) config('forecasting.history_weeks', 12)));
        $today = CarbonImmutable::today();
        $start = $today->subWeeks($weeks);
        $logs = StockLog::demandReleases()->whereIn('health_unit_id', $unitIds)
            ->whereDate('date_added', '>=', $start->toDateString())
            ->whereDate('date_added', '<', $today->toDateString())
            ->get(['health_unit_id', 'medicine_id', 'date_added', 'quantity_added']);
        $groups = [];
        foreach ($logs as $log) {
            $index = intdiv((int) $start->diffInDays($log->date_added->toDateString()), 7);
            $groups[$log->health_unit_id][$log->medicine_id][$index] =
                ($groups[$log->health_unit_id][$log->medicine_id][$index] ?? 0) + $log->quantity_added;
        }
        $result = [];
        foreach ($groups as $unitId => $medicines) {
            foreach ($medicines as $medicineId => $totals) {
                $first = min(array_keys($totals));
                $history = [];
                // Zero gaps after the first release, without inventing earlier observations.
                for ($index = $first; $index < $weeks; $index++) {
                    $periodStart = $start->addWeeks($index);
                    $history[] = ['start' => $periodStart->toDateString(), 'end' => $periodStart->addDays(6)->toDateString(),
                        'quantity' => $totals[$index] ?? 0];
                }
                $result[$unitId][$medicineId] = $this->projection($history, $today);
            }
        }

        return $result;
    }

    public function emptyProjection(): array
    {
        return $this->projection([], CarbonImmutable::today());
    }

    private function projection(array $history, CarbonImmutable $today): array
    {
        $fit = $this->model->fit(array_column($history, 'quantity'));
        $days = max(365, (int) config('forecasting.coverage_days', 365), (int) config('forecasting.forecast_days', 90));
        $daily = $this->model->dailyPredictions($fit, $days);
        $future = [];
        for ($week = 1; $week <= (int) ceil(config('forecasting.forecast_days', 90) / 7); $week++) {
            $periodStart = $today->addWeeks($week - 1);
            $future[] = ['start' => $periodStart->toDateString(), 'end' => $periodStart->addDays(6)->toDateString(),
                'quantity' => $this->model->weeklyPrediction($fit, $week)];
        }

        return ['fit' => $fit, 'history' => $history, 'future' => $future, 'daily' => $daily,
            'history_end' => $today->subDay()->toDateString(), 'forecast_start' => $today->toDateString()];
    }

    public function coverage(int $available, array $daily): array
    {
        if ($available <= 0) {
            return ['days' => 0, 'capped' => false];
        }
        $remaining = $available;
        foreach ($daily as $index => $demand) {
            if ($demand > 0 && $remaining <= $demand) {
                return ['days' => $index + $remaining / $demand, 'capped' => false];
            }
            $remaining -= $demand;
        }

        return ['days' => count($daily), 'capped' => true];
    }
}
