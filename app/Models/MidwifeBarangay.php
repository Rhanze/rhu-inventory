<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MidwifeBarangay extends Model
{
    protected $table = 'midwife_barangay';

    protected $fillable = ['user_id', 'barangay', 'is_primary'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}