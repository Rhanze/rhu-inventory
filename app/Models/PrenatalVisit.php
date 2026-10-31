<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrenatalVisit extends Model
{
    protected $table = 'prenatal_visits';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'patient_id',
        'health_unit_id',
        'visit_date',
        'next_visit_date',
        'trimester',
        'blood_pressure',
        'weight',
        'fundal_height',
        'fetal_heart_tone',
        'tt_dose',
        'remarks',
    ];

    protected $casts = [
        'visit_date'       => 'date:Y-m-d',
        'next_visit_date'  => 'date:Y-m-d',
        'weight'           => 'decimal:2',
        'fundal_height'    => 'decimal:2',
    ];

    public function patient()
    {
        return $this->belongsTo(PublicUser::class, 'patient_id');
    }

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }
}