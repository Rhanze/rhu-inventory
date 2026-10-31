<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PublicUser extends Model
{
    public function scopeForViewer($query)
    {
        if (session('health_unit_id')) {
            $query->where('public_users.health_unit_id', session('health_unit_id'));
        }
        if (session('role') === 'midwife') {
            $query->where('public_users.assigned_midwife_id', session('user_id'));
        }

        return $query;
    }

    protected $table = 'public_users';

    protected $fillable = [
        'health_unit_id',
        'registered_by',           // ← NEW
        'assigned_midwife_id',     // ← NEW
        'philhealth_id',
        'first_name',
        'last_name',
        'address',
        'contact_number',
        'birth_date',

        // ── FHSIS / midwife extension fields ──
        'household_id',
        'mother_id',
        'family_serial_no',
        'barangay',
        'is_pregnant',
        'lmp',
        'gravida',
        'para',
        'risk_codes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'lmp' => 'date',
        'is_pregnant' => 'boolean',
        'gravida' => 'integer',
        'para' => 'integer',
    ];

    // ─── Relationships ───

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }

    // ← NEW: the midwife clinically responsible for this patient
    public function assignedMidwife()
    {
        return $this->belongsTo(User::class, 'assigned_midwife_id');
    }

    // ← NEW: who originally entered this record (audit trail)
    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function immunizationRecords()
    {
        return $this->hasMany(ImmunizationRecord::class, 'public_user_id');
    }

    public function vaccinationSchedules()
    {
        return $this->hasMany(VaccinationSchedule::class, 'public_user_id');
    }

    public function prenatalVisits()
    {
        return $this->hasMany(PrenatalVisit::class, 'patient_id');
    }

    public function familyPlanning()
    {
        return $this->hasMany(FamilyPlanning::class, 'patient_id');
    }

    public function postpartumVisits()
    {
        return $this->hasMany(PostpartumVisit::class, 'patient_id');
    }

    public function mother()
    {
        return $this->belongsTo(PublicUser::class, 'mother_id');
    }

    public function children()
    {
        return $this->hasMany(PublicUser::class, 'mother_id');
    }

    // ─── Accessors ───

    public function getFullNameAttribute(): string
    {
        $first = $this->first_name ?? '';
        $last = $this->last_name ?? '';

        return trim($first.' '.$last) ?: 'Unnamed';
    }

    public function getDobAttribute(): ?string
    {
        return $this->birth_date
            ? Carbon::parse($this->birth_date)->toDateString()
            : null;
    }

    public function getAgeInMonthsAttribute(): int
    {
        if (! $this->birth_date) {
            return 0;
        }

        return Carbon::parse($this->birth_date)->diffInMonths(now());
    }

    public function getAgeInWeeksAttribute(): int
    {
        if (! $this->birth_date) {
            return 0;
        }

        return Carbon::parse($this->birth_date)->diffInWeeks(now());
    }

    public function getAgeDisplayAttribute(): string
    {
        if (! $this->birth_date) {
            return 'N/A';
        }

        $diff = Carbon::parse($this->birth_date)->diff(now());

        if ($diff->y > 0) {
            return $diff->y.'y '.$diff->m.'m';
        }
        if ($diff->m > 0) {
            return $diff->m.'m '.$diff->d.'d';
        }

        return $diff->d.'d';
    }

    public function getIsUnderFiveAttribute(): bool
    {
        if (! $this->birth_date) {
            return false;
        }

        return Carbon::parse($this->birth_date)->age < 5;
    }

    public function getIsInfantAttribute(): bool
    {
        if (! $this->birth_date) {
            return false;
        }

        return Carbon::parse($this->birth_date)->diffInMonths(now()) < 12;
    }
}
