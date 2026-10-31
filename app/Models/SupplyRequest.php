<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplyRequest extends Model
{
    protected $table = 'supply_requests';

    protected $fillable = [
        'health_unit_id',
        'medicine_id',
        'quantity',
        'reason',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'remarks',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}