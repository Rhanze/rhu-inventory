<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DohShipment extends Model
{
    protected $table = 'doh_shipments';

    // Table has only created_at (no updated_at)
    public $timestamps = false;

    protected $fillable = [
        'shipment_no',
        'reference_no',
        'supplier',
        'received_date',
        'received_by',
        'remarks',
    ];

    protected $casts = [
        'received_date' => 'date:Y-m-d',
    ];

    // ─── Relationships ───
    public function items()
    {
        return $this->hasMany(DohShipmentItem::class, 'shipment_id');
    }

    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ─── Accessors ───
    public function getTotalItemsAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }
}