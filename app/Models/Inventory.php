<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use SoftDeletes;

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
     * FEFO ordering: earliest expiration_date first, null expiry last.
     * Tiebreak by id ascending for stable results.
     */
    public function scopeFefo($query)
    {
        return $query
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC')
            ->orderBy('id', 'asc');
    }

    /**
     * Batches with available stock, non-expired (or no expiry), FEFO-ordered.
     */
    public function scopeAvailableFefo($query, int $healthUnitId, int $medicineId)
    {
        return $query
            ->where('health_unit_id', $healthUnitId)
            ->where('medicine_id', $medicineId)
            ->where('available_stock', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiration_date')
                  ->orWhereDate('expiration_date', '>=', now()->toDateString());
            })
            ->fefo();
    }

    public function scopeExpiringWithin($query, $days)
    {
        return $query->where('expiration_date', '<=', now()->addDays($days))
                     ->where('expiration_date', '>', now())
                     ->where('available_stock', '>', 0);
    }

    public function scopeLowStock($query, $threshold = 10)
    {
        return $query->where('available_stock', '<=', $threshold)
                     ->where('available_stock', '>', 0);
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