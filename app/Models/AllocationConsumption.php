<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationConsumption extends Model
{
    protected $fillable = ['allocation_id', 'dispensing_record_id', 'inventory_id', 'stock_movement_id', 'quantity', 'reversed_at'];

    protected $casts = ['quantity' => 'integer', 'reversed_at' => 'datetime'];
}
