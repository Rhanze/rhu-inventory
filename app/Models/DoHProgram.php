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
        $earliest = $date->copy()->startOfDay()->subDays(max(1, (int) $this->duration_days) - 1);

        return $this->startsBetween($earliest, $date) !== [];
    }

    /** Return occurrence starts without rolling invalid dates into another month. */
    public function startsBetween(Carbon $from, Carbon $through): array
    {
        if (! $this->is_active) {
            return [];
        }

        $dates = [];
        for ($date = $from->copy()->startOfDay(); $date->lte($through); $date->addDay()) {
            $matches = match ($this->recurrence) {
                'yearly' => (int) $this->month === $date->month && (int) $this->day === $date->day,
                'monthly' => (int) $this->day === $date->day,
                'weekly' => (int) $this->weekday === $date->dayOfWeek,
                default => false,
            };
            if ($matches) {
                $dates[] = $date->copy();
            }
        }

        return $dates;
    }
}
