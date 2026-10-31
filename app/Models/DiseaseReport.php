<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiseaseReport extends Model
{
    protected $fillable = [
        'period_start', 'period_end', 'is_complete', 'outbreak_closed_at', 'outbreak_closed_by', 'outbreak_closing_note',
        'disease_id',
        'health_unit_id',
        'report_date',
        'period_type',
        'case_count',
        'is_outbreak',
        'remarks',
        'reported_by',
    ];

    protected $casts = [
        'period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'is_complete' => 'boolean', 'outbreak_closed_at' => 'datetime',
        'report_date' => 'date:Y-m-d',
        'case_count' => 'integer',
        'is_outbreak' => 'boolean',
    ];

    public function disease()
    {
        return $this->belongsTo(Disease::class);
    }

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }

    public function reportedByUser()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function scopeConfirmedPeriods($query, string $type = 'weekly')
    {
        return $query->where('period_type', $type)->where('is_complete', true)
            ->whereNotNull('period_start')->whereNotNull('period_end')->whereDate('period_end', '<', today());
    }

    public function scopeRecent($query, $days = 90)
    {
        return $query->where('report_date', '>=', now()->subDays($days));
    }
}
