<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhuImmunizationSchedule extends Model
{
    protected $table = 'rhu_immunization_schedules';

    protected $fillable = [
        'health_unit_id',
        'area_label',
        'pattern',
        'weekday',
        'week_number',
        'is_active',
    ];

    protected $casts = [
        'weekday'     => 'integer',
        'week_number' => 'integer',
        'is_active'   => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForHealthUnit($q, $healthUnitId)
    {
        return $q->where('health_unit_id', $healthUnitId);
    }

    public function getPatternLabelAttribute(): string
    {
        $weekdays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $day = $weekdays[$this->weekday] ?? '?';

        return match ($this->pattern) {
            'weekly'        => 'Every ' . $day,
            'monthly_first' => 'First ' . $day . ' of the month',
            'monthly_last'  => 'Last ' . $day . ' of the month',
            'monthly_nth'   => ($this->week_number ?? '?') . 'th ' . $day . ' of the month',
            default         => ucfirst(str_replace('_', ' ', $this->pattern)),
        };
    }
}