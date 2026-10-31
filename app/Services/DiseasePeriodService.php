<?php

namespace App\Services;

use App\Models\DiseaseReport;
use Carbon\CarbonImmutable;

class DiseasePeriodService
{
    public function bounds(string $date, string $type): array
    {
        if (! in_array($type, ['weekly', 'monthly'], true)) {
            throw new \InvalidArgumentException('Invalid reporting frequency.');
        }
        $day = CarbonImmutable::parse($date)->startOfDay();
        $start = $type === 'weekly' ? $day->startOfWeek(CarbonImmutable::MONDAY) : $day->startOfMonth();
        $end = $type === 'weekly' ? $start->addDays(6) : $start->endOfMonth();

        return ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }

    public function series(int $diseaseId, ?int $unitId, string $type = 'weekly', int $count = 12): array
    {
        $count = max(4, min(52, $count));
        $current = CarbonImmutable::parse($this->bounds(today()->toDateString(), $type)['start']);
        $first = $type === 'weekly' ? $current->subWeeks($count) : $current->subMonths($count);
        $rows = DiseaseReport::where('health_unit_id', $unitId)->where('disease_id', $diseaseId)->where('period_type', $type)
            ->whereDate('report_date', '<=', today())
            ->where(function ($q) use ($first, $current) {
                $q->whereBetween('period_start', [$first->toDateString(), $current->subDay()->toDateString()])
                    ->orWhere(fn ($legacy) => $legacy->whereNull('period_start')->whereDate('report_date', '>=', $first)->whereDate('report_date', '<', $current));
            })->get();
        $groups = $rows->groupBy(fn ($row) => $this->bounds($row->report_date->toDateString(), $type)['start']);
        $periods = [];
        for ($index = 0; $index < $count; $index++) {
            $start = $type === 'weekly' ? $first->addWeeks($index) : $first->addMonths($index);
            $bounds = $this->bounds($start->toDateString(), $type);
            $reports = $groups->get($bounds['start'], collect());
            $status = 'missing';
            $cases = null;
            if ($reports->count() > 1) {
                $status = 'conflict';
            } elseif ($reports->count() === 1) {
                $report = $reports->first();
                if (! $report->period_start || ! $report->period_end || $report->period_start->toDateString() !== $bounds['start'] || $report->period_end->toDateString() !== $bounds['end']) {
                    $status = 'unverified';
                } elseif (! $report->is_complete) {
                    $status = 'provisional';
                } elseif ($report->case_count < 0) {
                    $status = 'invalid';
                } else {
                    $status = 'reported';
                    $cases = $report->case_count;
                }
            }
            $periods[] = $bounds + ['cases' => $cases, 'status' => $status];
        }
        $future = [];
        for ($h = 0; $h < (int) config('disease_analysis.forecast_periods', 4); $h++) {
            $start = $type === 'weekly' ? $current->addWeeks($h) : $current->addMonths($h);
            $future[] = $this->bounds($start->toDateString(), $type) + ['is_current_period' => $h === 0];
        }

        return ['period_type' => $type, 'periods' => $periods, 'future_periods' => $future,
            'reported_periods' => count(array_filter($periods, fn ($p) => $p['status'] === 'reported')),
            'quality_counts' => array_count_values(array_column($periods, 'status'))];
    }
}
