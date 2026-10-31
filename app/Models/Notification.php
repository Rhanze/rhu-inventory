<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = [
        'health_unit_id',
        'user_id',          // null = RHU-shared, set = personal
        'inventory_id',
        'transfer_id',
        'allocation_id',    // ← NEW — links to supply request Approve/Reject
        'type',
        'link',
        'title',
        'message',
        'severity',
        'is_read',
        'archived_at',
        'created_at',
    ];

    protected $casts = [
        'is_read'     => 'boolean',
        'created_at'  => 'datetime',
        'archived_at' => 'datetime',
    ];

    // ─── Relationships ───

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function transfer()
    {
        return $this->belongsTo(TransferRequest::class, 'transfer_id');
    }

    public function allocation()
    {
        return $this->belongsTo(\App\Models\Allocation::class, 'allocation_id');
    }

    // ─── Scopes ───

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForHealthUnit($query, $healthUnitId)
    {
        return $query->where('health_unit_id', $healthUnitId)
                     ->orWhereNull('health_unit_id');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeShared($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isPersonal(): bool
    {
        return $this->user_id !== null;
    }

    public function isTransferNotification(): bool
    {
        return in_array($this->type, [
            'transfer_request',
            'transfer_accepted',
            'transfer_rejected',
            'transfer_received',
            'transfer_reminder',
            'transfer_auto_completed',
            'transfer_cancelled',
            'pho_directive',
        ]);
    }

    public function isSupplyRequestNotification(): bool
    {
        return str_starts_with((string) $this->type, 'supply_request');
    }
}