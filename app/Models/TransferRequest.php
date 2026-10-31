<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferRequest extends Model
{
    protected $table = 'transfer_requests';

    protected $fillable = [
        'stock_reserved',
        'initiator_type',
        'from_health_unit_id',
        'to_health_unit_id',
        'medicine_id',

        // ▼ batch + medicine snapshot
        'inventory_id',
        'batch_no',
        'dosage',
        'dosage_form',
        'unit',
        'category',
        'pack_type',
        'pieces_per_pack',
        // ▲ END

        'quantity',
        'notes',
        'status',
        'requested_by',
        'approved_by',
        'rejected_by',
        'approved_at',
        'rejected_at',
        'completed_at',

        // ▼ receive workflow
        'received_at',
        'received_by',
        'reminder_sent_at',
        'auto_completed',
        // ▲ END

        'rejection_reason',
    ];

    protected $casts = [
        'stock_reserved' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'received_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'auto_completed' => 'boolean',
        'pieces_per_pack' => 'integer',
        'quantity' => 'integer',
    ];

    // ─── Relationships ───

    public function fromHealthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'from_health_unit_id');
    }

    public function toHealthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'to_health_unit_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ─── Status helpers ───

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isAutoCompleted(): bool
    {
        return (bool) $this->auto_completed;
    }

    // ─── Display helpers ───

    /**
     * Friendly category name. Use this in views instead of `$transfer->category`
     * so no underscores leak into the UI.
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'medicine' => 'Medicine',
            'vaccine' => 'Vaccine',
            'family_planning' => 'Family Planning',
            'family_health_nutrition' => 'Family Health & Nutrition',
            'emerging_reemerging' => 'Emerging & Re-emerging',
            'non_communicable' => 'Non-Communicable Disease',
            'dengue_prevention' => 'Dengue Prevention',
            'tuberculosis' => 'Tuberculosis',
            default => $this->category
                                            ? ucwords(str_replace('_', ' ', $this->category))
                                            : '',
        };
    }

    /**
     * Resolve the base unit — prefers the snapshot, falls back to the live medicine.
     * `base_unit` is the actual column on the medicines table.
     */
    public function getBaseUnitAttribute(): string
    {
        return $this->unit
            ?? $this->medicine?->base_unit
            ?? $this->medicine?->unit
            ?? 'unit';
    }

    /**
     * "Amoxicillin 500mg (Capsule)" — falls back to the live medicine record
     * if the snapshot is missing on this row.
     */
    public function getMedicineLabelAttribute(): string
    {
        $name = $this->medicine?->medicine_name;

        if (! $name) {
            return 'N/A';
        }

        $label = $name;

        $dosage = $this->dosage ?? $this->medicine->dosage;
        $form = $this->dosage_form ?? $this->medicine->dosage_form;

        if ($dosage) {
            $label .= ' '.$dosage;
        }
        if ($form) {
            $label .= ' ('.$form.')';
        }

        return $label;
    }

    /**
     * Human-readable quantity using the snapshot:
     *   23 → "2 Stubs + 3 tablets"
     *   10 → "1 Stub"
     *    5 → "5 tablets"
     * Falls back to raw count when pack info is missing.
     */
    public function formatQuantity(?int $qty = null): string
    {
        $qty = $qty ?? (int) $this->quantity;

        if ($qty <= 0) {
            return '0';
        }

        $unit = $this->base_unit;
        $ppp = (int) ($this->pieces_per_pack ?? 0);

        // No pack info → just show base units
        if (! $this->pack_type || $ppp < 1) {
            return $qty.' '.($qty === 1 ? $unit : $unit.'s');
        }

        $packs = intdiv($qty, $ppp);
        $pieces = $qty % $ppp;

        $parts = [];

        if ($packs > 0) {
            $parts[] = $packs.' '.($packs === 1 ? $this->pack_type : $this->pack_type.'s');
        }
        if ($pieces > 0) {
            $parts[] = $pieces.' '.($pieces === 1 ? $unit : $unit.'s');
        }

        return implode(' + ', $parts) ?: '0';
    }

    /**
     * "10 tablets per Stub" — used in the snapshot card.
     */
    public function getUnitDisplayAttribute(): string
    {
        $ppp = (int) ($this->pieces_per_pack ?? 0);
        $unit = $this->base_unit;

        if ($this->pack_type && $ppp > 0) {
            $unitWord = $ppp === 1 ? $unit : $unit.'s';
            $packWord = $ppp === 1 ? $this->pack_type : $this->pack_type.'s';

            return $ppp.' '.$unitWord.' per '.$packWord;
        }

        return 'per '.$unit;
    }

    /**
     * Short label of the initiator type, for badges.
     */
    public function getInitiatorLabelAttribute(): string
    {
        return $this->initiator_type === 'pho_directive'
            ? 'PHO Directive'
            : 'RHU Request';
    }
}
