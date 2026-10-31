<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyPlanning extends Model
{
    protected $table = 'family_planning';

    public $timestamps = true;
    const UPDATED_AT = null;   // table has only created_at

    protected $fillable = [
        'patient_id',
        'health_unit_id',
        'method',
        'date_accepted',
        'next_supply_date',
        'remarks',
    ];

    protected $casts = [
        'date_accepted'    => 'date:Y-m-d',
        'next_supply_date' => 'date:Y-m-d',
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