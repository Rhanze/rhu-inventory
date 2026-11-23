<?php

namespace App\Support;

use App\Helpers\ActivityLogger;
use App\Models\PublicUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClinicalAudit
{
    public const MODULE = 'Clinical Audit';

    public static function record(string $action, string $resource, ?int $id = null): void
    {
        ActivityLogger::log(self::MODULE, $action, $resource.($id === null ? '' : ' #'.$id));
    }

    public static function archiveVisible(): bool
    {
        return session('role') === 'admin' && (bool) session('health_unit_id');
    }

    public static function scopedVisits(string $model): Builder
    {
        return $model::whereHas('patient', fn ($query) => $query->forViewer())
            ->where('health_unit_id', session('health_unit_id'));
    }

    public static function createVisit(string $model, array $attributes): PublicUser
    {
        return DB::transaction(function () use ($model, $attributes) {
            $patient = PublicUser::forViewer()->where('health_unit_id', $attributes['health_unit_id'])
                ->lockForUpdate()->findOrFail($attributes['patient_id']);
            foreach (['visit_date', 'delivery_date', 'date_accepted'] as $field) {
                if (isset($attributes[$field]) && $patient->birth_date
                    && Carbon::parse($attributes[$field])->startOfDay()->lt($patient->birth_date)) {
                    throw ValidationException::withMessages([$field => 'Choose a date on or after the patient birth date.']);
                }
            }
            $record = $model::create($attributes);
            self::record('Create', $record->getTable(), $record->getKey());

            return $patient;
        }, 3);
    }

    public static function setVoided(string $model, int $id, bool $void): void
    {
        abort_unless($void || self::archiveVisible(), 403);

        DB::transaction(function () use ($model, $id, $void) {
            /** @var Model $record */
            $record = self::scopedVisits($model)->when(! $void, fn ($query) => $query->onlyTrashed())
                ->lockForUpdate()->findOrFail($id);
            if ($void) {
                $record->delete();
            } else {
                $record->restore();
            }
            self::record($void ? 'Void' : 'Restore', $record->getTable(), $id);
        }, 3);
    }
}
