<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DispensingRecord extends Model
{
    use SoftDeletes;

    protected $table = 'dispensing_records';

    public $timestamps = false;

    protected $fillable = [
        'medicine_id',
        'health_unit_id',
        'patient_name',              // ← NEW
        'quantity_dispensed',
        'batch_no',
        'doctor_name',
        'date_dispensed',
        'dispensed_by',
        'prescription_image',
    ];

    protected $casts = [
        'quantity_dispensed' => 'integer',
        'date_dispensed'     => 'date:Y-m-d',
    ];

    // ─── Relationships ───

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }

    public function healthUnit()
    {
        return $this->belongsTo(HealthUnit::class, 'health_unit_id');
    }

    public function dispensedByUser()
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    public function stockLogs()
    {
        return $this->hasMany(StockLog::class, 'dispensing_record_id');
    }

    // ─── Helpers ───

    public function hasPrescription(): bool
    {
        return !empty($this->prescription_image);
    }
}