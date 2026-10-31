<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiseaseMedicineMap extends Model
{
    protected $table = 'disease_medicine_map';

    protected $fillable = [
        'disease_id',
        'medicine_id',
        'priority',
        'doses_per_case',
        'notes',
    ];

    protected $casts = [
        'priority'       => 'integer',
        'doses_per_case' => 'integer',
    ];

    public function disease()
    {
        return $this->belongsTo(Disease::class, 'disease_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }
}