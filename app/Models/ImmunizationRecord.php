<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImmunizationRecord extends Model
{
    protected $table = 'immunization_records';

    protected $fillable = [
        'public_user_id',
        'health_unit_id',
        'antigen',
        'dose_number',
        'date_given',
        'inventory_id',
        'batch_no',
        'administered_by',
        'site',
        'route',
        'remarks',
    ];

    protected $casts = [
        'date_given'  => 'date:Y-m-d',
        'dose_number' => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(PublicUser::class, 'public_user_id');
    }

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }

    public function administeredBy()
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function vaccinationSchedule()
    {
        return $this->hasOne(VaccinationSchedule::class, 'immunization_record_id');
    }
}