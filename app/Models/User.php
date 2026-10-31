<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The users table has no created_at / updated_at columns,
     * so disable Eloquent's automatic timestamp handling.
     */
    public $timestamps = false;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'health_unit_id',
        'is_active',
        'is_emergency',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_emergency' => 'boolean',
    ];

    // ─── Relationships ───
    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'health_unit_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    public function stockLogs()
    {
        return $this->hasMany(StockLog::class, 'added_by');
    }

    // ← NEW: barangays this midwife covers
    public function assignedBarangays()
    {
        return $this->hasMany(MidwifeBarangay::class, 'user_id');
    }

    // ← NEW: patients assigned to this midwife
    public function assignedPatients()
    {
        return $this->hasMany(PublicUser::class, 'assigned_midwife_id');
    }

    // ─── Scopes ───
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeStaff($query)
    {
        return $query->where('role', 'staff');
    }

    public function scopeMidwives($query)
    {
        return $query->where('role', 'midwife');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // ─── Role helpers ───
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isMidwife(): bool
    {
        return $this->role === 'midwife';
    }

    public function isRhuAdmin(): bool
    {
        return $this->role === 'admin' && !is_null($this->health_unit_id);
    }

    public function isPhoAdmin(): bool
    {
        return $this->role === 'admin' && is_null($this->health_unit_id);
    }

    // ─── Label helpers ───
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin'   => $this->isPhoAdmin() ? 'PHO Admin' : 'RHU Admin',
            'staff'   => 'Staff',
            'midwife' => 'Midwife',
            default   => ucfirst($this->role ?? 'Unknown'),
        };
    }
}