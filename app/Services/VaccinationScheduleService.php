<?php

namespace App\Services;

use App\Models\HealthUnit;
use App\Models\PublicUser;
use App\Models\VaccinationSchedule;
use Carbon\Carbon;

class VaccinationScheduleService
{
    /**
     * DOH Philippines National Immunization Program (NIP) schedule.
     * Format: [vaccine_name, dose_number, offset_days_from_birth]
     */
    private const EPI_SCHEDULE = [
        ['BCG',          1,   0],
        ['Hepatitis B',  1,   0],
        ['Pentavalent',  1,  42],
        ['OPV',          1,  42],
        ['PCV',          1,  42],
        ['Rotavirus',    1,  42],
        ['Pentavalent',  2,  70],
        ['OPV',          2,  70],
        ['PCV',          2,  70],
        ['Rotavirus',    2,  70],
        ['Pentavalent',  3,  98],
        ['OPV',          3,  98],
        ['PCV',          3,  98],
        ['IPV',          1,  98],
        ['MMR',          1, 270],
        ['MMR',          2, 365],
    ];

    // ═══════════════════════════════════════════════════════════
    //  SCHEDULE GENERATION
    // ═══════════════════════════════════════════════════════════

    public function generateFor(PublicUser $child): int
    {
        if (! $child->birth_date) {
            return 0;
        }

        $birth = Carbon::parse($child->birth_date)->startOfDay();
        $created = 0;

        foreach (self::EPI_SCHEDULE as [$vaccine, $dose, $offsetDays]) {
            $exists = VaccinationSchedule::where('public_user_id', $child->id)
                ->where('vaccine_name', $vaccine)
                ->where('dose_number', $dose)
                ->exists();

            if ($exists) {
                continue;
            }

            VaccinationSchedule::create([
                'health_unit_id' => $child->health_unit_id,
                'public_user_id' => $child->id,
                'vaccine_name' => $vaccine,
                'dose_number' => $dose,
                'due_date' => $birth->copy()->addDays($offsetDays)->toDateString(),
                'status' => 'pending',
            ]);
            $created++;
        }

        return $created;
    }

    // ═══════════════════════════════════════════════════════════
    //  MARKING AS GIVEN / MISSED
    // ═══════════════════════════════════════════════════════════

    public function markAsGiven(int $scheduleId, int $immunizationRecordId, ?string $givenDate = null): bool
    {
        $schedule = VaccinationSchedule::find($scheduleId);
        if (! $schedule) {
            return false;
        }

        $schedule->update([
            'status' => 'given',
            'given_date' => $givenDate ?? now()->toDateString(),
            'immunization_record_id' => $immunizationRecordId,
        ]);

        return true;
    }

    public function markOverdueAsMissed(int $graceDays = 30): int
    {
        return VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->where('status', 'pending')
            ->whereDate('due_date', '<', now()->subDays($graceDays)->toDateString())
            ->update(['status' => 'missed']);
    }

    // ═══════════════════════════════════════════════════════════
    //  SUMMARY HELPERS
    // ═══════════════════════════════════════════════════════════

    public function summaryFor(PublicUser $child): array
    {
        $q = VaccinationSchedule::where('public_user_id', $child->id);

        return [
            'total' => (clone $q)->count(),
            'given' => (clone $q)->where('status', 'given')->count(),
            'pending' => (clone $q)->where('status', 'pending')->count(),
            'missed' => (clone $q)->where('status', 'missed')->count(),
        ];
    }

