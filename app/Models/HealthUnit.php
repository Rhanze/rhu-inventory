<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthUnit extends Model
{
    protected $table = 'health_units';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'municipality',
        'facility_type',
        'address',
        'latitude',
        'longitude',
        'parent_id',
        'immunization_pattern',
        'immunization_weekday',
        'immunization_week_number',
    ];

    protected $casts = [
        'latitude'                  => 'decimal:7',
        'longitude'                 => 'decimal:7',
        'immunization_weekday'      => 'integer',
        'immunization_week_number'  => 'integer',
    ];

    // ─── Relationships ───
    public function users()
    {
        return $this->hasMany(User::class, 'health_unit_id');
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'health_unit_id');
    }

    public function parent()
    {
        return $this->belongsTo(HealthUnit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(HealthUnit::class, 'parent_id');
    }

    public function publicUsers()
    {
        return $this->hasMany(PublicUser::class, 'health_unit_id');
    }

    public function vaccinationSchedules()
    {
        return $this->hasMany(VaccinationSchedule::class, 'health_unit_id');
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class, 'health_unit_id');
    }

    // ─── Scopes ───

    /**
     * Only real RHUs (excludes Provincial Health Office and any other facility).
     */
    public function scopeRhuOnly($query)
    {
        return $query->where('facility_type', 'Rural Health Unit');
    }

    /**
     * Alias — kept for backward compatibility.
     */
    public function scopeRhus($query)
    {
        return $query->where('facility_type', 'Rural Health Unit');
    }

    /**
     * Barangay Health Stations.
     */
    public function scopeBhss($query)
    {
        return $query->where('facility_type', 'Barangay Health Station');
    }

    /**
     * Provincial Health Office.
     */
    public function scopePho($query)
    {
        return $query->where('facility_type', 'Provincial Health Office');
    }

    // ─── Helpers ───

    /**
     * Return the id of the Provincial Health Office row.
     */
    public static function phoId(): ?int
    {
        return self::where('facility_type', 'Provincial Health Office')
            ->orWhere('name', 'like', '%Provincial Health%')
            ->value('id');
    }

    // ─── Accessors ───

    public function getImmunizationPatternLabelAttribute(): string
    {
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $day = $this->immunization_weekday !== null
            ? ($dayNames[(int) $this->immunization_weekday] ?? '')
            : '';

        return match ($this->immunization_pattern) {
            'weekly'        => "Every {$day}",
            'monthly_first' => "First {$day} of month",
            'monthly_last'  => "Last {$day} of month",
            'monthly_nth'   => "{$this->immunization_week_number}th {$day} of month",
            default         => 'Vaccinate on exact due date',
        };
    }
}