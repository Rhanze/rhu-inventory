<?php

namespace App\Services;

use App\Models\Inventory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Trends in recorded patient dispensing, never inferred disease cases or actual adherence. */
class MedicineUsageTrendService
{
    public function __construct(private StockTrendModel $model) {}

    public function analyze(int $healthUnitId, ?int $dispenserId, int $medicineId, string $periodType = 'weekly', int $periods = 12, ?string $barangay = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        if (! in_array($periodType, ['weekly', 'monthly'], true)) {
            throw new \InvalidArgumentException('Usage periods must be weekly or monthly.');
        }
        if ($fromDate !== null || $toDate !== null) {
            return $this->historicalUsage($healthUnitId, $dispenserId, $medicineId, $periodType, $barangay, $fromDate, $toDate);
        }

        $periods = max(4, min(52, $periods));
        $currentStart = $this->periodStart(CarbonImmutable::today(), $periodType);
        $historyStart = $this->offset($currentStart, -$periods, $periodType);
        $query = app(DiseaseConsumptionTrendService::class)->recordsQuery($healthUnitId, $barangay, $dispenserId)
            ->where('medicine_id', $medicineId)
            ->when($dispenserId !== null, fn ($q) => $q->where('dispensed_by', $dispenserId))
            ->when($barangay !== null, fn ($q) => $q->where('barangay', $barangay))
            ->where('quantity_dispensed', '>', 0)
            ->whereDate('date_dispensed', '<', $currentStart->toDateString());
        $firstDate = (clone $query)->min('date_dispensed');
        $firstPeriod = $firstDate ? $this->periodStart(CarbonImmutable::parse($firstDate), $periodType) : null;
        $rows = (clone $query)->whereDate('date_dispensed', '>=', $historyStart->toDateString())
            ->get(['date_dispensed', 'quantity_dispensed']);
        $totals = [];
        foreach ($rows as $row) {
            $key = $this->periodStart(CarbonImmutable::parse($row->date_dispensed), $periodType)->toDateString();
            $totals[$key]['quantity'] = ($totals[$key]['quantity'] ?? 0) + $row->quantity_dispensed;
            $totals[$key]['transactions'] = ($totals[$key]['transactions'] ?? 0) + 1;
        }

        $history = [];
        for ($index = 0; $index < $periods; $index++) {
            $start = $this->offset($historyStart, $index, $periodType);
            $key = $start->toDateString();
            // A zero means no recorded dispensing after recording began, not confirmed zero demand.
            $quantity = $firstPeriod && $start->greaterThanOrEqualTo($firstPeriod) ? ($totals[$key]['quantity'] ?? 0) : null;
            $history[] = ['start' => $key, 'end' => $this->offset($start, 1, $periodType)->subDay()->toDateString(),
                'quantity' => $quantity, 'transactions' => $totals[$key]['transactions'] ?? 0];
        }

        $values = array_values(array_filter(array_column($history, 'quantity'), fn ($value) => $value !== null));
        $fit = null;
        $status = count($values) ? 'insufficient_history' : 'no_history';
        $direction = 'unavailable';
        if (count($values) >= max(4, (int) config('forecasting.minimum_trend_weeks', 4))) {
            $fit = $this->model->fit($values);
            $status = 'ready';
            $threshold = max(1e-9, array_sum($values) / count($values) * 0.05);
            $direction = abs($fit['slope']) <= $threshold ? 'stable' : ($fit['slope'] > 0 ? 'rising' : 'declining');
        }

        $forecast = [];
        for ($index = 0; $index < 4; $index++) {
            $start = $this->offset($currentStart, $index, $periodType);
            $forecast[] = ['start' => $start->toDateString(),
                'end' => $this->offset($start, 1, $periodType)->subDay()->toDateString(),
                // StockTrendModel's equation is equally applicable to equal calendar-period totals.
                'quantity' => $fit ? $this->model->weeklyPrediction($fit, $index + 1) : null];
        }

        $currentQuantity = $history[$periods - 1]['quantity'];
        $previousQuantity = $history[$periods - 2]['quantity'];
        $changeStatus = $currentQuantity === null || $previousQuantity === null ? 'unavailable'
            : ($previousQuantity > 0 ? 'comparable' : 'no_baseline');
        $stock = (int) Inventory::availableFefo($healthUnitId, $medicineId)->sum('available_stock');
        $forecastTotal = $fit ? array_sum(array_column($forecast, 'quantity')) : null;
        $relatedDiseases = DB::table('disease_medicine_map')->join('diseases', 'diseases.id', '=', 'disease_medicine_map.disease_id')
            ->where('disease_medicine_map.medicine_id', $medicineId)->select('diseases.id', 'diseases.name')->distinct()->orderBy('diseases.name')
            ->get()->map(fn ($disease) => ['id' => (int) $disease->id, 'name' => $disease->name])->all();

        return ['period_type' => $periodType, 'scope' => $barangay !== null ? 'area' : ($dispenserId === null ? 'rhu' : 'personal'),
            'location' => $barangay,
            'history' => $history, 'forecast' => $forecast, 'status' => $status, 'direction' => $direction,
            'fit' => $fit, 'observations' => count($values), 'total_quantity' => array_sum($values),
            'total_transactions' => array_sum(array_column($history, 'transactions')),
            'current_quantity' => $currentQuantity, 'previous_quantity' => $previousQuantity,
            'change_percent' => $changeStatus === 'comparable' ? ($currentQuantity - $previousQuantity) / $previousQuantity * 100 : null,
            'change_status' => $changeStatus, 'usable_stock' => $stock, 'forecast_total' => $forecastTotal,
            'suggested_replenishment' => $barangay === null && $dispenserId === null && $forecastTotal !== null ? max(0, (int) ceil($forecastTotal) - $stock) : null,
            'related_diseases' => $relatedDiseases];
    }

