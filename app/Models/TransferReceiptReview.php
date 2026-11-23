<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferReceiptReview extends Model
{
    public $timestamps = false;

    protected $fillable = ['reviewed_by', 'expected_quantity', 'received_quantity', 'evidence_reference', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime', 'expected_quantity' => 'integer', 'received_quantity' => 'integer'];

    public function matchesQuantity(): bool
    {
        return $this->expected_quantity === $this->received_quantity;
    }

    public function resolution()
    {
        return $this->hasOne(TransferReceiptResolution::class);
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->matchesQuantity() => 'Quantity verified',
            $this->resolution !== null => 'Discrepancy resolved',
            default => 'Discrepancy recorded — resolution pending',
        };
    }
}
