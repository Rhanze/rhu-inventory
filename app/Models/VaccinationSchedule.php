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
        'notes',
    ];

    protected $casts = [
        'due_date'    => 'date:Y-m-d',
        'given_date'  => 'date:Y-m-d',
        'dose_number' => 'integer',
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

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeMissed($q)  { return $q->where('status', 'missed'); }
    public function scopeGiven($q)   { return $q->where('status', 'given'); }

    public function scopeOverdue($q)
    {
        return $q->where('status', 'pending')
                 ->whereDate('due_date', '<', now()->toDateString());
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'given'   => 'Given',
            'missed'  => 'Missed',
            'skipped' => 'Skipped',
            default   => $this->is_overdue ? 'Overdue' : 'Pending',
        };
    }
}