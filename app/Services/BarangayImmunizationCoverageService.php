<?php

namespace App\Services;

use App\Models\PublicUser;
use Illuminate\Support\Facades\Schema;

class BarangayImmunizationCoverageService
{
    /** Coverage means at least one recorded dose among the same registered under-five cohort. */
    public function forHealthUnit(int $healthUnitId): array
    {
        $cohort = PublicUser::forViewer()->where('health_unit_id', $healthUnitId)
            ->whereDate('birth_date', '>', today()->subYearsNoOverflow(5)->toDateString())
            ->whereDate('birth_date', '<=', today()->toDateString())
            ->whereNotNull('barangay')->whereRaw("TRIM(barangay) != ''");
        $totals = (clone $cohort)->select('barangay')->selectRaw('COUNT(*) as total')
            ->groupBy('barangay')->orderByDesc('total')->orderBy('barangay')->pluck('total', 'barangay');
        $immunized = collect();
        if (Schema::hasTable('immunization_records')) {
            $immunized = (clone $cohort)->whereHas('immunizationRecords', fn ($records) => $records
                ->where('health_unit_id', $healthUnitId)->whereDate('date_given', '<=', today()->toDateString()))
                ->select('barangay')->selectRaw('COUNT(*) as total')->groupBy('barangay')->pluck('total', 'barangay');
        }

        $counts = [];
        $percentages = [];
        foreach ($totals as $barangay => $total) {
            $count = (int) ($immunized[$barangay] ?? 0);
            $counts[] = $count;
            $percentages[] = $total > 0 ? (int) round($count / $total * 100) : 0;
        }

        return ['labels' => $totals->keys()->all(), 'immunized' => $counts,
            'totals' => $totals->map(fn ($value) => (int) $value)->values()->all(), 'percent' => $percentages];
    }
}
