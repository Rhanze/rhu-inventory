<?php

namespace App\Services;

use Carbon\Carbon;

class FamilyPlanningService
{
    /**
     * Given a method string and the date the method was accepted,
     * return the date the patient should return for resupply.
     * Returns null for permanent methods or unknown methods.
     */
    public function computeNextSupplyDate(string $method, Carbon $acceptedAt): ?Carbon
    {
        $interval = $this->resolveInterval($method);

        if ($interval === null) {
            return null;
        }

        return $acceptedAt->copy()->addWeeks($interval);
    }

    /**
     * Find the interval (in weeks) for a given method string.
     * Uses case-insensitive substring matching — order matters.
     */
    public function resolveInterval(string $method): ?int
    {
        $needle  = strtolower(trim($method));
        $map     = config('family_planning.intervals', []);

        foreach ($map as $pattern => $weeks) {
            if ($needle !== '' && str_contains($needle, $pattern)) {
                return $weeks;   // may be null for permanent methods
            }
        }

        return config('family_planning.default_interval');
    }

    /**
     * Format a friendly label like "12 weeks (DMPA injection)".
     */
    public function intervalLabel(string $method): string
    {
        $weeks = $this->resolveInterval($method);

        if ($weeks === null) {
            return 'No resupply needed';
        }

        if ($weeks >= 52) {
            $years = round($weeks / 52, 1);
            return $years . ' year' . ($years == 1 ? '' : 's');
        }

        return $weeks . ' week' . ($weeks == 1 ? '' : 's');
    }
}