<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImmunizationSchedule extends Model
{
    protected $table = 'immunization_schedule';

    protected $fillable = [
        'antigen', 'antigen_label', 'dose_number',
        'min_age_days', 'max_age_days', 'recommended_age_label',
        'interval_days', 'is_required_for_fic', 'is_required_for_cbi',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_required_for_fic' => 'boolean',
        'is_required_for_cbi' => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('antigen')->orderBy('dose_number');
    }
}