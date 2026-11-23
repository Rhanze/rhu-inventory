<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\HealthUnit;
use App\Models\ImmunizationRecord;
use App\Models\ImmunizationSchedule;
use App\Models\PublicUser;
use App\Models\RhuImmunizationSchedule;
use App\Models\VaccinationSchedule;
use App\Support\ClinicalAudit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($child) {
            $current = PublicUser::whereKey($child->getKey())->lockForUpdate()->first();

            return $current ? $this->generateForLockedPatient($current) : 0;
        }, 3);
    }

    /** Called inside the patient update transaction after locking the patient. */
    public function regenerateForCorrectedBirthDate(PublicUser $child): void
    {
        $child->vaccinationSchedules()->current()->where('health_unit_id', $child->health_unit_id)
            ->whereIn('status', ['pending', 'missed'])->update(['superseded_at' => now()]);
        $this->generateForLockedPatient($child);
    }

    private function generateForLockedPatient(PublicUser $child): int
    {
        if (! $child->birth_date || ! $child->is_under_five) {
            return 0;
        }

        $birth = Carbon::parse($child->birth_date)->startOfDay();
        $definitions = ImmunizationSchedule::all()->keyBy(fn ($row) => $row->antigen_label.':'.$row->dose_number);
        $records = ImmunizationRecord::where('public_user_id', $child->id)->get()
            ->keyBy(fn ($row) => $row->antigen.':'.$row->dose_number);
        $created = 0;

        foreach (self::EPI_SCHEDULE as [$vaccine, $dose, $offsetDays]) {
            $definition = $definitions->get($vaccine.':'.$dose);
            $record = $definition ? $records->get($definition->antigen.':'.$dose) : null;
            $dueDate = $birth->copy()->addDays($offsetDays)->toDateString();
            if ($record) {
                $created += (int) $this->fulfillAdministration($child, $definition, $record, $dueDate);
                continue;
            }
            if ($this->appointmentsForDose($child, $vaccine, $dose, $definition?->antigen)->exists()) {
                continue;
            }

            VaccinationSchedule::create([
                'health_unit_id' => $child->health_unit_id,
                'public_user_id' => $child->id,
                'vaccine_name' => $vaccine,
                'dose_number' => $dose,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);
            $created++;
        }

        return $created;
    }

    // ═══════════════════════════════════════════════════════════
    //  MARKING AS GIVEN / MISSED
    // ═══════════════════════════════════════════════════════════

    /** Link one current appointment while the patient lock serializes schedule writes. */
    public function fulfillAdministration(PublicUser $child, ImmunizationSchedule $dose, ImmunizationRecord $record, string $dueDate): bool
    {
        $appointments = $this->appointmentsForDose($child, $dose->antigen_label, $dose->dose_number, $dose->antigen);
        $pending = (clone $appointments)->pending()->whereNull('immunization_record_id')->whereNull('given_date');
        $given = (clone $appointments)->given()->where(function ($query) use ($record) {
            $query->where('immunization_record_id', $record->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('immunization_record_id')
                    ->whereDate('given_date', $record->date_given->toDateString()));
        })->orderByDesc('due_date')->orderByDesc('id')->first();
        $appointment = $given ?? (clone $pending)->orderByDesc('due_date')->orderByDesc('id')->first();
        $details = [
            'status' => 'given',
            'given_date' => $record->date_given->toDateString(),
            'immunization_record_id' => $record->id,
        ];

        if ($appointment) {
            $appointment->update($details);
        } else {
            $child->vaccinationSchedules()->create($details + [
                'health_unit_id' => $child->health_unit_id,
                'vaccine_name' => $dose->antigen_label,
                'dose_number' => $dose->dose_number,
                'due_date' => $dueDate,
            ]);
        }

        // Keep old outcomes for the record and remove obsolete reminders from follow-up lists.
        $appointments->whereIn('status', ['pending', 'missed'])->update(['superseded_at' => now()]);

        return $appointment === null;
    }

    private function appointmentsForDose(PublicUser $child, string $label, int $dose, ?string $antigen): HasMany
    {
        $aliases = array_map(fn ($name) => mb_strtolower(trim($name)), array_filter([$label, $antigen]));

        return $child->vaccinationSchedules()->current()->where('health_unit_id', $child->health_unit_id)
            ->whereIn(DB::raw('LOWER(TRIM(vaccine_name))'), $aliases)->where('dose_number', $dose);
    }

    // ═══════════════════════════════════════════════════════════
    //  SUMMARY HELPERS
    // ═══════════════════════════════════════════════════════════

    public function summarizeAppointments(Collection $appointments): array
    {
        $doseKey = $this->doseKey();
        $key = fn (VaccinationSchedule $row) => $row->public_user_id.':'.$doseKey($row->vaccine_name, $row->dose_number);
        $current = $appointments->whereNull('superseded_at');
        $given = $current->where('status', 'given')->keyBy($key);

        return [
            'total' => $current->unique($key)->count(),
            'given' => $given->count(),
            'pending' => $current->where('status', 'pending')->keyBy($key)->diffKeys($given)->count(),
            'missed' => $appointments->where('status', 'missed')->count(),
        ];
    }

    public function countAdministrations(int $healthUnitId, Carbon $from, Carbon $through): int
    {
        $records = ImmunizationRecord::whereHas('patient', fn ($q) => $q->forViewer())
            ->where('health_unit_id', $healthUnitId);
        $count = (clone $records)->whereBetween('date_given', [$from->toDateString(), $through->toDateString()])->count();
        $legacy = VaccinationSchedule::current()->given()->whereHas('patient', fn ($q) => $q->forViewer())
            ->where('health_unit_id', $healthUnitId)->whereNull('immunization_record_id')
            ->whereDate('given_date', '<=', $through)->orderBy('given_date')->orderBy('id')->get();
        if ($legacy->isEmpty()) {
            return $count;
        }

        $doseKey = $this->doseKey();
        $known = $records->whereIn('public_user_id', $legacy->pluck('public_user_id')->unique()->values())
            ->get(['public_user_id', 'antigen', 'dose_number'])
            ->keyBy(fn ($record) => $record->public_user_id.':'.$doseKey($record->antigen, $record->dose_number));

        // An explicitly dated legacy record is evidence only when there is no administration record for that dose.
        return $count + $legacy->unique(fn ($row) => $row->public_user_id.':'.$doseKey($row->vaccine_name, $row->dose_number))
            ->filter(fn ($row) => $row->given_date->gte($from)
                && ! $known->has($row->public_user_id.':'.$doseKey($row->vaccine_name, $row->dose_number)))->count();
    }

    public function monthlySummary(int $healthUnitId, string $yearMonth): array
    {
        $start = Carbon::parse($yearMonth.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rows = VaccinationSchedule::current()->whereHas('patient', fn ($q) => $q->forViewer())->with('patient')
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
     * Return the first area session, or RHU default, on or after the dose due date.
     */
    public function sessionDateFor(Carbon $dueDate, ?int $healthUnitId = null, ?string $barangay = null): Carbon
    {
        if (! $healthUnitId) {
            return $dueDate->copy();
        }

        $rhu = HealthUnit::find($healthUnitId);
        if ($rhu && trim((string) $barangay) !== '') {
            $area = RhuImmunizationSchedule::active()->forHealthUnit($healthUnitId)
                ->whereRaw('LOWER(TRIM(area_label)) = ?', [mb_strtolower(trim($barangay))])->first();
            if ($area) {
                $rhu->immunization_pattern = $area->pattern;
                $rhu->immunization_weekday = $area->weekday;
                $rhu->immunization_week_number = $area->week_number;
            }
        }
        if (! $rhu
            || $rhu->immunization_pattern === 'exact'
            || $rhu->immunization_weekday === null) {
            return $dueDate->copy();
        }

        if ($rhu->immunization_pattern === 'weekly') {
            $session = $dueDate->copy()->startOfDay();
            return $session->dayOfWeek === (int) $rhu->immunization_weekday
                ? $session : $session->next((int) $rhu->immunization_weekday);
        }

        $monthStart = $dueDate->copy()->startOfMonth();
        for ($monthOffset = 0; $monthOffset < 2; $monthOffset++) {
            $session = $this->sessionInMonth($monthStart, $rhu);
            if ($session && $session->greaterThanOrEqualTo($dueDate->copy()->startOfDay())) {
                return $session;
            }
            $monthStart->addMonth();
        }

        return $dueDate->copy();
    }

    public function appointmentSessionDate(VaccinationSchedule $appointment, ?string $barangay): Carbon
    {
        return $this->sessionDateFor($appointment->due_date, $appointment->health_unit_id,
            in_array($appointment->status, ['pending', 'missed'], true) ? $barangay : null);
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
            'weekly' => $monthStart->copy()->firstOfMonth($weekday),
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
            while ($session && $session->month === $cursor->month) {
                if ($session->greaterThanOrEqualTo(today())) {
                    $dates[] = $session->copy();
                }
                if ($rhu->immunization_pattern !== 'weekly') {
                    break;
                }
                $session->addWeek();
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
        return $this->processAppointments($graceDays, false, true)['rescheduled'];
    }

    public function maintainSchedule(int $graceDays = 30): array
    {
        return $this->processAppointments($graceDays, true, false);
    }

    private function processAppointments(int $graceDays, bool $markMissed, bool $forViewer): array
    {
        $cutoff = today()->subDays($graceDays)->toDateString();
        $sessionBase = today();
        $doseKey = $this->doseKey();
        $totals = ['missed' => 0, 'rescheduled' => 0];

        PublicUser::query()->when($forViewer, fn ($query) => $query->forViewer())
            ->whereHas('vaccinationSchedules', fn ($query) => $query->current()
                ->whereColumn('vaccination_schedules.health_unit_id', 'public_users.health_unit_id')
                ->where('due_date', '<', $cutoff)
                ->whereIn('status', $markMissed ? ['pending', 'missed'] : ['missed']))
            ->select('id')->eachById(function (PublicUser $candidate) use ($forViewer, $markMissed, $cutoff, $sessionBase, $doseKey, &$totals) {
                [$missed, $rescheduled] = DB::transaction(function () use ($candidate, $forViewer, $markMissed, $cutoff, $sessionBase, $doseKey) {
                    // All schedule writers lock the patient before reading or changing appointments.
                    $patient = PublicUser::query()->when($forViewer, fn ($query) => $query->forViewer())
                        ->whereKey($candidate->id)->lockForUpdate()->first();
                    if (! $patient) {
                        return [0, 0];
                    }
                    $appointments = $patient->vaccinationSchedules()->current()->where('health_unit_id', $patient->health_unit_id)
                        ->orderByDesc('due_date')->orderByDesc('id')->lockForUpdate()->get();
                    $completed = $patient->immunizationRecords()->get(['antigen', 'dose_number'])
                        ->keyBy(fn ($record) => $doseKey($record->antigen, $record->dose_number));
                    $missed = 0;
                    if ($markMissed) {
                        foreach ($appointments as $appointment) {
                            if ($appointment->status === 'pending'
                                && ! $completed->has($doseKey($appointment->vaccine_name, $appointment->dose_number))
                                && $appointment->due_date->toDateString() < $cutoff
                                && $this->appointmentSessionDate($appointment, $patient->barangay)->toDateString() < $cutoff) {
                                $appointment->update(['status' => 'missed']);
                                $this->auditMaintenance('Mark missed', $appointment);
                                $missed++;
                            }
                        }
                    }

                    $replaced = $appointments->pluck('rescheduled_from_id')->filter()->flip();
                    $rescheduled = 0;
                    foreach ($appointments->groupBy(fn ($row) => $doseKey($row->vaccine_name, $row->dose_number)) as $key => $rows) {
                        $latest = $rows->first();
                        if ($completed->has($key) || $replaced->has($latest->id) || $latest->status !== 'missed'
                            || $latest->due_date->toDateString() >= $cutoff
                            || $this->appointmentSessionDate($latest, $patient->barangay)->gte($sessionBase)
                            || $rows->contains(fn ($row) => in_array($row->status, ['pending', 'given', 'skipped'], true))) {
                            continue;
                        }
                        $replacement = $patient->vaccinationSchedules()->create([
                            'health_unit_id' => $patient->health_unit_id,
                            'vaccine_name' => $latest->vaccine_name,
                            'dose_number' => $latest->dose_number,
                            'due_date' => $this->sessionDateFor($sessionBase, $patient->health_unit_id, $patient->barangay)->toDateString(),
                            'status' => 'pending',
                            'rescheduled_from_id' => $latest->id,
                            'notes' => 'Rescheduled from '.$latest->due_date->toDateString(),
                        ]);
                        $this->auditMaintenance('Reschedule', $replacement);
                        $rescheduled++;
                    }

                    return [$missed, $rescheduled];
                }, 3);
                $totals['missed'] += $missed;
                $totals['rescheduled'] += $rescheduled;
            });

        return $totals;
    }

    private function doseKey(): \Closure
    {
        $aliases = [];
        foreach (ImmunizationSchedule::get(['antigen', 'antigen_label', 'dose_number']) as $definition) {
            $label = mb_strtolower(trim($definition->antigen_label)).':'.$definition->dose_number;
            $aliases[$label] = mb_strtolower(trim($definition->antigen)).':'.$definition->dose_number;
        }

        return static function (string $name, int $dose) use ($aliases): string {
            $key = mb_strtolower(trim($name)).':'.$dose;

            return $aliases[$key] ?? $key;
        };
    }

    private function auditMaintenance(string $action, VaccinationSchedule $appointment): void
    {
        ActivityLog::create([
            'health_unit_id' => $appointment->health_unit_id,
            'user_id' => null,
            'module' => ClinicalAudit::MODULE,
            'action' => $action,
            'description' => 'vaccination_schedule #'.$appointment->id.' (schedule maintenance)',
            'created_at' => now(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════

    public function getEpiSchedule(): array
    {
        return self::EPI_SCHEDULE;
    }
}
