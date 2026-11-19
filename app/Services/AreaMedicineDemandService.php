<?php

namespace App\Services;

use App\Models\DispensingRecord;
use App\Models\Medicine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Local medicine demand indicators; these are never disease case counts. */
class AreaMedicineDemandService
{
    public function analyze(int $healthUnitId, string $barangay, string $periodType = 'weekly'): array
    {
        if (! in_array($periodType, ['weekly', 'monthly'], true)) {
            throw new \InvalidArgumentException('Demand periods must be weekly or monthly.');
        }
        if (trim($barangay) === '') {
            throw new \InvalidArgumentException('A barangay is required for local demand analysis.');
        }

        $currentStart = $this->periodStart(CarbonImmutable::today(), $periodType);
        $latestStart = $this->offset($currentStart, -1, $periodType);
        $baselineStart = $this->offset($currentStart, -5, $periodType);
        $query = DispensingRecord::where('health_unit_id', $healthUnitId)->where('barangay', $barangay)
            ->where('quantity_dispensed', '>', 0)->whereDate('date_dispensed', '<', $currentStart->toDateString());
        $firstDates = (clone $query)->select('medicine_id')->selectRaw('MIN(date_dispensed) AS first_date')
            ->groupBy('medicine_id')->pluck('first_date', 'medicine_id');
        $medicines = Medicine::dispensable()->nonVaccine()->whereIn('id', $firstDates->keys())
            ->whereExists(function ($maps) {
                $maps->selectRaw('1')->from('disease_medicine_map')
                    ->join('diseases', 'diseases.id', '=', 'disease_medicine_map.disease_id')
                    ->whereColumn('disease_medicine_map.medicine_id', 'medicines.id')->where('diseases.is_active', true);
            })->orderBy('medicine_name')->orderBy('dosage')->get(['id', 'medicine_name', 'dosage', 'dosage_form', 'unit']);
        $related = DB::table('disease_medicine_map')->join('diseases', 'diseases.id', '=', 'disease_medicine_map.disease_id')
            ->whereIn('medicine_id', $medicines->modelKeys())->where('diseases.is_active', true)
            ->select('medicine_id', 'diseases.id', 'diseases.name')->distinct()->orderBy('diseases.name')->get()->groupBy('medicine_id');
        $totals = [];
        foreach ((clone $query)->whereIn('medicine_id', $medicines->modelKeys())
            ->whereDate('date_dispensed', '>=', $baselineStart->toDateString())
            ->get(['medicine_id', 'date_dispensed', 'quantity_dispensed']) as $record) {
            $key = $this->periodStart(CarbonImmutable::parse($record->date_dispensed), $periodType)->toDateString();
            $totals[$record->medicine_id][$key] = ($totals[$record->medicine_id][$key] ?? 0) + $record->quantity_dispensed;
        }

        $signals = [];
        $overview = ['medicines_reviewed' => $medicines->count(), 'high' => 0, 'new_demand' => 0, 'typical' => 0, 'insufficient_history' => 0];
        foreach ($medicines as $medicine) {
            $firstPeriod = $this->periodStart(CarbonImmutable::parse($firstDates[$medicine->id]), $periodType);
            $latest = $totals[$medicine->id][$latestStart->toDateString()] ?? 0;
            $baselineAverage = null;
            $change = null;
            $status = 'insufficient_history';
            if ($firstPeriod->lessThanOrEqualTo($baselineStart)) {
                $baselineTotal = 0;
                for ($index = 0; $index < 4; $index++) {
                    $baselineTotal += $totals[$medicine->id][$this->offset($baselineStart, $index, $periodType)->toDateString()] ?? 0;
                }
                $baselineAverage = $baselineTotal / 4;
                $status = 'typical';
                if ($baselineAverage > 0) {
                    $change = ($latest - $baselineAverage) / $baselineAverage * 100;
                    if ($latest > $baselineAverage && $latest >= $baselineAverage * 1.5) {
                        $status = 'high';
                    }
                } elseif ($latest > 0) {
                    $status = 'new_demand';
                }
            }
            $overview[$status]++;
            $signals[] = ['id' => $medicine->id, 'name' => $medicine->medicine_name, 'dosage' => $medicine->dosage,
                'dosage_form' => $medicine->dosage_form, 'unit' => $medicine->unit ?: 'units',
                'current_quantity' => $latest, 'baseline_average' => $baselineAverage, 'change_percent' => $change,
                'status' => $status, 'related_diseases' => ($related[$medicine->id] ?? collect())
                    ->map(fn ($disease) => ['id' => (int) $disease->id, 'name' => $disease->name])->values()->all()];
        }
        $priority = ['high' => 0, 'new_demand' => 1, 'insufficient_history' => 2, 'typical' => 3];
        usort($signals, fn ($left, $right) => ($priority[$left['status']] <=> $priority[$right['status']])
            ?: (($right['change_percent'] ?? 0) <=> ($left['change_percent'] ?? 0)) ?: strcmp($left['name'], $right['name']));
        $unassignedRecords = DispensingRecord::where('health_unit_id', $healthUnitId)->where('quantity_dispensed', '>', 0)
            ->whereDate('date_dispensed', '>=', $baselineStart->toDateString())->whereDate('date_dispensed', '<', $currentStart->toDateString())
            ->where(fn ($areas) => $areas->whereNull('barangay')->orWhereRaw("TRIM(barangay) = ''"))->count();

        return ['location' => $barangay, 'health_unit_id' => $healthUnitId, 'period_type' => $periodType,
            'period_start' => $latestStart->toDateString(), 'period_end' => $currentStart->subDay()->toDateString(),
            'baseline_start' => $baselineStart->toDateString(), 'baseline_end' => $latestStart->subDay()->toDateString(),
            'threshold_percent' => 50, 'signals' => $signals, 'overview' => $overview, 'unassigned_records' => $unassignedRecords];
    }

    private function periodStart(CarbonImmutable $date, string $periodType): CarbonImmutable
    {
        return $periodType === 'weekly' ? $date->startOfWeek(CarbonImmutable::MONDAY) : $date->startOfMonth();
    }

    private function offset(CarbonImmutable $date, int $offset, string $periodType): CarbonImmutable
    {
        return $periodType === 'weekly' ? $date->addWeeks($offset) : $date->addMonths($offset);
    }
}
