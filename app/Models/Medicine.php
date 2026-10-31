<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use SoftDeletes;

    protected $table = 'medicines';

    public $timestamps = false;

    protected $fillable = [
        'medicine_name',
        'dosage',
        'category',
        'dosage_form',
        'unit',
        'pack_type',
        'pieces_per_pack',
        'description',
        'requires_prescription',
        'is_dispensable',
        'min_stock',
        'max_stock',
    ];

    protected $casts = [
        'pieces_per_pack'       => 'integer',
        'requires_prescription' => 'boolean',
        'is_dispensable'        => 'boolean',
        'min_stock'             => 'integer',
        'max_stock'             => 'integer',
    ];

    // ─── Relationships ───
    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'medicine_id');
    }

    public function stockLogs()
    {
        return $this->hasMany(StockLog::class, 'medicine_id');
    }

    public function allocations()
    {
        return $this->hasMany(Allocation::class, 'medicine_id');
    }

    public function dispensingRecords()
    {
        return $this->hasMany(DispensingRecord::class, 'medicine_id');
    }

    // ─── Scopes ───
    public function scopeByName($query, $name)
    {
        return $query->where('medicine_name', 'like', "%{$name}%");
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeDispensable($query)
    {
        return $query->where('is_dispensable', true);
    }

    public function scopeInternalOnly($query)
    {
        return $query->where('is_dispensable', false);
    }

    // ─── Helpers ───

    /**
     * Friendly category name. Use this in views instead of `$medicine->category`
     * so no underscores leak into the UI.
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'medicine'                => 'Medicine',
            'vaccine'                 => 'Vaccine',
            'family_planning'         => 'Family Planning',
            'family_health_nutrition' => 'Family Health & Nutrition',
            'emerging_reemerging'     => 'Emerging & Re-emerging',
            'non_communicable'        => 'Non-Communicable Disease',
            'dengue_prevention'       => 'Dengue Prevention',
            'tuberculosis'            => 'Tuberculosis',
            default                   => $this->category
                                            ? ucwords(str_replace('_', ' ', $this->category))
                                            : '—',
        };
    }

    public function getUnitDisplayAttribute(): string
    {
        $unit = $this->unit ?? 'unit';

        if ($this->pack_type && $this->pieces_per_pack) {
            $pluralUnit = $this->pieces_per_pack === 1 ? $unit : $unit . 's';
            $pluralPack = $this->pieces_per_pack === 1 ? $this->pack_type : $this->pack_type . 's';
            return $this->pieces_per_pack . ' ' . $pluralUnit . ' per ' . $pluralPack;
        }

        return 'per ' . $unit;
    }

    public function getFullLabelAttribute(): string
    {
        $parts = [];

        if ($this->medicine_name) {
            $label = $this->medicine_name;
            if ($this->dosage) {
                $label .= ' ' . $this->dosage;
            }
            if ($this->dosage_form) {
                $label .= ' (' . $this->dosage_form . ')';
            }
            $parts[] = $label;
        }

        if ($this->unit) {
            $parts[] = $this->unit_display;
        }

        return implode(' — ', $parts);
    }

    public function formatQuantity(int $qty): string
    {
        $unit = $this->unit ?? 'unit';

        if (!$this->pack_type || !$this->pieces_per_pack || $this->pieces_per_pack < 1) {
            return $qty . ' ' . ($qty === 1 ? $unit : $unit . 's');
        }

        $packs  = intdiv($qty, $this->pieces_per_pack);
        $pieces = $qty % $this->pieces_per_pack;

        $parts = [];

        if ($packs > 0) {
            $parts[] = $packs . ' ' . ($packs === 1 ? $this->pack_type : $this->pack_type . 's');
        }
        if ($pieces > 0) {
            $parts[] = $pieces . ' ' . ($pieces === 1 ? $unit : $unit . 's');
        }

        return implode(' + ', $parts) ?: '0';
    }

    // ─── MMSL — Min-Max Stock Level helpers ───

    public function effectiveMinStock(): int
    {
        return (int) ($this->min_stock
            ?? config('inventory.default_min_stock', 50));
    }

    public function effectiveMaxStock(): int
    {
        return (int) ($this->max_stock
            ?? config('inventory.default_max_stock', 500));
    }

    public function isLowStock(int $availableStock): bool
    {
        return $availableStock > 0
            && $availableStock <= $this->effectiveMinStock();
    }

    public function isOverstock(int $availableStock): bool
    {
        return $availableStock > $this->effectiveMaxStock();
    }
}