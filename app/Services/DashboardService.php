<?php

namespace App\Services;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private bool $isPHOAdmin = false;

    private ?int $healthUnitId = null;

    public function getDashboardData(bool $isPHOAdmin = false, ?int $healthUnitId = null, int $usageDays = 90, string $usageRanking = 'trend'): array
    {
        $this->isPHOAdmin = $isPHOAdmin;
        $this->healthUnitId = $healthUnitId;

        return [
            'summary' => $this->getSummary(),
            'utilization' => $this->getUtilizationData($usageDays, $usageRanking),
            'stockMovement' => $this->getStockMovementData(),
            'expirationRisk' => $this->getExpirationRiskData(),
            'redistribution' => $this->getReceivedTransfersData(),
            'lowStockByRHU' => $this->getLowStockByRHU(),
            'forecast' => $this->getForecastSummary(),
        ];
    }

    private function getSummary(): array
    {
        $scoped = fn () => Inventory::query()
            ->when(! $this->isPHOAdmin, fn ($q) => $q->where('health_unit_id', $this->healthUnitId));

        $totalItems = (clone $scoped())->where('available_stock', '>', 0)->count();
        $totalStock = (clone $scoped())->sum('available_stock');
        $lowStock = $scoped()->stockLevel('low')->count();
        $nearExpiry = $scoped()->expiringWithin()->count();

        $totalRHUs = HealthUnit::where('facility_type', 'Rural Health Unit')
            ->orWhere('facility_type', 'RHU')
            ->count();

        return [
            'total_items' => $totalItems,
            'total_stock' => $totalStock,
            'low_stock' => $lowStock,
            'near_expiry' => $nearExpiry,
            'total_rhus' => $totalRHUs,
        ];
    }

    // ================================================================
    // Utilization — Top dispensed medicines (90-day window)
    // ================================================================
    private function getUtilizationData(int $windowDays = 90, string $ranking = 'trend'): array
    {
        $windowDays = in_array($windowDays, [30, 90, 180], true) ? $windowDays : 90;
        $ranking = $ranking === 'usage' ? 'usage' : 'trend';
        $today = today();
        $windowStart = $today->copy()->subDays($windowDays - 1)->toDateString();
        $currentStart = $today->copy()->subDays(29)->toDateString();
        $previousStart = $today->copy()->subDays(59)->toDateString();
        $previousEnd = $today->copy()->subDays(30)->toDateString();

        // Usage and projections share terminal-release evidence, including saved
        // transactions whose original stock logs are absent or only partial.
        $events = app(StockDemandHistoryService::class)->events(
            $this->isPHOAdmin ? null : [$this->healthUnitId], min($windowStart, $previousStart), $today->toDateString());
        $medicines = Medicine::whereIn('id', $events->pluck('medicine_id')->unique())->get()->keyBy('id');
        $facilities = HealthUnit::whereIn('id', $events->pluck('health_unit_id')->unique())->pluck('name', 'id');
        $rows = $events->filter(fn ($event) => $medicines->has($event->medicine_id) && $facilities->has($event->health_unit_id))
            ->groupBy(fn ($event) => $event->health_unit_id.':'.$event->medicine_id)
            ->map(function ($releases) use ($medicines, $facilities, $windowStart, $currentStart, $previousStart, $previousEnd) {
                $first = $releases->first();
                $medicine = $medicines[$first->medicine_id];
                $quantity = fn ($from, $to = null) => (int) $releases->filter(fn ($event) =>
                    $event->date_added->toDateString() >= $from && ($to === null || $event->date_added->toDateString() <= $to))->sum('quantity_added');

                return (object) ['medicine_id' => $medicine->id, 'health_unit_id' => $first->health_unit_id,
                    'medicine_name' => $medicine->medicine_name, 'dosage' => $medicine->dosage,
                    'category' => $medicine->category, 'unit' => $medicine->unit,
                    'rhu_name' => $facilities[$first->health_unit_id], 'total_quantity' => $quantity($windowStart),
                    'current_quantity' => $quantity($currentStart), 'previous_quantity' => $quantity($previousStart, $previousEnd)];
            })->values();
        $trends = app(StockTrendService::class)->analyze($rows->pluck('health_unit_id')->unique()->all());
        $unitKey = fn ($unit) => mb_strtolower(trim((string) $unit));
        $usageByUnit = $rows->groupBy(fn ($row) => $unitKey($row->unit))
            ->map(fn ($group) => (int) $group->sum('total_quantity'));
        $details = [];
        foreach ($rows->groupBy('medicine_id') as $medicineId => $facilities) {
            $medicine = $facilities->first();
            $current = (int) $facilities->sum('current_quantity');
            $previous = (int) $facilities->sum('previous_quantity');
            $total = (int) $facilities->sum('total_quantity');
            $unit = $unitKey($medicine->unit);
            $unitTotal = $usageByUnit->get($unit, 0);
            $forecast = 0.0;
            $forecastComplete = true;
            $limited = false;
            foreach ($facilities as $facility) {
                $projection = $trends[$facility->health_unit_id][$medicineId] ?? null;
                if ($projection === null || $projection['fit']['periods'] === 0) {
                    $forecastComplete = false;
                    continue;
                }
                $forecast += array_sum(array_slice($projection['daily'], 0, 30));
                $limited = $limited || $projection['fit']['method'] !== 'linear_regression';
            }
            $details[] = [
                'medicine_id' => (int) $medicineId, 'medicine_name' => $medicine->medicine_name,
                'dosage' => $medicine->dosage, 'category' => $medicine->category, 'unit' => $medicine->unit ?: 'units',
                'total_90d' => $total, 'total_usage' => $total,
                'share_pct' => $unit !== '' && $unitTotal > 0 ? round($total / $unitTotal * 100, 1) : null,
                'last_30d' => $current, 'prev_30d' => $previous,
                'change_pct' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : ($current > 0 ? null : 0),
                'forecast_30d' => $forecastComplete ? (int) ceil(round($forecast, 8)) : null,
                'forecast_basis' => ! $forecastComplete ? 'No completed release history' : ($limited ? 'Limited-history average' : 'Weekly release trend'),
                'top_rhu' => $this->isPHOAdmin ? $facilities->sortByDesc('total_quantity')->first()->rhu_name : null,
            ];
        }
        $details = array_values(array_filter($details, fn ($row) => $row['total_usage'] > 0 || ($row['forecast_30d'] ?? 0) > 0));
        usort($details, function ($left, $right) use ($ranking) {
            $metric = $ranking === 'trend' ? 'forecast_30d' : 'total_usage';

            return (($right[$metric] ?? -1) <=> ($left[$metric] ?? -1))
                ?: ($right['total_usage'] <=> $left['total_usage'])
                ?: strcmp($left['medicine_name'], $right['medicine_name']);
        });
        $details = array_slice($details, 0, 10);

        return [
            'labels' => array_column($details, 'medicine_name'),
            'data' => array_map(fn ($row) => $ranking === 'trend' ? $row['forecast_30d'] : $row['total_usage'], $details),
            'details' => $details,
            'summary' => ['window_days' => $windowDays, 'window_start' => $windowStart, 'window_end' => $today->toDateString(),
                'current_start' => $currentStart, 'current_end' => $today->toDateString(),
                'previous_start' => $previousStart, 'previous_end' => $previousEnd,
                'forecasted_items' => count(array_filter($details, fn ($row) => $row['forecast_30d'] !== null)),
                'ranking' => $ranking, 'is_pho' => $this->isPHOAdmin],
        ];
    }

    private function getStockMovementData(): array
    {
        $months = [];
        $additions = [];
        $releases = [];
        $today = today();
        $tomorrow = $today->copy()->addDay()->toDateString();
        $logs = StockLog::activeForReporting()->where('quantity_added', '>', 0)
            ->when(! $this->isPHOAdmin, fn ($query) => $query->where('health_unit_id', $this->healthUnitId));

        for ($i = 5; $i >= 0; $i--) {
            $month = $today->copy()->startOfMonth()->subMonths($i);
            $months[] = $month->format('M Y');

            $monthEnd = $month->copy()->addMonth()->toDateString();
            $monthlyLogs = (clone $logs)->where('date_added', '>=', $month->toDateString())
                ->where('date_added', '<', min($monthEnd, $tomorrow));
            $additions[] = (clone $monthlyLogs)->additions()->count();
            $releases[] = $monthlyLogs->releases()->count();
        }

        return [
            'months' => $months,
            'additions' => $additions,
            'releases' => $releases,
        ];
    }

    private function getExpirationRiskData(): array
    {
        $today = today();

        $categories = [
            '0-30 days' => 0,
            '31-60 days' => 0,
            '61-90 days' => 0,
            '>90 days' => 0,
            'No expiry' => 0,
        ];

        $inventories = Inventory::query()
            ->when(! $this->isPHOAdmin, fn ($q) => $q->where('health_unit_id', $this->healthUnitId))
            ->where('available_stock', '>', 0)
            ->whereHas('medicine')
            ->get();

        foreach ($inventories as $inventory) {
            if (! $inventory->expiration_date) {
                $categories['No expiry']++;

                continue;
            }

            $daysDiff = $today->diffInDays($inventory->expiration_date, false);

            if ($daysDiff < 0) {
                continue;
            } elseif ($daysDiff <= Inventory::NEAR_EXPIRY_DAYS) {
                $categories['0-30 days']++;
            } elseif ($daysDiff <= 60) {
                $categories['31-60 days']++;
            } elseif ($daysDiff <= 90) {
                $categories['61-90 days']++;
            } else {
                $categories['>90 days']++;
            }
        }

        return [
            'labels' => array_keys($categories),
            'data' => array_values($categories),
        ];
    }

    private function getReceivedTransfersData(): array
    {
        $facilityId = $this->isPHOAdmin ? HealthUnit::phoId() : $this->healthUnitId;
        if ($facilityId === null) {
            return ['labels' => [], 'data' => []];
        }

        $window = [today()->subMonthsNoOverflow(3), now()];
        $ownFacility = $this->isPHOAdmin ? 'from_health_unit_id' : 'to_health_unit_id';
        $otherFacility = $this->isPHOAdmin ? 'to_health_unit_id' : 'from_health_unit_id';
        $relation = $this->isPHOAdmin ? 'toHealthUnit' : 'fromHealthUnit';
        $transfers = TransferRequest::query()
            ->where($ownFacility, $facilityId)
            ->whereIn('status', ['received', 'completed'])
            ->where('auto_completed', false)
            ->where(fn ($query) => $query->whereBetween('received_at', $window)
                ->orWhere(fn ($legacy) => $legacy->whereNull('received_at')->whereBetween('completed_at', $window)))
            ->select($otherFacility, DB::raw('COUNT(*) as count'))
            ->groupBy($otherFacility)
            ->with($relation)
            ->orderByDesc('count')->orderBy($otherFacility)->limit(8)->get();

        return [
            'labels' => $transfers->map(fn ($transfer) => $transfer->{$relation}?->name ?? 'Unknown facility')->all(),
            'data' => $transfers->pluck('count')->map(fn ($count) => (int) $count)->all(),
        ];
    }

    private function getLowStockByRHU(): array
    {
        if ($this->isPHOAdmin) {
            $lowStockItems = Inventory::with('healthUnit')
                ->stockLevel('low')
                ->get();

            $rhuCounts = [];
            foreach ($lowStockItems as $item) {
                $rhuName = $item->healthUnit->name ?? 'Unknown';
                $rhuCounts[$rhuName] = ($rhuCounts[$rhuName] ?? 0) + 1;
            }

            arsort($rhuCounts);
            $limited = array_slice($rhuCounts, 0, 8, true);

            return [
                'labels' => array_keys($limited),
                'data' => array_values($limited),
            ];
        }

        $rows = Inventory::stockLevel('low')
            ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
            ->where('inventory.health_unit_id', $this->healthUnitId)
            ->whereNull('medicines.deleted_at')
            ->select('medicines.category', DB::raw('COUNT(*) as count'))
            ->groupBy('medicines.category')
            ->orderByDesc('count')
            ->get();

        $map = [
            'medicine' => 'Medicine',
            'vaccine' => 'Vaccine',
            'family_planning' => 'Family Planning',
            'family_health_nutrition' => 'Family Health & Nutrition',
            'emerging_reemerging' => 'Emerging & Re-emerging',
            'non_communicable' => 'Non-Communicable Disease',
            'dengue_prevention' => 'Dengue Prevention',
            'tuberculosis' => 'Tuberculosis',
        ];

        return [
            'labels' => $rows->map(fn ($r) => $map[$r->category] ?? ucfirst(str_replace('_', ' ', $r->category ?? 'Uncategorized')))->all(),
            'data' => $rows->pluck('count')->map(fn ($c) => (int) $c)->all(),
        ];
    }

    // ================================================================
    // Forecast summary
    // ================================================================
    public function getForecastSummary(): array
    {
        $forecastService = new ForecastService;

        $forecast = $forecastService->getForecast($this->healthUnitId);

        $summary = $forecast['summary'] ?? [];
        $horizonDays = (int) ($summary['horizon_days'] ?? config('forecasting.forecast_days', 90));

        return [
            'shortage' => (int) ($summary['forecast_shortage'] ?? 0),
            'shortage_items' => collect($forecast['items'] ?? [])->where('shortage', '>', 0)->count(),
            'low_stock_items' => (int) ($summary['low_stock_items'] ?? 0),
            'total_items' => (int) ($summary['total_items'] ?? 0),
            'horizon_days' => $horizonDays,
            'forecast_start' => today()->toDateString(),
            'forecast_end' => today()->addDays($horizonDays - 1)->toDateString(),
            'scope' => $this->isPHOAdmin ? 'province' : 'rhu',
        ];
    }
}
