<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use SoftDeletes;

    public const NEAR_EXPIRY_DAYS = 30;

    protected $table = 'inventory';

    public $timestamps = false;

    protected $fillable = [
        'medicine_id',
        'health_unit_id',
        'available_stock',
        'allocated_stock',
        'batch_no',
        'storage_location',
        'pack_type',
        'pieces_per_pack',
        'expiration_date',
    ];

    protected $casts = [
        'expiration_date' => 'date:Y-m-d',
        'available_stock' => 'integer',
        'allocated_stock' => 'integer',
        'pieces_per_pack' => 'integer',
    ];

    // Relationships
    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'health_unit_id');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    /**
     * FEFO uses earliest expiry, then FIFO by the first recorded receipt.
     * Batches without a receipt date follow dated receipts, with ID as a stable fallback.
     */
    public function scopeFefo($query)
    {
        return $query->withReceiptDate()
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderByRaw('stock_received_at IS NULL')
            ->orderBy('stock_received_at')
            ->orderBy('id', 'asc');
    }

    public function scopeWithReceiptDate($query)
    {
        $receipts = StockLog::query()->selectRaw('COALESCE(MIN(CASE WHEN stock_logs.inventory_id = inventory.id THEN stock_logs.date_added END), MIN(stock_logs.date_added))')
            ->whereColumn('stock_logs.medicine_id', 'inventory.medicine_id')
            ->whereColumn('stock_logs.health_unit_id', 'inventory.health_unit_id')
            ->whereNull('stock_logs.voided_at')->where('stock_logs.quantity_added', '>', 0)->additions()
            ->where(fn ($receipt) => $receipt->whereNull('stock_logs.movement_type')
                ->orWhereIn('stock_logs.movement_type', ['addition', 'receipt', 'transfer_in']))
            ->where(fn ($reason) => $reason->whereNull('stock_logs.remarks')->orWhere('stock_logs.remarks', 'not like', '%Inventory adjusted%'))
            ->where(function ($match) {
                $match->whereColumn('stock_logs.inventory_id', 'inventory.id')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('stock_logs.inventory_id')->where(function ($batch) {
                            $batch->whereColumn('stock_logs.batch_no', 'inventory.batch_no')
                                ->orWhere(fn ($unnumbered) => $unnumbered->whereNull('stock_logs.batch_no')->whereNull('inventory.batch_no'));
                        });
                    });
            });

        return $query->addSelect(['stock_received_at' => $receipts]);
    }

    public function stockRotationKey(): string
    {
        return ($this->expiration_date?->toDateString() ?? '9999-12-31').':'.
            ($this->stock_received_at ?? '9999-12-31 23:59:59').':'.sprintf('%020d', $this->id);
    }

    /**
     * Batches with available stock, non-expired (or no expiry), FEFO-ordered.
     */
    public function scopeAvailableFefo($query, int $healthUnitId, int $medicineId)
    {
        return $query
            ->availableAt($healthUnitId)
            ->where('medicine_id', $medicineId)
            ->fefo();
    }

    public function scopeAvailableAt($query, int $healthUnitId)
    {
        return $query->where('health_unit_id', $healthUnitId)->usableStock();
    }

    public function scopeUsableStock($query)
    {
        return $query->where('available_stock', '>', 0)
            ->where(fn ($batch) => $batch->whereNull('expiration_date')
                ->orWhereDate('expiration_date', '>=', today()));
    }

    public function scopeExpiringWithin($query, int $days = self::NEAR_EXPIRY_DAYS, ?CarbonInterface $from = null)
    {
        $from ??= today();

        return $query->whereDate('inventory.expiration_date', '>=', $from)
            ->whereDate('inventory.expiration_date', '<=', $from->copy()->addDays($days))
            ->where('inventory.available_stock', '>', 0)
            ->whereHas('medicine');
    }

    public function scopeLowStock($query, $threshold = 10)
    {
        return $query->where('available_stock', '<=', $threshold)
                     ->where('available_stock', '>', 0);
    }

    public function scopeStockLevel($query, string $level)
    {
        return $query->where('inventory.available_stock', '>', 0)
            ->where(fn ($batch) => $batch->whereNull('inventory.expiration_date')
                ->orWhereDate('inventory.expiration_date', '>=', today()))
            ->whereHas('medicine', function ($medicine) use ($level) {
                if ($level === 'low') {
                    $medicine->whereRaw('inventory.available_stock <= COALESCE(medicines.min_stock, ?)',
                        [(int) config('inventory.default_min_stock', 50)]);
                } elseif ($level === 'overstock') {
                    $medicine->whereRaw('inventory.available_stock > COALESCE(medicines.max_stock, ?)',
                        [(int) config('inventory.default_max_stock', 500)]);
                }
            });
    }

    public function scopeActive($query)
    {
        return $query->where('available_stock', '>', 0);
    }

    // ─── Display helpers (unchanged) ───

    public function getBaseUnitAttribute(): string
    {
        return $this->medicine->unit ?? 'piece';
    }

    public function getPackTypeResolvedAttribute(): ?string
    {
        return $this->pack_type ?? $this->medicine->pack_type;
    }

    public function getPiecesPerPackResolvedAttribute(): ?int
    {
        $ppp = $this->pieces_per_pack ?? $this->medicine->pieces_per_pack;
        return $ppp !== null ? (int) $ppp : null;
    }

    public function getUnitDisplayAttribute(): string
    {
        $ppp      = $this->pieces_per_pack_resolved;
        $packType = $this->pack_type_resolved;
        $unit     = $this->base_unit;

        if ($packType && $ppp && $ppp > 0) {
            $unitWord = $ppp === 1 ? $unit : $unit . 's';
            $packWord = $ppp === 1 ? $packType : $packType . 's';
            return $ppp . ' ' . $unitWord . ' per ' . $packWord;
        }

        return 'per ' . $unit;
    }

    public function formatQuantity(int $qty): string
    {
        $unit     = $this->base_unit;
        $packType = $this->pack_type_resolved;
        $ppp      = $this->pieces_per_pack_resolved;

        if (!$packType || !$ppp || $ppp < 1) {
            return $qty . ' ' . ($qty === 1 ? $unit : $unit . 's');
        }

        $packs  = intdiv($qty, $ppp);
        $pieces = $qty % $ppp;

        $parts = [];

        if ($packs > 0) {
            $parts[] = $packs . ' ' . ($packs === 1 ? $packType : $packType . 's');
        }
        if ($pieces > 0) {
            $parts[] = $pieces . ' ' . ($pieces === 1 ? $unit : $unit . 's');
        }

        return implode(' + ', $parts) ?: '0';
    }
}