    public function monthlySummary(int $healthUnitId, string $yearMonth): array
    {
        $start = Carbon::parse($yearMonth.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rows = VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
            ->where('health_unit_id', $healthUnitId)
            ->whereDate('due_date', '>=', $start->toDateString())
            ->whereDate('due_date', '<=', $end->toDateString())
            ->whereIn('status', ['pending', 'missed'])
            ->orderBy('due_date')
            ->get();

        $byVaccine = $rows->groupBy('vaccine_name')
            ->map(fn ($group) => [
                'vaccine' => $group->first()->vaccine_name,
                'children' => $group->pluck('public_user_id')->unique()->count(),
                'doses' => $group->count(),
            ])
            ->sortByDesc('doses')
            ->values();

        $uniqueChildren = $rows->pluck('public_user_id')->unique()->count();

        return [
            'month' => $start->format('F Y'),
            'year_month' => $yearMonth,
            'start' => $start,
            'end' => $end,
            'total_children' => $uniqueChildren,
            'total_doses' => $rows->count(),
            'by_vaccine' => $byVaccine,
            'children' => $rows,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  SESSION PATTERN
    // ═══════════════════════════════════════════════════════════

    /**
     * Given a due_date, return the RHU session date nearest to it.
     */
    public function sessionDateFor(Carbon $dueDate, ?int $healthUnitId = null): Carbon
    {
        if (! $healthUnitId) {
            return $dueDate->copy();
        }

        $rhu = HealthUnit::find($healthUnitId);
        if (! $rhu
            || $rhu->immunization_pattern === 'exact'
            || $rhu->immunization_weekday === null) {
            return $dueDate->copy();
        }

        $candidates = collect();

        foreach ([-1, 0, 1] as $monthOffset) {
            $monthStart = $dueDate->copy()->addMonths($monthOffset)->startOfMonth();
            $session = $this->sessionInMonth($monthStart, $rhu);
            if ($session) {
                $candidates->push($session);
            }
        }

        return $candidates
            ->sortBy(fn ($d) => abs($d->diffInDays($dueDate)))
            ->first() ?? $dueDate->copy();
    }

    /**
     * Find the session date within a given month based on the RHU pattern.
     */
    private function sessionInMonth(Carbon $monthStart, HealthUnit $rhu): ?Carbon
    {
        $weekday = $rhu->immunization_weekday !== null
            ? (int) $rhu->immunization_weekday
            : null;

        if ($weekday === null) {
            return null;
        }

        return match ($rhu->immunization_pattern) {
            'weekly' => $monthStart->copy()->next($weekday),
            'monthly_first' => $monthStart->copy()->firstOfMonth($weekday),
            'monthly_last' => $monthStart->copy()->lastOfMonth($weekday),
            'monthly_nth' => $rhu->immunization_week_number
                                ? $monthStart->copy()->nthOfMonth((int) $rhu->immunization_week_number, $weekday)
                                : null,
            default => null,
        };
    }

    /**
     * NEW — Compute upcoming session dates for the next N months.
     * Used by "Generate EPI Days" to create recurring calendar events.
     */
    public function upcomingSessionDates(HealthUnit $rhu, int $months = 12): array
    {
        if ($rhu->immunization_pattern === 'exact' || $rhu->immunization_weekday === null) {
            return [];
        }

        $dates = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $session = $this->sessionInMonth($cursor->copy(), $rhu);
            if ($session && ($session->isToday() || $session->isFuture())) {
                $dates[] = $session->copy();
            }
            $cursor->addMonth();
        }

        return $dates;
    }

    // ═══════════════════════════════════════════════════════════
    //  MISSED-DOSE RESCHEDULING
    // ═══════════════════════════════════════════════════════════

    public function rescheduleMissedDoses(int $graceDays = 30): int
    {
        $missed = VaccinationSchedule::whereHas('patient', fn ($q) => $q->forViewer())->with('healthUnit')
            ->where('status', 'missed')
            ->whereDate('due_date', '<', now()->subDays($graceDays)->toDateString())
            ->get();

        $count = 0;

        foreach ($missed as $dose) {
            $alreadyRescheduled = VaccinationSchedule::where('public_user_id', $dose->public_user_id)
                ->where('vaccine_name', $dose->vaccine_name)
                ->where('dose_number', $dose->dose_number)
                ->where('status', 'pending')
                ->exists();

            if ($alreadyRescheduled) {
                continue;
            }

            $originalDue = Carbon::parse($dose->due_date);
            $nextMonth = $originalDue->copy()->addMonth()->startOfMonth();

            $rhu = $dose->healthUnit;
            $newDate = null;

            if ($rhu && $rhu->immunization_pattern !== 'exact' && $rhu->immunization_weekday !== null) {
                $newDate = $this->sessionInMonth($nextMonth, $rhu);
            }

            if (! $newDate) {
                $newDate = $originalDue->copy()->addMonth();
            }

            VaccinationSchedule::create([
                'health_unit_id' => $dose->health_unit_id,
                'public_user_id' => $dose->public_user_id,
                'vaccine_name' => $dose->vaccine_name,
                'dose_number' => $dose->dose_number,
                'due_date' => $newDate->toDateString(),
                'status' => 'pending',
                'notes' => 'Rescheduled from '.$originalDue->toDateString(),
            ]);

            $count++;
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════

    public function getEpiSchedule(): array
    {
        return self::EPI_SCHEDULE;
    }
}
