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
        $logs = app(StockDemandHistoryService::class)->events($unitIds, $start->toDateString(),
            $today->subDay()->toDateString());
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
        return $this->projectStock([['quantity' => $available, 'expires' => null]], $daily)['coverage'];
    }

    /** Fulfill each day's expected demand before removing unused lots that expire that day. */
    public function projectStock(array $batches, array $daily): array
    {
        $start = CarbonImmutable::today();
        $firstDay = $start->toDateString();
        $batches = array_filter($batches, fn ($batch) => $batch['quantity'] > 0
            && ($batch['expires'] === null || $batch['expires'] >= $firstDay));
        usort($batches, fn ($left, $right) => ($left['expires'] ?? '9999-12-31') <=> ($right['expires'] ?? '9999-12-31'));
        $remaining = array_sum(array_column($batches, 'quantity'));
        $coverage = $remaining <= 0 ? 0 : null;
        $unfilled = [];
        $expired = [];

        foreach ($daily as $offset => $demand) {
            $date = $start->addDays($offset)->toDateString();
            $demand = max(0, $demand);
            if ($coverage === null && ($remaining <= 1e-9 || ($demand > 0 && $remaining <= $demand + 1e-9))) {
                $coverage = $offset + ($demand > 0 ? min(1, $remaining / $demand) : 0);
            }

            $pending = $demand;
            $expiryLoss = 0;
            foreach ($batches as &$batch) {
                $used = min($batch['quantity'], $pending);
                $pending -= $used;
                $batch['quantity'] -= $used;

                if ($batch['expires'] !== null && $batch['expires'] <= $date) {
                    $expiryLoss += $batch['quantity'];
                    $batch['quantity'] = 0;
                }
            }
            unset($batch);

            $remaining = max(0, $remaining - ($demand - $pending) - $expiryLoss);
            if ($coverage === null && $remaining <= 1e-9) {
                $coverage = $offset + 1;
            }
            $unfilled[] = $pending;
            $expired[] = $expiryLoss;
        }

        return [
            'coverage' => ['days' => $coverage ?? count($daily), 'capped' => $coverage === null],
            'unfilled' => $unfilled,
            'expired' => $expired,
        ];
    }
}
