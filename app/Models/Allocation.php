<?php

namespace App\Models;

use App\Support\StockLogBatchFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Allocation extends Model
{
    use SoftDeletes;

    public const STATUS_LABELS = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'awaiting_receipt' => 'Awaiting Receipt',
        'received' => 'Received',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
    ];

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
        'dispensing_enabled',
        'destination_health_unit_id',
        'status',
        'remarks',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'dispensing_enabled' => 'boolean',
        'quantity_allocated' => 'integer',
        'date_allocated'     => 'date',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id')->withTrashed();
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

    public function sourceBatchLogs()
    {
        return $this->stockLogs()->whereNull('voided_at')->releases()
            ->whereNotNull('batch_no')->where('batch_no', '!=', '')->orderBy('id');
    }

    // ========== HELPERS ==========

    public function getDestinationDisplayAttribute(): string
    {
        if ($this->destination_health_unit_id) {
            return $this->destinationHealthUnit->name ?? 'Unknown health unit';
        }

        return $this->destination ?: '—';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ($this->status ?: '—');
    }

    public function canEditStock(): bool
    {
        return $this->destination_health_unit_id === null && ! in_array($this->status, ['pending', 'rejected'], true);
    }

    public function getSourceBatchLabelsAttribute(): ?string
    {
        $batches = $this->sourceBatchLogs->pluck('batch_no')->unique();

        return $batches->isNotEmpty() ? $batches->implode(', ') : $this->inventory?->batch_no;
    }

    // ========== SCOPES ==========

    public function scopeInvolvingHealthUnit($query, $healthUnitId)
    {
        return $query->where(fn ($units) => $units->where('health_unit_id', $healthUnitId)
            ->orWhere('destination_health_unit_id', $healthUnitId));
    }

    public function scopeMatchingSourceBatches($query, ?string $input)
    {
        if (trim($input ?? '', " \t\n\r\0\x0B,") === '') {
            return $query;
        }

        return $query->where(fn ($matches) => $matches
            ->whereHas('sourceBatchLogs', fn ($logs) => StockLogBatchFilter::apply($logs, 'batch_no', $input))
            ->orWhere(fn ($legacy) => $legacy->whereDoesntHave('sourceBatchLogs')
                ->whereHas('inventory', fn ($batches) => StockLogBatchFilter::apply($batches, 'batch_no', $input))));
    }

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
