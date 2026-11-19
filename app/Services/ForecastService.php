<?php

namespace App\Services;

use App\Models\DispensingRecord;
use App\Models\CalendarEvent;
use App\Models\DoHProgram;
use App\Models\RhuImmunizationSchedule;
use App\Models\VaccinationSchedule;
use App\Models\ImmunizationRecord;
use App\Models\ImmunizationSchedule;
use Carbon\Carbon;
use App\Models\HealthUnit;
use App\Models\Medicine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ForecastService
{
    /**
     * Palette used to color-code conditions linked to medicine consumption.
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
        $days = max(1, min(365, $days));
        $from = today();
        $through = $from->copy()->addDays($days - 1);
        $items = [];
        $unitNames = HealthUnit::pluck('name', 'id');
        $events = CalendarEvent::with('healthUnit')->whereNull('deleted_at')
            ->when($healthUnitId !== null, fn ($query) => $query->where('health_unit_id', $healthUnitId))
            ->whereDate('event_date', '>=', $from)->whereDate('event_date', '<=', $through)->orderBy('event_date')->get();
        $epiDates = [];
        foreach ($events as $event) {
            $date = $event->event_date->toDateString();
            if (in_array($event->type, ['immunization_day', 'immunization_drive', 'catch_up_campaign'], true)) {
                $epiDates[$event->health_unit_id.':'.$date.':'.($event->area_label ?? '')] = true;
            }
            $items[] = ['id' => 'calendar-'.$event->id, 'title' => $event->title, 'date' => $date,
                'type' => $event->type, 'source' => 'RHU calendar', 'health_unit_name' => $event->healthUnit?->name,
                'target_count' => null, 'medicine_name' => null, 'medicine_id' => null, 'medicine_dosage' => null,
                'notes' => $event->description, 'basis' => 'Calendar event; individual vaccine targets are listed separately.'];
        }

        foreach (RhuImmunizationSchedule::active()
            ->when($healthUnitId !== null, fn ($query) => $query->where('health_unit_id', $healthUnitId))->get() as $schedule) {
            for ($date = $from->copy(); $date->lte($through); $date->addDay()) {
                if ($date->dayOfWeek !== (int) $schedule->weekday) {
                    continue;
                }
                $matches = match ($schedule->pattern) {
                    'weekly' => true,
                    'monthly_first' => $date->day <= 7,
                    'monthly_last' => $date->copy()->addWeek()->month !== $date->month,
                    'monthly_nth' => (int) ceil($date->day / 7) === (int) $schedule->week_number,
                    default => false,
                };
                $key = $schedule->health_unit_id.':'.$date->toDateString().':'.$schedule->area_label;
                if (! $matches || isset($epiDates[$key])) {
                    continue;
                }
                $items[] = ['id' => 'epi-'.$schedule->id.'-'.$date->toDateString(),
                    'title' => 'EPI session - '.$schedule->area_label, 'date' => $date->toDateString(),
                    'type' => 'immunization_day', 'source' => 'Recurring EPI schedule',
                    'health_unit_name' => $unitNames[$schedule->health_unit_id] ?? null, 'target_count' => null,
                    'medicine_name' => null, 'medicine_id' => null, 'medicine_dosage' => null,
                    'notes' => $schedule->pattern_label, 'basis' => 'Active area schedule; attendance is not specified.'];
            }
        }

        foreach (DoHProgram::active()->get() as $program) {
            foreach ($program->startsBetween($from, $through) as $date) {
                $items[] = ['id' => 'doh-'.$program->id.'-'.$date->toDateString(), 'title' => $program->name,
                    'date' => $date->toDateString(), 'type' => 'national_program', 'source' => 'DoH program calendar',
                    'health_unit_name' => $healthUnitId === null ? 'All RHUs' : ($unitNames[$healthUnitId] ?? null),
                    'target_count' => null, 'medicine_name' => null, 'medicine_id' => null, 'medicine_dosage' => null,
                    'notes' => $program->description, 'basis' => 'Configured program date; material quantities are planned on Calendar.'];
            }
        }

        $doses = VaccinationSchedule::with(['patient', 'healthUnit'])->where('status', 'pending')
            ->whereNull('immunization_record_id')->whereNull('given_date')
            ->whereHas('patient', fn ($query) => $query->whereColumn('public_users.health_unit_id', 'vaccination_schedules.health_unit_id'))
            ->when($healthUnitId !== null, fn ($query) => $query->where('health_unit_id', $healthUnitId))
            ->whereDate('due_date', '>=', $from->copy()->subDays(35))->whereDate('due_date', '<=', $through)
            ->whereNotExists(function ($completed) {
                $completed->selectRaw('1')->from('vaccination_schedules as completed')
                    ->where('completed.status', 'given')->whereColumn('completed.public_user_id', 'vaccination_schedules.public_user_id')
                    ->whereColumn('completed.vaccine_name', 'vaccination_schedules.vaccine_name')
                    ->whereColumn('completed.dose_number', 'vaccination_schedules.dose_number');
            })->orderBy('due_date')->get();
        $vaccineCodes = [];
        foreach (ImmunizationSchedule::all() as $definition) {
            $vaccineCodes[mb_strtolower($definition->antigen_label)] = mb_strtolower($definition->antigen);
            $vaccineCodes[mb_strtolower($definition->antigen)] = mb_strtolower($definition->antigen);
        }
        $doseKey = fn ($patientId, $vaccine, $doseNumber) => $patientId.':'
            .($vaccineCodes[mb_strtolower($vaccine)] ?? mb_strtolower($vaccine)).':'.$doseNumber;
        $completedDoses = [];
        $patientIds = $doses->pluck('public_user_id')->unique();
        foreach (ImmunizationRecord::whereIn('public_user_id', $patientIds)->get() as $record) {
            $completedDoses[$doseKey($record->public_user_id, $record->antigen, $record->dose_number)] = true;
        }
        foreach (VaccinationSchedule::whereIn('public_user_id', $patientIds)->where('status', 'given')->get() as $given) {
            $completedDoses[$doseKey($given->public_user_id, $given->vaccine_name, $given->dose_number)] = true;
        }
        $doses = $doses->unique(fn ($dose) => $doseKey($dose->public_user_id, $dose->vaccine_name, $dose->dose_number))
            ->reject(fn ($dose) => isset($completedDoses[$doseKey($dose->public_user_id, $dose->vaccine_name, $dose->dose_number)]));
        $sessionService = app(VaccinationScheduleService::class);
        $sessionDates = [];
        $scheduled = $doses->map(function ($dose) use ($sessionService, &$sessionDates) {
            $key = $dose->health_unit_id.':'.$dose->due_date->toDateString().':'.mb_strtolower(trim((string) $dose->patient?->barangay));
            $dose->session_date = $sessionDates[$key] ??= $sessionService->sessionDateFor(
                $dose->due_date, (int) $dose->health_unit_id, $dose->patient?->barangay);

            return $dose;
        })->filter(fn ($dose) => $dose->session_date->betweenIncluded($from, $through));
        foreach ($scheduled->groupBy(fn ($dose) => $dose->health_unit_id.':'.$dose->session_date->toDateString().':'.$dose->vaccine_name) as $key => $group) {
            $dose = $group->first();
            $children = $group->pluck('public_user_id')->unique()->count();
            $items[] = ['id' => 'doses-'.$key, 'title' => 'Scheduled vaccination - '.$dose->vaccine_name,
                'date' => $dose->session_date->toDateString(), 'type' => 'vaccine_targets', 'source' => 'Pending vaccine appointments',
                'health_unit_name' => $dose->healthUnit?->name, 'target_count' => $group->count(),
                'medicine_name' => $dose->vaccine_name, 'medicine_id' => null, 'medicine_dosage' => null,
                'notes' => $children.' registered '.($children === 1 ? 'child' : 'children'),
                'basis' => 'One target per pending dose appointment. Inventory vial quantities require the product dose-per-vial details.'];
        }
        usort($items, fn ($left, $right) => strcmp($left['date'], $right['date']) ?: strcmp($left['title'], $right['title']));
        $byMonth = [];
        $eventsByMonth = [];
        for ($month = $from->copy()->startOfMonth(); $month->lte($through); $month->addMonth()) {
            $byMonth[$month->format('Y-m')] = 0;
            $eventsByMonth[$month->format('Y-m')] = 0;
        }
        foreach ($items as $item) {
            $month = substr($item['date'], 0, 7);
            if ($item['type'] === 'vaccine_targets') {
                $byMonth[$month] += $item['target_count'];
            } else {
                $eventsByMonth[$month]++;
            }
        }

        return ['items' => $items, 'summary' => ['total_events' => array_sum($eventsByMonth),
            'total_targets' => array_sum($byMonth), 'target_children' => $scheduled->pluck('public_user_id')->unique()->count(),
            'by_month' => $byMonth, 'events_by_month' => $eventsByMonth, 'horizon_days' => $days,
            'from' => $from->toDateString(), 'through' => $through->toDateString()]];
    }

    /** Disease-linked supply signals from completed medicine dispensing, kept separate by RHU and medicine. */
    public function getUnplannedForecast(?int $healthUnitId = null, ?int $topN = null, int $days = 30): array
    {
        $units = HealthUnit::query()
            ->when($healthUnitId !== null, fn ($query) => $query->whereKey($healthUnitId),
                fn ($query) => $query->whereIn('facility_type', ['Rural Health Unit', 'RHU'])
                    ->whereIn('id', DispensingRecord::query()->select('health_unit_id')->distinct()))
            ->orderBy('name')->get(['id', 'name']);
        $items = [];
        $metadata = Medicine::query()->get(['id', 'requires_prescription'])->keyBy('id');
        foreach ($units as $unit) {
            $analysis = app(DiseaseConsumptionTrendService::class)->analyze((int) $unit->id);
            foreach ($analysis['signals'] as $signal) {
                $medicines = collect($signal['medicines'])->filter(fn ($medicine) => $medicine['transactions'] > 0)
                    ->map(fn ($medicine) => (object) [
                        'id' => $medicine['id'], 'medicine_name' => $medicine['name'],
                        'dosage' => $medicine['dosage'], 'dosage_form' => $medicine['dosage_form'], 'unit' => $medicine['unit'],
                        'requires_prescription' => (bool) ($metadata[$medicine['id']]->requires_prescription ?? false),
                        'current_quantity' => $medicine['current_quantity'], 'baseline_average' => $medicine['baseline_average'],
                        'change_percent' => $medicine['change_percent'], 'status' => $medicine['status'],
                        'history' => $medicine['history'],
                    ])->values()->all();
                if ($medicines === []) {
                    continue;
                }
                $items[] = (object) [
                    'disease_id' => $signal['id'], 'disease_name' => $signal['name'],
                    'health_unit_id' => (int) $unit->id, 'health_unit_name' => $unit->name,
                    'status' => $signal['status'], 'elevated_medicines' => $signal['elevated_medicines'],
                    'new_medicines' => $signal['new_medicines'], 'transactions' => $signal['transactions'],
                    'period_start' => $analysis['period_start'], 'period_end' => $analysis['period_end'],
                    'medicines' => $medicines,
                ];
            }
        }
        $priority = ['elevated' => 0, 'new' => 1, 'insufficient' => 2, 'typical' => 3];
        usort($items, fn ($left, $right) => (($priority[$left->status] ?? 4) <=> ($priority[$right->status] ?? 4))
            ?: ($right->elevated_medicines <=> $left->elevated_medicines)
            ?: strcmp($left->disease_name, $right->disease_name)
            ?: strcmp($left->health_unit_name, $right->health_unit_name));
        if ($topN !== null && $topN > 0) {
            $items = array_slice($items, 0, $topN);
        }
        foreach ($items as $index => $item) {
            $item->rank = $index + 1;
            $item->color = self::DISEASE_PALETTE[$index % count(self::DISEASE_PALETTE)];
        }

        return ['items' => $items, 'days' => $days, 'period_type' => 'weekly',
            'completed_periods' => 5, 'baseline_periods' => 4, 'threshold_percent' => 50,
            'source' => 'medicine_consumption'];
    }

    /** Unit-aware medicine histories supporting possible disease trends; no inferred case totals. */
    public function getTrendingDiseases(?int $healthUnitId = null, int $days = 30): array
    {
        $count = max(1, min(52, (int) ceil($days / 7)));
        $start = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY)->subWeeks($count);
        $labels = [];
        for ($index = 0; $index < $count; $index++) {
            $labels[] = $start->addWeeks($index)->toDateString();
        }
        $datasets = [];
        $histories = [];
        foreach ($this->getUnplannedForecast($healthUnitId, null, $days)['items'] as $disease) {
            foreach ($disease->medicines as $medicine) {
                $key = $disease->health_unit_id.':'.$medicine->id;
                if (! isset($histories[$key])) {
                    $usage = app(MedicineUsageTrendService::class)->analyze($disease->health_unit_id, null, $medicine->id, 'weekly', max(4, $count));
                    $histories[$key] = array_column($usage['history'], 'quantity', 'start');
                }
                $color = self::DISEASE_PALETTE[count($datasets) % count(self::DISEASE_PALETTE)];
                $datasets[] = [
                    'label' => $disease->disease_name.' / '.$medicine->medicine_name.' / '.$disease->health_unit_name.' ('.$medicine->unit.')',
                    'disease_id' => $disease->disease_id, 'medicine_id' => $medicine->id,
                    'health_unit_id' => $disease->health_unit_id, 'unit' => $medicine->unit,
                    'data' => array_map(fn ($date) => $histories[$key][$date] ?? null, $labels),
                    'spanGaps' => false, 'borderColor' => $color, 'backgroundColor' => $color,
                ];
            }
        }

        return ['labels' => $labels, 'datasets' => $datasets, 'days' => $days,
            'period_type' => 'weekly', 'completed_periods' => $count, 'source' => 'medicine_consumption'];
    }

    public function getForecast(?int $healthUnitId = null): array
    {
        $inventories = app(StockSummaryService::class)->inventories($healthUnitId ? [$healthUnitId] : null, true);
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
                'shared_available_stock' => $inventory->shared_available_stock,
                'recipient_available_stock' => $inventory->recipient_available_stock,
                'monthly_consumption' => round($fit['historical_weekly_average'] / 7 * 30, 2),
                'daily_consumption' => round($fit['historical_weekly_average'] / 7, 2),
                'forecast_daily_consumption' => round($next30 / 30, 2),
                'forecast_next_30_days' => $this->units($next30),
                'days_remaining' => $inventory->available_stock === 0 ? 0 : (! $hasHistory ? 'N/A' : ($coverage['capped'] ? ($noProjectedDemand ? 'No projected use' : $days.'+') : $days)),
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
                'history_start' => $projection['history'][0]['start'] ?? null,
                'history_end' => $projection['history_end'],
                'history_total' => array_sum(array_column($projection['history'], 'quantity')),
                'base_unit' => $inventory->medicine->unit ?: 'units',
                'forecast_start' => $projection['forecast_start'],
                'forecast_end' => today()->addDays($horizon - 1)->toDateString(),
                'forecast_complete_weeks' => intdiv($horizon, 7),
                'forecast_remaining_days' => $horizon % 7,
                'forecast_full_weeks_need' => array_sum(array_slice($daily, 0, intdiv($horizon, 7) * 7)),
                'forecast_partial_week_need' => array_sum(array_slice($daily, intdiv($horizon, 7) * 7, $horizon % 7)),
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
