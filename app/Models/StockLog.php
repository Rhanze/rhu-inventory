<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLog extends Model
{
    private const ADDITION_TYPES = ['addition', 'receipt', 'transfer_in', 'adjustment_in'];

    private const RELEASE_TYPES = ['dispensing', 'allocation', 'transfer_out', 'adjustment_out'];

    protected $table = 'stock_logs';

    public $timestamps = false;

    protected $fillable = [
        'medicine_id',
        'inventory_id', 'movement_type', 'voided_at',
        'health_unit_id',
        'quantity_added',
        'batch_no',
        'storage_location',
        'date_added',
        'added_by',
        'remarks',
        'dispensing_record_id',
        'transfer_id',
        'allocation_id',          // ← NEW
    ];

    protected $casts = [
        'date_added' => 'date:Y-m-d',
        'quantity_added' => 'integer',
    ];

    // ─── Relationships ───
    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id')->withTrashed();
    }

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'health_unit_id');
    }

    public function addedByUser()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function dispensingRecord()
    {
        return $this->belongsTo(DispensingRecord::class, 'dispensing_record_id');
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
    /** Currently effective rows; cancelled transfers and reversed originals are audit evidence. */
    public function scopeActiveForReporting($query)
    {
        return $query->whereNull('stock_logs.voided_at')
            ->where(fn ($row) => $row->whereNull('stock_logs.movement_type')->orWhere('stock_logs.movement_type', '!=', 'reversal'))
            ->whereDoesntHave('transfer', fn ($transfer) => $transfer->where('status', 'cancelled'));
    }

    public function scopeAdditions($query)
    {
        return $this->directionScope($query, self::ADDITION_TYPES, ['added%', 'stock%', 'received%'], true);
    }

    public function isDispensingMovement(): bool
    {
        return $this->dispensing_record_id !== null || $this->movement_type === 'dispensing'
            || ($this->movement_type === null && preg_match('/\A(?:dispensed|released.*dispensed)/is', trim($this->remarks ?? '')) === 1);
    }

    public function getReportRemarksAttribute(): ?string
    {
        return $this->isDispensingMovement() ? null : $this->remarks;
    }

    /** Explicit movement types take precedence over editable legacy descriptions. */
    public function getMovementDirectionAttribute(): ?string
    {
        if ($this->movement_type !== null) {
            return in_array($this->movement_type, self::ADDITION_TYPES, true) ? 'addition'
                : (in_array($this->movement_type, self::RELEASE_TYPES, true) ? 'release' : null);
        }

        $remarks = strtolower($this->remarks ?? '');
        if ($this->remarks === null || preg_match('/^(added|stock|received)/', $remarks)) {
            return 'addition';
        }

        return preg_match('/^(released|dispensed|allocated)/', $remarks) ? 'release' : null;
    }

    private function directionScope($query, array $types, array $patterns, bool $nullAddition = false)
    {
        return $query->where(function ($direction) use ($types, $patterns, $nullAddition) {
            $direction->whereIn('movement_type', $types)
                ->orWhere(function ($legacy) use ($patterns, $nullAddition) {
                    $legacy->whereNull('movement_type')->where(function ($remarks) use ($patterns, $nullAddition) {
                        if ($nullAddition) {
                            $remarks->whereNull('remarks');
                        }
                        foreach ($patterns as $pattern) {
                            $remarks->orWhereRaw('LOWER(remarks) LIKE ?', [$pattern]);
                        }
                    });
                });
        });
    }

    /** Patient dispensing and terminal programs; moving stock to a recipient is not patient usage. */
    public function scopeDemandReleases($query)
    {
        $terminalAllocation = fn ($allocation) => $allocation->whereNull('destination_health_unit_id')
            ->whereNull('recipient_user_id')
            ->whereIn('status', ['completed', 'approved', 'received'])
            ->where(fn ($state) => $state->whereNotExists(fn ($movement) => $movement->selectRaw('1')
                ->from('stock_movements')->where('owner_type', 'allocations')->whereColumn('owner_id', 'allocations.id')
                ->whereNull('reverses_id'))
                ->orWhereExists(fn ($movement) => $movement->selectRaw('1')->from('stock_movements')
                    ->where('owner_type', 'allocations')->whereColumn('owner_id', 'allocations.id')
                    ->whereNull('reverses_id')->whereNull('reversed_at')))
            ->whereDoesntHave('allocatedBy', fn ($user) => $user->where('role', 'midwife')
                ->whereColumn('users.health_unit_id', 'allocations.health_unit_id'));

        return $query->activeForReporting()->where('quantity_added', '>', 0)
            ->where(fn ($record) => $record->whereNull('dispensing_record_id')
                ->orWhereHas('dispensingRecord', fn ($dispensing) => $dispensing->qualifying()))
            ->where(function ($q) use ($terminalAllocation) {
                $q->where('movement_type', 'dispensing')
                    ->orWhere(fn ($issued) => $issued->where('movement_type', 'allocation')
                        ->where(fn ($link) => $link->whereNull('allocation_id')->orWhereHas('allocation', $terminalAllocation)))
                    ->orWhere(function ($legacy) use ($terminalAllocation) {
                        $legacy->whereNull('movement_type')->whereNull('transfer_id')->where(function ($release) use ($terminalAllocation) {
                            $release->whereNotNull('dispensing_record_id')
                                ->orWhereHas('allocation', $terminalAllocation)
                                ->orWhere(function ($unlinked) {
                                    $unlinked->whereNull('dispensing_record_id')->whereNull('allocation_id')->where(function ($text) {
                                        $text->where('remarks', 'like', 'Dispensed%')->orWhere('remarks', 'like', 'Allocated%')
                                            ->orWhere('remarks', 'like', 'Released%Dispensed%')->orWhere('remarks', 'like', 'Released%Allocated%');
                                    });
                                });
                        });
                    });
            });
    }

    public function scopeReleases($query)
    {
        return $this->directionScope($query, self::RELEASE_TYPES, ['released%', 'dispensed%', 'allocated%']);
    }

    public function scopeInLastDays($query, $days)
    {
        return $query->where('date_added', '>=', now()->subDays($days));
    }

    public function scopeByMonth($query, $year, $month)
    {
        return $query->whereYear('date_added', $year)
            ->whereMonth('date_added', $month);
    }
}
