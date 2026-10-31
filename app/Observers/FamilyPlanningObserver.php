<?php

namespace App\Observers;

use App\Models\FamilyPlanning;
use App\Services\FamilyPlanningService;

class FamilyPlanningObserver
{
    public function __construct(private FamilyPlanningService $service)
    {
    }

    public function creating(FamilyPlanning $fp): void
    {
        // Only fill if the midwife didn't set it manually.
        if (!empty($fp->next_supply_date)) {
            return;
        }

        if (empty($fp->method) || empty($fp->date_accepted)) {
            return;
        }

        $acceptedAt = $fp->date_accepted instanceof \Carbon\Carbon
            ? $fp->date_accepted
            : \Carbon\Carbon::parse($fp->date_accepted);

        $fp->next_supply_date = $this->service->computeNextSupplyDate(
            $fp->method,
            $acceptedAt
        );
    }

    public function updating(FamilyPlanning $fp): void
    {
        // If method or date_accepted changed and next_supply_date was
        // left empty, recompute.
        if (!empty($fp->next_supply_date)) {
            return;
        }

        if ($fp->isDirty('method') || $fp->isDirty('date_accepted')) {
            $acceptedAt = $fp->date_accepted instanceof \Carbon\Carbon
                ? $fp->date_accepted
                : \Carbon\Carbon::parse($fp->date_accepted);

            $fp->next_supply_date = $this->service->computeNextSupplyDate(
                (string) $fp->method,
                $acceptedAt
            );
        }
    }
}   