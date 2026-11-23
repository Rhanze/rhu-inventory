<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaccinationSchedule extends Model
{
    protected $table = 'vaccination_schedules';

    protected $fillable = [
        'health_unit_id',
        'public_user_id',
        'vaccine_name',
        'dose_number',
        'due_date',
        'given_date',
        'status',
        'immunization_record_id',
        'rescheduled_from_id',
        'superseded_at',
        'notes',
    ];

    protected $casts = [
        'due_date'    => 'date:Y-m-d',
        'given_date'  => 'date:Y-m-d',
        'dose_number' => 'integer',
        'superseded_at' => 'datetime',
    ];

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }

    public function patient()
    {
        return $this->belongsTo(PublicUser::class, 'public_user_id');
    }

    public function immunizationRecord()
    {
        return $this->belongsTo(ImmunizationRecord::class, 'immunization_record_id');
    }

    public function replacement()
    {
        return $this->hasOne(self::class, 'rescheduled_from_id');
    }

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeMissed($q)  { return $q->where('status', 'missed'); }
    public function scopeGiven($q)   { return $q->where('status', 'given'); }

    public function scopeCurrent($query)
    {
        return $query->whereNull('vaccination_schedules.superseded_at');
    }

    public function scopeOverdue($q)
    {
        return $q->current()->where('status', 'pending')
                 ->whereDate('due_date', '<', now()->toDateString());
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->superseded_at === null && $this->status === 'pending'
            && ($this->session_date ?? $this->due_date)->lessThan(today());
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->superseded_at !== null) {
            return $this->status === 'missed' ? 'Missed (superseded)' : 'Superseded';
        }

        return match ($this->status) {
            'given'   => 'Given',
            'missed'  => 'Missed',
            'skipped' => 'Skipped',
            default   => $this->is_overdue ? 'Overdue' : 'Pending',
        };
    }
}
