<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DohShipmentItem extends Model
{
    protected $table = 'doh_shipment_items';

    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'medicine_id',
        'quantity',
        'batch_no',
        'expiration_date',
        'storage_location',
        'unit_cost',
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'unit_cost'       => 'decimal:2',
        'expiration_date' => 'date:Y-m-d',
    ];

    // ─── Relationships ───
    public function shipment()
    {
        return $this->belongsTo(DohShipment::class, 'shipment_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }
}