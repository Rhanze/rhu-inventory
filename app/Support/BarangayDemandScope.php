<?php

namespace App\Support;

use App\Models\DispensingRecord;
use App\Models\MidwifeBarangay;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BarangayDemandScope
{
    /** Assigned areas for midwives; assignment and historical dispensing areas for RHU staff. */
    public static function options(int $healthUnitId, ?int $midwifeId = null): Collection
    {
        $areas = MidwifeBarangay::whereHas('user', fn ($q) => $q->where('health_unit_id', $healthUnitId)->where('role', 'midwife'))
            ->when($midwifeId !== null, fn ($q) => $q->where('user_id', $midwifeId))
            ->orderByDesc('is_primary')->orderBy('barangay')->pluck('barangay');
        if ($midwifeId === null) {
            $areas = $areas->merge(DispensingRecord::where('health_unit_id', $healthUnitId)
                ->whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay'));
        }

        return $areas->map(fn ($area) => trim($area))->filter()->unique(fn ($area) => mb_strtolower($area))->values();
    }

    public static function validateForViewer(int $healthUnitId, ?string $area, string $field = 'barangay', bool $required = false): ?string
    {
        $area = trim((string) $area);
        if (session('role') !== 'midwife') {
            return $area !== '' ? $area : null;
        }
        $area = preg_replace('/\s+/u', ' ', $area);
        if ($area === '' && ! $required) {
            return null;
        }
        $options = self::options($healthUnitId, (int) session('user_id'));
        if ($options->isEmpty()) {
            throw ValidationException::withMessages([$field => 'Your account has no assigned barangays. Ask your RHU administrator to assign an area.']);
        }
        $match = $options->first(fn ($option) => mb_strtolower(preg_replace('/\s+/u', ' ', $option)) === mb_strtolower($area));
        if ($match === null) {
            throw ValidationException::withMessages([$field => 'Choose one of your assigned barangays.']);
        }

        return $match;
    }
}
