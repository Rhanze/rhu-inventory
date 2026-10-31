<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Immunization extends Model
{
    protected $table = 'immunizations';
    public $timestamps = false;

    protected $fillable = [
        'patient_id', 'health_unit_id', 'vaccine',
        'dose_number', 'date_given', 'given_by', 'batch_no', 'remarks',
    ];

    protected $casts = [
        'date_given'  => 'date',
        'dose_number' => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(PublicUser::class, 'patient_id');
    }

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'health_unit_id');
    }
}