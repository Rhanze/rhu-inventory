<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $table = 'calendar_events';

    /**
     * Event type keys → labels.
     * The controller uses array_keys() for validation rules.
     */
    public const TYPES = [
        'immunization_drive' => 'Immunization Drive',
        'outbreak_response'  => 'Outbreak Response',
        'training'           => 'Training / Meeting',
        'stock_delivery'     => 'Stock Delivery',
        'other'              => 'Other',

        // Legacy keys — kept so old rows still validate & render
        'immunization_day'   => 'Immunization Day',
        'catch_up_campaign'  => 'Catch-Up Campaign',
        'holiday'            => 'Holiday / No Session',
    ];

    /**
     * Priority keys → labels.
     */
    public const PRIORITIES = [
        'normal'    => 'Normal',
        'high'      => 'High Priority',
        'emergency' => 'Emergency',
    ];

    protected $fillable = [
        'health_unit_id',
        'title',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'type',
        'priority',
        'area_label',      // ← added
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date:Y-m-d',
    ];

    // ─── Relationships ───

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ───

    public function scopeForHealthUnit($query, $healthUnitId)
    {
        return $query->where('health_unit_id', $healthUnitId);
    }

    // ─── Accessors ───

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? 'Normal';
    }

    // ─── Helper: is this an auto-generated EPI day? ───

    public function isEpiDay(): bool
    {
        return $this->type === 'immunization_day';
    }
}