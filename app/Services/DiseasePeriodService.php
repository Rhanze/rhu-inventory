<?php

namespace App\Services;

use App\Models\DiseaseReport;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

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

    public function series(int $diseaseId, ?int $unitId, string $type = 'weekly', int $count = 12, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($fromDate !== null || $toDate !== null) {
            return $this->historicalSeries($diseaseId, $unitId, $type, $fromDate, $toDate);
        }
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

    /** Full case-report periods overlap a requested date; they are not daily case counts. */
    public function applyHistoryRange($query, ?string $fromDate, ?string $toDate, ?string $type = null)
    {
        if ($fromDate === null && $toDate === null) {
            return $query;
        }

        return $query->where(function ($frequencies) use ($fromDate, $toDate, $type) {
            foreach ($type ? [$type] : ['weekly', 'monthly'] as $frequency) {
                $frequencies->orWhere(function ($reports) use ($fromDate, $toDate, $frequency) {
                    $reports->where('period_type', $frequency)->where(function ($coverage) use ($fromDate, $toDate, $frequency) {
                        $coverage->where(function ($canonical) use ($fromDate, $toDate) {
                            $canonical->whereNotNull('period_start')->whereNotNull('period_end')
                                ->when($fromDate, fn ($q) => $q->whereDate('period_end', '>=', $fromDate))
                                ->when($toDate, fn ($q) => $q->whereDate('period_start', '<=', $toDate));
                        })->orWhere(function ($legacy) use ($fromDate, $toDate, $frequency) {
                            $legacy->where(fn ($q) => $q->whereNull('period_start')->orWhereNull('period_end'))
                                ->when($fromDate, fn ($q) => $q->whereDate('report_date', '>=', $this->bounds($fromDate, $frequency)['start']))
                                ->when($toDate, fn ($q) => $q->whereDate('report_date', '<=', $this->bounds($toDate, $frequency)['end']));
                        });
                    });
                });
            }
        });
    }

    /** Callers resolve an omitted start from their scoped saved records. */
    public function historicalWindow(string $type, ?string $fromDate, ?string $toDate): array
    {
        $today = CarbonImmutable::today();
        $to = CarbonImmutable::parse($toDate ?? $today->toDateString())->min($today);
        if ($fromDate === null) {
            return ['from' => null, 'to' => $to->toDateString(), 'periods' => []];
        }
        $last = CarbonImmutable::parse($this->bounds($to->toDateString(), $type)['start']);
        $from = CarbonImmutable::parse($fromDate);
        if ($from->lessThanOrEqualTo($to) && $to->greaterThan($from->addYears(10))) {
            throw ValidationException::withMessages(['date_from' => 'Choose ten years or less for historical trend charts. All retained reports remain available without a date filter or in the CSV export.']);
        }
        $periods = [];
        if ($from->lessThanOrEqualTo($to)) {
            $cursor = CarbonImmutable::parse($this->bounds($from->toDateString(), $type)['start']);
            while ($cursor->lessThanOrEqualTo($last)) {
                $periods[] = $this->bounds($cursor->toDateString(), $type);
                $cursor = $type === 'weekly' ? $cursor->addWeek() : $cursor->addMonth();
            }
        }

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'periods' => $periods];
    }

    private function historicalSeries(int $diseaseId, ?int $unitId, string $type, ?string $fromDate, ?string $toDate): array
    {
        $query = DiseaseReport::where('health_unit_id', $unitId)->where('disease_id', $diseaseId)->where('period_type', $type)
            ->whereDate('report_date', '<=', today());
        if ($fromDate === null) {
            $firstDate = $this->applyHistoryRange(clone $query, null, $toDate, $type)->min('report_date');
            // A reporting week/month can overlap the end date even when its report was saved later.
            $fromDate = $firstDate ? $this->bounds($firstDate, $type)['start'] : null;
        }
        $window = $this->historicalWindow($type, $fromDate, $toDate);
        $rows = $window['periods'] ? $this->applyHistoryRange($query, $window['from'], $window['to'], $type)->get() : collect();
        $groups = $rows->groupBy(fn ($row) => $this->bounds($row->report_date->toDateString(), $type)['start']);
        $periods = [];
        foreach ($window['periods'] as $bounds) {
            $reports = $groups->get($bounds['start'], collect());
            $status = 'missing';
            $cases = null;
            if ($reports->count() > 1) {
                $status = 'conflict';
            } elseif ($reports->count() === 1) {
                $report = $reports->first();
                if (! $report->period_start || ! $report->period_end || $report->period_start->toDateString() !== $bounds['start'] || $report->period_end->toDateString() !== $bounds['end']) {
                    $status = 'unverified';
                } elseif (! $report->is_complete || $report->period_end->greaterThanOrEqualTo(today())) {
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

        return ['period_type' => $type, 'historical' => true, 'window' => $window,
            'periods' => $periods, 'future_periods' => [],
            'reported_periods' => count(array_filter($periods, fn ($period) => $period['status'] === 'reported')),
            'quality_counts' => array_count_values(array_column($periods, 'status'))];
    }
}
