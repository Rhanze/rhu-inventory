<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostpartumVisit extends Model
{
    protected $table = 'postpartum_visits';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'patient_id',
        'health_unit_id',
        'delivery_date',
        'place_of_delivery',
        'attended_by',
        'visit_date',
        'next_visit_date',
        'visit_number',
        'remarks',
    ];

    protected $casts = [
        'delivery_date'    => 'date:Y-m-d',
        'visit_date'       => 'date:Y-m-d',
        'next_visit_date'  => 'date:Y-m-d',
        'visit_number'     => 'integer',
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