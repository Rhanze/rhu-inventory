<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class DispensingRecord extends Model
{
    use SoftDeletes;

    protected $table = 'dispensing_records';

    public $timestamps = false;

    protected $fillable = [
        'medicine_id',
        'stock_source',
        'health_unit_id',
        'barangay',
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
        'date_dispensed' => 'date:Y-m-d',
    ];

    // ─── Relationships ───

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id')->withTrashed();
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

    /** Saved usage shared by totals, record lists, exports, and consumption analysis. */
    public function scopeQualifying($query)
    {
        return $query->where('quantity_dispensed', '>', 0)
            ->where(fn ($q) => $q->whereDoesntHave('stockLogs')
                ->orWhereHas('stockLogs', fn ($log) => $log->whereNull('voided_at')))
            ->where(function ($query) {
                $originalMovements = fn ($movement) => $movement->selectRaw('1')->from('stock_movements')
                    ->where('owner_type', 'dispensing_records')->whereColumn('owner_id', 'dispensing_records.id')
                    ->whereNull('reverses_id');
                $query->whereNotExists($originalMovements)->orWhereExists(function ($movement) use ($originalMovements) {
                    $originalMovements($movement)->whereNull('reversed_at');
                });
            });
    }

    public function scopeWithoutRecordedBarangay($query)
    {
        return $query->where(fn ($area) => $area->whereNull('barangay')->orWhereRaw("TRIM(barangay) = ''"));
    }

    public function scopeForBarangays($query, iterable $barangays)
    {
        return $query->whereIn(DB::raw('LOWER(TRIM(barangay))'),
            collect($barangays)->map(fn ($name) => mb_strtolower(trim($name))));
    }

    public function scopeBarangayBreakdown($query)
    {
        return $query->setEagerLoads([])->with('medicine')
            ->selectRaw("COALESCE(MIN(NULLIF(TRIM(barangay), '')), 'Not recorded') AS area, MIN(NULLIF(TRIM(barangay), '')) AS recorded_area, medicine_id, COUNT(*) AS record_count, SUM(quantity_dispensed) AS quantity")
            ->groupByRaw("NULLIF(LOWER(TRIM(barangay)), '')")->groupBy('medicine_id')->orderBy('area');
    }

    // ─── Helpers ───

    public function prescribingDoctorName(): ?string
    {
        $name = trim((string) $this->doctor_name);

        return $this->medicine?->requires_prescription && $name !== '' ? $name : null;
    }

    public function prescribingDoctorDisplay(): string
    {
        return $this->prescribingDoctorName()
            ?? ($this->medicine?->requires_prescription ? 'Not recorded' : 'Not required');
    }

    public function hasPrescription(): bool
    {
        return ! empty($this->prescription_image);
    }
}
