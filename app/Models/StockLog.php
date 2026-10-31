<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLog extends Model
{
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
        return $this->belongsTo(Medicine::class, 'medicine_id');
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
    public function scopeAdditions($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('remarks')
                ->orWhere('remarks', 'like', 'Added%')
                ->orWhere('remarks', 'like', 'Stock%');
        });
    }

    /** Patient/local-program releases; supply receipts, logistics and corrections are not usage. */
    public function scopeDemandReleases($query)
    {
        return $query->whereNull('voided_at')->where('quantity_added', '>', 0)
            ->where(function ($q) {
                $q->whereIn('movement_type', ['dispensing', 'allocation'])
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('movement_type')->whereNull('transfer_id')->where(function ($release) {
                            $release->whereNotNull('dispensing_record_id')
                                ->orWhereHas('allocation', fn ($a) => $a->whereNull('destination_health_unit_id'))
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
        return $query->where(function ($q) {
            $q->where('remarks', 'like', 'Released%')
                ->orWhere('remarks', 'like', 'Dispensed%')
                ->orWhere('remarks', 'like', 'Allocated%');
        });
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
