<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DoHProgram extends Model
{
    protected $table = 'doh_programs';

    protected $fillable = [
        'name', 'description', 'color',
        'recurrence', 'month', 'day', 'weekday', 'duration_days',
        'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'month'         => 'integer',
        'day'           => 'integer',
        'weekday'       => 'integer',
        'duration_days' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Does this program occur on the given date?
     * Multi-day programs: check if the date falls inside the window.
     */
    public function occursOn(Carbon $date): bool
    {
        if (!$this->is_active) return false;

        switch ($this->recurrence) {
            case 'yearly':
                if ((int) $this->month !== $date->month) return false;
                $start = Carbon::create($date->year, $this->month, $this->day)->startOfDay();
                $end   = $start->copy()->addDays(max(1, (int) $this->duration_days) - 1);
                return $date->between($start, $end);

            case 'monthly':
                return (int) $this->day === $date->day;

            case 'weekly':
                return (int) $this->weekday === $date->dayOfWeek;

            default:
                return false;
        }
    }
}