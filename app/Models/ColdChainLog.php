<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColdChainLog extends Model
{
    protected $fillable = [
        'health_unit_id', 'logged_at', 'temperature_c',
        'equipment', 'vvm_status', 'notes', 'logged_by',
    ];

    protected $casts = [
        'logged_at'      => 'datetime',
        'temperature_c'  => 'decimal:2',
    ];

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'health_unit_id');
    }

    public function logger()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    /**
     * WHO cold chain: vaccines must stay between +2°C and +8°C.
     */
    public function getIsSafeAttribute(): bool
    {
        return $this->temperature_c >= 2.00 && $this->temperature_c <= 8.00;
    }

    public function getStatusBadgeAttribute(): array
    {
        $t = (float) $this->temperature_c;
        if ($t < 0)   return ['bg' => '#ede9fe', 'color' => '#6d28d9', 'label' => 'Frozen'];
        if ($t < 2)   return ['bg' => '#dbeafe', 'color' => '#1e40af', 'label' => 'Too Cold'];
        if ($t <= 8)  return ['bg' => '#dcfce7', 'color' => '#166534', 'label' => 'Safe'];
        if ($t <= 25) return ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => 'Too Warm'];
        return ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => 'Danger'];
    }
}