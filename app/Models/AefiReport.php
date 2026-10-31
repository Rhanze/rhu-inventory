<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AefiReport extends Model
{
    protected $fillable = [
        'public_user_id', 'health_unit_id', 'immunization_record_id',
        'antigen', 'date_onset', 'symptoms', 'severity',
        'reported_at', 'reported_by', 'outcome',
    ];

    protected $casts = [
        'date_onset'  => 'date',
        'reported_at' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(PublicUser::class, 'public_user_id');
    }

    public function immunizationRecord()
    {
        return $this->belongsTo(ImmunizationRecord::class, 'immunization_record_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function getSeverityBadgeAttribute(): array
    {
        return match ($this->severity) {
            'fatal'   => ['bg' => '#7f1d1d', 'color' => '#ffffff', 'label' => 'Fatal'],
            'serious' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => 'Serious'],
            default   => ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => 'Minor'],
        };
    }
}