    private function historicalUsage(int $healthUnitId, ?int $dispenserId, int $medicineId, string $type, ?string $barangay, ?string $fromDate, ?string $toDate): array
    {
        $to = CarbonImmutable::parse($toDate ?? today()->toDateString())->min(CarbonImmutable::today())->toDateString();
        $query = app(DiseaseConsumptionTrendService::class)->recordsQuery($healthUnitId, $barangay, $dispenserId)->where('medicine_id', $medicineId)
            ->when($dispenserId !== null, fn ($q) => $q->where('dispensed_by', $dispenserId))
            ->when($barangay !== null, fn ($q) => $q->where('barangay', $barangay))
            ->where('quantity_dispensed', '>', 0)->whereDate('date_dispensed', '<=', $to);
        $firstDate = (clone $query)->min('date_dispensed');
        $window = app(DiseasePeriodService::class)->historicalWindow($type, $fromDate ?? $firstDate, $toDate);
        $firstPeriod = $firstDate ? $this->periodStart(CarbonImmutable::parse($firstDate), $type)->toDateString() : null;
        $rows = $window['periods'] ? (clone $query)->whereDate('date_dispensed', '>=', $window['from'])->get(['date_dispensed', 'quantity_dispensed']) : collect();
        $totals = [];
        foreach ($rows as $row) {
            $key = $this->periodStart(CarbonImmutable::parse($row->date_dispensed), $type)->toDateString();
            $totals[$key]['quantity'] = ($totals[$key]['quantity'] ?? 0) + $row->quantity_dispensed;
            $totals[$key]['transactions'] = ($totals[$key]['transactions'] ?? 0) + 1;
        }
        $history = [];
        foreach ($window['periods'] as $bounds) {
            $key = $bounds['start'];
            $quantity = $firstPeriod !== null && $key >= $firstPeriod ? ($totals[$key]['quantity'] ?? 0) : null;
            $history[] = $bounds + ['included_start' => max($bounds['start'], $window['from']),
                'included_end' => min($bounds['end'], $window['to']), 'quantity' => $quantity,
                'transactions' => $totals[$key]['transactions'] ?? 0];
        }

        return ['historical' => true, 'window' => $window, 'period_type' => $type,
            'scope' => $barangay !== null ? 'area' : ($dispenserId === null ? 'rhu' : 'personal'), 'location' => $barangay,
            'history' => $history, 'forecast' => [], 'fit' => null, 'direction' => 'unavailable',
            'status' => $rows->isEmpty() ? 'no_history' : 'historical',
            'total_quantity' => (int) $rows->sum('quantity_dispensed'), 'total_transactions' => $rows->count(),
            'suggested_replenishment' => null];
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
