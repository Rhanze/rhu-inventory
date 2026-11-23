<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferReceiptResolution extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_log_id', 'resolved_by', 'decision', 'reason', 'resolved_at'];

    protected $casts = ['resolved_at' => 'datetime'];
}
