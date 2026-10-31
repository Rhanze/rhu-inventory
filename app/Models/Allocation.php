<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Allocation extends Model
{
    use SoftDeletes;

    protected $table = 'allocations';

    protected $fillable = [
        'medicine_id',
        'inventory_id',
        'health_unit_id',
        'destination',
        'quantity_allocated',
        'date_allocated',
        'allocated_by',
        'recipient_user_id',         // ← NEW
        'destination_health_unit_id',
        'status',
        'remarks',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'quantity_allocated' => 'integer',
        'date_allocated'     => 'date',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'health_unit_id');
    }

    public function destinationHealthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'destination_health_unit_id');
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function stockLogs()
    {
        return $this->hasMany(StockLog::class, 'allocation_id');
    }

    // ========== HELPERS ==========

    public function getDestinationDisplayAttribute(): string
    {
        if ($this->destination_health_unit_id) {
            return ($this->destinationHealthUnit->name ?? 'Unknown RHU') . ' (RHU)';
        }

        return $this->destination ?: '—';
    }

    // ========== SCOPES ==========

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInLastMonths($query, $months)
    {
        return $query->where('created_at', '>=', now()->subMonths($months));
    }
}