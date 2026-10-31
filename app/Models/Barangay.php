<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    protected $table = 'barangays';

    public $timestamps = false;

    protected $fillable = [
        'health_unit_id',
        'barangay_name',
    ];

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'health_unit_id');
    }
}