<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public const TYPE_TRANSFER_PHO_REVIEW_REQUESTED = 'transfer_pho_review_requested';

    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = [
        'health_unit_id',
        'user_id',          // null = RHU-shared, set = personal
        'inventory_id',
        'transfer_id',
        'allocation_id',    // ← NEW — links to supply request Approve/Reject
        'type',
        'dedup_key',
        'link',
        'title',
        'message',
        'severity',
        'is_read',
        'archived_at',
        'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    // ─── Relationships ───

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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
        return $this->belongsTo(Allocation::class, 'allocation_id');
    }

    // ─── Scopes ───

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForHealthUnit($query, $healthUnitId)
    {
        return $query->where(fn ($q) => $q->where('health_unit_id', $healthUnitId)
            ->orWhereNull('health_unit_id'));
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
            self::TYPE_TRANSFER_PHO_REVIEW_REQUESTED,
            'transfer_pho_approved',
            'transfer_pho_rejected',
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

    public function destinationUrl(?string $role): ?string
    {
        if ($this->type === 'disease_outbreak') {
            return null;
        }

        if ($this->allocation_id && $this->isSupplyRequestNotification()) {
            return route($role === 'midwife' ? 'supply_requests.index' : 'allocation.index');
        }

        $link = trim((string) $this->link);
        $scheme = parse_url($link, PHP_URL_SCHEME);
        if ($link !== '' && ! str_starts_with($link, '#')
            && ($scheme === null || in_array(strtolower((string) $scheme), ['http', 'https'], true))) {
            // Receipt controls are already on the notification card; opening this page again adds no detail.
            if ($this->type === 'allocation_to_user' && $link === route('notifications.index')) {
                return null;
            }

            return $link;
        }

        if ($this->inventory_id && $role !== 'midwife') {
            return route('inventory.index', ['highlight' => $this->inventory_id]).'#inventory-'.$this->inventory_id;
        }
        if ($this->transfer_id && $role !== 'midwife') {
            return route('transfers.index');
        }

        return null;
    }
}
