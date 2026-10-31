<?php

namespace App\Services;

use App\Models\DiseaseReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ForecastService
{
    /**
     * Palette used to color-code diseases in the recommended stock-up section.
     * Cycles if there are more diseases than colors.
     */
    private const DISEASE_PALETTE = [
        '#dc2626', // red
        '#f59e0b', // amber
        '#3b82f6', // blue
        '#8b5cf6', // violet
        '#ec4899', // pink
        '#06b6d4', // cyan
        '#84cc16', // lime
        '#f97316', // orange
        '#14b8a6', // teal
        '#a855f7', // purple
        '#eab308', // yellow
        '#ef4444', // rose
    ];

    /* ============================================================
       PLANNED FORECAST — driven by calendar_events
       ============================================================ */
    public function getPlannedForecast(?int $healthUnitId = null, int $days = 90): array
    {
        $items = [];
        $byMonth = [];

        try {
            $events = DB::table('calendar_events')
                ->when($healthUnitId, fn ($q) => $q->where('health_unit_id', $healthUnitId))
                ->where('event_date', '>=', now()->startOfDay())
                ->where('event_date', '<=', now()->addDays($days)->endOfDay())
                ->orderBy('event_date')
                ->get();
        } catch (\Throwable $e) {
            return $this->emptyPlanned($days);
        }

        foreach ($events as $ev) {
            $title = $ev->title ?? $ev->event_name ?? 'Scheduled Event';
            $date = $ev->event_date ?? $ev->date ?? null;
            $type = $ev->event_type ?? 'general';
            $target = (int) ($ev->target_count ?? $ev->expected_count ?? 0);

            $medicine = null;
            if (! empty($ev->medicine_id)) {
                $medicine = DB::table('medicines')->find($ev->medicine_id);
            }

            $items[] = [
                'id' => $ev->id,
                'title' => $title,
                'date' => $date,
                'type' => $type,
                'target_count' => $target,
                'medicine_name' => $medicine->medicine_name ?? null,
                'medicine_id' => $medicine->id ?? null,
                'medicine_dosage' => $medicine->dosage ?? null,
                'notes' => $ev->description ?? $ev->notes ?? null,
            ];

            if ($date) {
                $month = date('Y-m', strtotime($date));
                $byMonth[$month] = ($byMonth[$month] ?? 0) + $target;
            }
        }

        ksort($byMonth);

        return [
            'items' => $items,
            'summary' => [
                'total_events' => count($items),
                'total_targets' => array_sum(array_column($items, 'target_count')),
                'by_month' => $byMonth,
                'horizon_days' => $days,
            ],
        ];
    }

    private function emptyPlanned(int $days): array
    {
        return [
            'items' => [],
            'summary' => [
                'total_events' => 0,
                'total_targets' => 0,
                'by_month' => [],
                'horizon_days' => $days,
            ],
        ];
    }

    /* ============================================================
       UNPLANNED FORECAST — all diseases by reported cases (last N days)
       ============================================================ */
    public function getUnplannedForecast(?int $healthUnitId = null, ?int $topN = null, int $days = 30): array
    {
        try {
            $start = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(max(1, min(52, (int) ceil($days / 7))));
            $query = DiseaseReport::confirmedPeriods('weekly')
                ->join('diseases', 'diseases.id', '=', 'disease_reports.disease_id')
                ->when($healthUnitId, fn ($q) => $q->where('disease_reports.health_unit_id', $healthUnitId))
                ->whereDate('disease_reports.period_start', '>=', $start)
                ->select(
                    'diseases.id as disease_id',
                    'diseases.name as disease_name',
                    DB::raw('SUM(disease_reports.case_count) as total_cases')
                )
                ->groupBy('diseases.id', 'diseases.name')
                ->orderByDesc('total_cases');

            if ($topN !== null && $topN > 0) {
                $query->limit($topN);
            }

            $diseases = $query->toBase()->get();
        } catch (\Throwable $e) {
            Log::error('Disease forecast failed', ['exception' => $e]);
            throw $e;
        }

        $palette = self::DISEASE_PALETTE;
        $count = count($palette);

        $diseases = $diseases->map(function ($disease, $index) use ($palette, $count) {
            $disease->rank = $index + 1;
            $disease->color = $palette[$index % $count];

            try {
                $disease->medicines = DB::table('disease_medicine_map')
                    ->join('medicines', 'medicines.id', '=', 'disease_medicine_map.medicine_id')
                    ->where('disease_medicine_map.disease_id', $disease->disease_id)
                    ->whereNull('medicines.deleted_at')
                    ->get([
                        'medicines.id',
                        'medicines.medicine_name',
                        'medicines.dosage',
                        'medicines.category',
                        'medicines.requires_prescription',
                    ])
                    ->toArray();
            } catch (\Throwable $e) {
                $disease->medicines = [];
            }

            return $disease;
        });

        return [
            'items' => $diseases->toArray(),
            'days' => $days,
            'period_type' => 'weekly',
            'completed_periods' => max(1, min(52, (int) ceil($days / 7))),
        ];
    }

    /* ============================================================
       TRENDING DISEASES — last N days, grouped by date & disease
       ============================================================ */
    public function getTrendingDiseases(?int $healthUnitId = null, int $days = 30): array
    {
        $count = max(1, min(52, (int) ceil($days / 7)));
        $current = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);
        $start = $current->subWeeks($count);
        $rows = DiseaseReport::confirmedPeriods('weekly')->join('diseases', 'diseases.id', '=', 'disease_reports.disease_id')
            ->when($healthUnitId, fn ($q) => $q->where('disease_reports.health_unit_id', $healthUnitId))
            ->whereDate('disease_reports.period_start', '>=', $start)
            ->select('diseases.id as disease_id', 'diseases.name as disease_name', 'disease_reports.period_start')
            ->selectRaw('SUM(disease_reports.case_count) as cases')
            ->groupBy('diseases.id', 'diseases.name', 'disease_reports.period_start')->toBase()->get();
        $labels = [];
        for ($i = 0; $i < $count; $i++) {
            $labels[] = $start->addWeeks($i)->toDateString();
        }
        $datasets = [];
        foreach ($rows->groupBy('disease_id') as $diseaseRows) {
            $totals = $diseaseRows->pluck('cases', 'period_start');
            $data = array_map(fn ($date) => $totals->has($date) ? (int) $totals[$date] : null, $labels);
            $color = self::DISEASE_PALETTE[count($datasets) % count(self::DISEASE_PALETTE)];
            $datasets[] = ['label' => $diseaseRows->first()->disease_name, 'data' => $data, 'spanGaps' => false,
                'borderColor' => $color, 'backgroundColor' => $color, 'total_cases' => array_sum($data)];
        }

        return ['labels' => $labels, 'datasets' => $datasets, 'days' => $days, 'period_type' => 'weekly', 'completed_periods' => $count];
    }

    public function getForecast(?int $healthUnitId = null): array
    {
        $inventories = app(StockSummaryService::class)->inventories($healthUnitId ? [$healthUnitId] : null);
        $trendService = app(StockTrendService::class);
        $trends = $trendService->analyze($inventories->pluck('health_unit_id')->unique()->all());
        $empty = $trendService->emptyProjection();
        $horizon = (int) config('forecasting.forecast_days', 90);
        $reorderDays = (int) config('forecasting.lead_time_days', 14) + (int) config('forecasting.safety_stock_days', 7);
        $items = collect();
        foreach ($inventories as $inventory) {
            $projection = $trends[$inventory->health_unit_id][$inventory->medicine_id] ?? $empty;
            $fit = $projection['fit'];
            $daily = $projection['daily'];
            $forecastNeed = $this->units(array_sum(array_slice($daily, 0, $horizon)));
            $next30 = array_sum(array_slice($daily, 0, 30));
            $coverage = $trendService->coverage($inventory->available_stock, $daily);
            $days = (int) floor($coverage['days'] + 1e-9);
            $hasHistory = $fit['periods'] > 0;
            $noProjectedDemand = array_sum($daily) <= 0;
            $status = match (true) {
                $inventory->available_stock === 0 => 'critical',
                ! $hasHistory => 'unknown',
                $noProjectedDemand => 'surplus',
                $days < 7 => 'critical',
                $days < 30 => 'low',
                $days < 90 => 'adequate',
                default => 'surplus',
            };
            $items->push([
                'inventory' => $inventory,
                'medicine_name' => $inventory->medicine->medicine_name,
                'health_unit_name' => $inventory->healthUnit->name,
                'available_stock' => $inventory->available_stock,
                'monthly_consumption' => round($fit['historical_weekly_average'] / 7 * 30, 2),
                'daily_consumption' => round($fit['historical_weekly_average'] / 7, 2),
                'forecast_daily_consumption' => round($next30 / 30, 2),
                'forecast_next_30_days' => $this->units($next30),
                'days_remaining' => $inventory->available_stock === 0 ? 0 : (! $hasHistory ? 'N/A' : ($coverage['capped'] ? ($noProjectedDemand ? '?' : $days.'+') : $days)),
                'coverage_capped' => $coverage['capped'],
                'forecast_need_3mo' => $forecastNeed,
                'forecast_horizon_days' => $horizon,
                'shortage' => max(0, $forecastNeed - $inventory->available_stock),
                'status' => $status,
                'status_color' => $this->getStatusColor($status),
                'reorder_point' => $hasHistory ? $this->units(array_sum(array_slice($daily, 0, $reorderDays))) : null,
                'trend' => $fit['trend'],
                'model' => $fit,
                'weekly_history' => $projection['history'],
                'weekly_forecast' => $projection['future'],
                'history_end' => $projection['history_end'],
            ]);
        }

        return [
            'items' => $items,
            'summary' => [
                'total_items' => $items->count(),
                'low_stock_items' => $items->whereIn('status', ['low', 'critical'])->count(),
                'forecast_shortage' => $items->sum('shortage'),
                'model' => 'Weekly linear regression with a short-history average fallback',
                'horizon_days' => $horizon,
            ],
        ];
    }

    private function units(float $quantity): int
    {
        return (int) ceil(round(max(0, $quantity), 8));
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'critical' => '#dc2626',
            'low' => '#eab308',
            'adequate' => '#42ad8a',
            'surplus' => '#3b82f6',
            default => '#94a3b8',
        };
    }
}
