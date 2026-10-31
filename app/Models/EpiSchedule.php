<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpiSchedule extends Model
{
    protected $table = 'epi_schedule';
    public $timestamps = false;

    protected $fillable = [
        'vaccine', 'age_weeks', 'age_months', 'dose_number', 'is_fic_requirement',
    ];

    protected $casts = [
        'is_fic_requirement' => 'boolean',
    ];
}