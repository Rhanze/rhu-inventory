<?php

namespace App\Services;

use App\Models\PublicUser;
use App\Models\ImmunizationRecord;
use App\Models\ImmunizationSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ImmunizationService
{
    /**
     * Grace period (days) after the earliest age before a dose becomes "overdue".
     * A child is not late during this window — the midwife still has time to reach them.
     */
    public const GRACE_DAYS = 28;

    // ═══════════════════════════════════════════════
    //  PUBLIC API
    // ═══════════════════════════════════════════════

    public function getScheduleForChild(PublicUser $child): array
    {
        $ageDays = $this->ageInDays($child);

        if ($ageDays === null) {
            return [
                'overall'  => 'unknown',
                'age_days' => null,
                'doses'    => collect(),
            ];
        }

        $given = ImmunizationRecord::where('public_user_id', $child->id)
            ->get()
            ->keyBy(fn($r) => $r->antigen . ':' . $r->dose_number);

        $doses = ImmunizationSchedule::active()->ordered()
            ->get()
            ->map(function (ImmunizationSchedule $row) use ($ageDays, $given) {
                $key    = $row->antigen . ':' . $row->dose_number;
                $record = $given->get($key);
                $status = $this->computeStatus($row, $ageDays, (bool) $record);

                return [
                    'schedule_id'           => $row->id,
                    'antigen'               => $row->antigen,
                    'antigen_label'         => $row->antigen_label,
                    'dose_number'           => $row->dose_number,
                    'recommended_age_label' => $row->recommended_age_label,
                    'min_age_days'          => $row->min_age_days,
                    'max_age_days'          => $row->max_age_days,
                    'is_fic'                => (bool) $row->is_required_for_fic,
                    'is_cbi'                => (bool) $row->is_required_for_cbi,
                    'status'                => $status,
                    'date_given'            => $record?->date_given?->toDateString(),
                    'record_id'             => $record?->id,
                    'days_until_due'        => $status === 'not_due'
                        ? max(0, $row->min_age_days - $ageDays)
                        : null,
                    'days_overdue'          => $status === 'overdue'
                        ? max(0, $ageDays - ($row->min_age_days + self::GRACE_DAYS))
                        : null,
                ];
            });

        return [
            'overall'  => $this->computeOverall($doses),
            'age_days' => $ageDays,
            'doses'    => $doses,
        ];
    }

    public function getBulkSchedulesForChildren($children): array
    {
        $childIds = collect($children)->pluck('id')->all();
        if (empty($childIds)) {
            return [];
        }

        $schedule = ImmunizationSchedule::active()->ordered()->get();

        $records = ImmunizationRecord::whereIn('public_user_id', $childIds)
            ->get()
            ->groupBy('public_user_id');

        $out = [];

        foreach ($children as $child) {
            $ageDays = $this->ageInDays($child);

            if ($ageDays === null) {
                $out[$child->id] = ['overall' => 'unknown', 'age_days' => null, 'doses' => []];
                continue;
            }

            $childRecords = ($records[$child->id] ?? collect())
                ->keyBy(fn($r) => $r->antigen . ':' . $r->dose_number);

            $doses = [];
            foreach ($schedule as $row) {
                $key    = $row->antigen . ':' . $row->dose_number;
                $record = $childRecords->get($key);
                $status = $this->computeStatus($row, $ageDays, (bool) $record);

                $doses[$key] = [
                    'status'     => $status,
                    'date_given' => $record?->date_given?->toDateString(),
                    'record_id'  => $record?->id,
                    'overdue_by' => $status === 'overdue'
                        ? max(0, $ageDays - ($row->min_age_days + self::GRACE_DAYS))
                        : 0,
                ];
            }

            $out[$child->id] = [
                'overall'  => $this->computeOverall(
                    collect($doses)->map(fn($d) => ['status' => $d['status']])
                ),
                'age_days' => $ageDays,
                'doses'    => $doses,
            ];
        }

        return $out;
    }

    public function isFullyImmunized(PublicUser $child): bool
    {
        return $this->hasAllRequiredDoses($child, 'is_required_for_fic');
    }

    public function isCompleteBasicImmunization(PublicUser $child): bool
    {
        return $this->hasAllRequiredDoses($child, 'is_required_for_cbi');
    }

    public function getDefaulters(int $healthUnitId, int $limit = 300): Collection
    {
        // FIXED: `birth_date` (not `date_of_birth`), and order by it desc
        $children = PublicUser::where('health_unit_id', $healthUnitId)
            ->whereNotNull('birth_date')
            ->orderByDesc('birth_date')          // youngest first
            ->limit($limit)
            ->get();

        return $children
            ->map(function (PublicUser $child) {
                $result = $this->getScheduleForChild($child);

                if ($result['overall'] === 'unknown') {
                    return null;
                }

                $overdue = $result['doses']
                    ->where('status', 'overdue')
                    ->where('is_fic', true);

                if ($overdue->isEmpty()) {
                    return null;
                }

                return [
                    'child'    => $child,
                    'age_days' => $result['age_days'],
                    'overdue'  => $overdue,
                    'next'     => $overdue->sortByDesc('days_overdue')->first(),
                ];
            })
            ->filter()
            ->sortByDesc(fn($d) => $d['next']['days_overdue'] ?? 0)
            ->values();
    }

    public function getFicSummary(int $healthUnitId): array
    {
        // FIXED: `birth_date`
        $children = PublicUser::where('health_unit_id', $healthUnitId)
            ->whereNotNull('birth_date')
            ->get();

        $fic   = 0;
        $cbi   = 0;
        $total = 0;

        foreach ($children as $child) {
            $ageDays = $this->ageInDays($child);
            if ($ageDays === null || $ageDays > 730) {   // under 2 years only
                continue;
            }
            $total++;

            if ($this->isFullyImmunized($child))             $fic++;
            if ($this->isCompleteBasicImmunization($child))  $cbi++;
        }

        return [
            'total_under_2' => $total,
            'fic_count'     => $fic,
            'cbi_count'     => $cbi,
            'fic_percent'   => $total > 0 ? round(($fic / $total) * 100, 1) : 0,
            'cbi_percent'   => $total > 0 ? round(($cbi / $total) * 100, 1) : 0,
        ];
    }

    // ═══════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════

    /**
     * FIXED: prefer `birth_date` (the real column on public_users).
     * Keeps fallbacks so it doesn't break if the schema shifts later.
     */
    public function dateOfBirth(PublicUser $child): ?Carbon
    {
        $raw = $child->birth_date
            ?? $child->date_of_birth
            ?? $child->dob
            ?? null;

        if (!$raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function ageInDays(PublicUser $child): ?int
    {
        $dob = $this->dateOfBirth($child);
        return $dob ? (int) $dob->diffInDays(today(), false) : null;
    }

    private function computeStatus(ImmunizationSchedule $row, int $ageDays, bool $hasRecord): string
    {
        if ($hasRecord) {
            return 'completed';
        }

        if ($ageDays < $row->min_age_days) {
            return 'not_due';
        }

        if ($row->max_age_days && $ageDays > $row->max_age_days) {
            return 'out_of_window';
        }

        if ($ageDays <= $row->min_age_days + self::GRACE_DAYS) {
            return 'due';
        }

        return 'overdue';
    }

    private function computeOverall(Collection $doses): string
    {
        if ($doses->isEmpty()) {
            return 'not_started';
        }
        if ($doses->contains(fn($d) => ($d['status'] ?? null) === 'overdue')) {
            return 'has_overdue';
        }
        if ($doses->contains(fn($d) => ($d['status'] ?? null) === 'due')) {
            return 'has_due';
        }
        if ($doses->every(fn($d) => ($d['status'] ?? null) === 'completed')) {
            return 'up_to_date';
        }
        return 'not_started';
    }

    private function hasAllRequiredDoses(PublicUser $child, string $flag): bool
    {
        $required = ImmunizationSchedule::active()->where($flag, true)->get();

        if ($required->isEmpty()) {
            return false;
        }

        $given = ImmunizationRecord::where('public_user_id', $child->id)
            ->get()
            ->keyBy(fn($r) => $r->antigen . ':' . $r->dose_number);

        foreach ($required as $row) {
            if (!$given->has($row->antigen . ':' . $row->dose_number)) {
                return false;
            }
        }

        return true;
    }
}