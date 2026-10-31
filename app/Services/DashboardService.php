<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\StockLog;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private bool $isPHOAdmin = false;

    private ?int $healthUnitId = null;

    public function getDashboardData(bool $isPHOAdmin = false, ?int $healthUnitId = null): array
    {
        $this->isPHOAdmin = $isPHOAdmin;
        $this->healthUnitId = $healthUnitId;

        return [
            'summary' => $this->getSummary(),
            'utilization' => $this->getUtilizationData(),
            'stockMovement' => $this->getStockMovementData(),
            'expirationRisk' => $this->getExpirationRiskData(),
            'redistribution' => $this->getRedistributionData(),
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
        $lowStock = (clone $scoped())->where('available_stock', '<=', 10)
            ->where('available_stock', '>', 0)->count();
        $nearExpiry = (clone $scoped())->where('expiration_date', '<=', now()->addDays(30))
            ->where('expiration_date', '>', now())
            ->where('available_stock', '>', 0)
            ->count();

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
    private function getUtilizationData(): array
    {
        $windowDays = 90;

        $records = DispensingRecord::query()
            ->join('medicines', 'medicines.id', '=', 'dispensing_records.medicine_id')
            ->whereNull('medicines.deleted_at')
            ->where('dispensing_records.date_dispensed', '>=', now()->subDays($windowDays))
            ->when(! $this->isPHOAdmin, function ($q) {
                $q->where('dispensing_records.health_unit_id', $this->healthUnitId);
            })
            ->select(
                'medicines.id as medicine_id',
                'medicines.medicine_name',
                'medicines.dosage',
                'medicines.category',
                DB::raw('SUM(dispensing_records.quantity_dispensed) as total_quantity')
            )
            ->groupBy('medicines.id', 'medicines.medicine_name', 'medicines.dosage', 'medicines.category')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        $medicineIds = $records->pluck('medicine_id')->all();

        $grandTotal = (int) DispensingRecord::query()
            ->where('date_dispensed', '>=', now()->subDays($windowDays))
            ->when(! $this->isPHOAdmin, fn ($q) => $q->where('health_unit_id', $this->healthUnitId))
            ->sum('quantity_dispensed');

        $top10Total = (int) $records->sum('total_quantity');

        $trends = [];
        if (! empty($medicineIds)) {
            $last30 = DispensingRecord::query()
                ->whereIn('medicine_id', $medicineIds)
                ->where('date_dispensed', '>=', now()->subDays(30))
                ->when(! $this->isPHOAdmin, fn ($q) => $q->where('health_unit_id', $this->healthUnitId))
                ->select('medicine_id', DB::raw('SUM(quantity_dispensed) as qty'))
                ->groupBy('medicine_id')
                ->pluck('qty', 'medicine_id')
                ->all();

            $prev30 = DispensingRecord::query()
                ->whereIn('medicine_id', $medicineIds)
                ->whereBetween('date_dispensed', [now()->subDays(60), now()->subDays(30)])
                ->when(! $this->isPHOAdmin, fn ($q) => $q->where('health_unit_id', $this->healthUnitId))
                ->select('medicine_id', DB::raw('SUM(quantity_dispensed) as qty'))
                ->groupBy('medicine_id')
                ->pluck('qty', 'medicine_id')
                ->all();

            foreach ($medicineIds as $id) {
                $l = (int) ($last30[$id] ?? 0);
                $p = (int) ($prev30[$id] ?? 0);

                if ($p === 0 && $l === 0) {
                    $pct = 0;
                } elseif ($p === 0) {
                    $pct = null;
                } else {
                    $pct = (int) round((($l - $p) / $p) * 100);
                }

                $trends[$id] = ['last_30d' => $l, 'prev_30d' => $p, 'change_pct' => $pct];
            }
        }

        $topRhus = [];
        if ($this->isPHOAdmin && ! empty($medicineIds)) {
            $rows = DispensingRecord::query()
                ->join('health_units', 'health_units.id', '=', 'dispensing_records.health_unit_id')
                ->whereIn('dispensing_records.medicine_id', $medicineIds)
                ->where('dispensing_records.date_dispensed', '>=', now()->subDays($windowDays))
                ->select(
                    'dispensing_records.medicine_id',
                    'health_units.name as rhu_name',
                    DB::raw('SUM(dispensing_records.quantity_dispensed) as qty')
                )
                ->groupBy('dispensing_records.medicine_id', 'health_units.name')
                ->orderByDesc('qty')
                ->get();

            foreach ($rows as $row) {
                if (! isset($topRhus[$row->medicine_id])) {
                    $topRhus[$row->medicine_id] = $row->rhu_name;
                }
            }
        }

        $details = $records->map(function ($r) use ($trends, $topRhus, $top10Total) {
            $id = $r->medicine_id;
            $trend = $trends[$id] ?? ['last_30d' => 0, 'prev_30d' => 0, 'change_pct' => 0];

            return [
                'medicine_id' => $id,
                'medicine_name' => $r->medicine_name,
                'dosage' => $r->dosage,
                'category' => $r->category,
                'total_90d' => (int) $r->total_quantity,
                'share_pct' => $top10Total > 0
                                    ? round(($r->total_quantity / $top10Total) * 100, 1)
                                    : 0,
                'last_30d' => $trend['last_30d'],
                'prev_30d' => $trend['prev_30d'],
                'change_pct' => $trend['change_pct'],
                'top_rhu' => $topRhus[$id] ?? null,
            ];
        })->all();

        return [
            'labels' => $records->pluck('medicine_name')->all(),
            'data' => $records->pluck('total_quantity')->map(fn ($q) => (int) $q)->all(),
            'details' => $details,
            'summary' => [
                'top_units' => $top10Total,
                'grand_total' => $grandTotal,
                'top_share_pct' => $grandTotal > 0
                                    ? (int) round(($top10Total / $grandTotal) * 100)
                                    : 0,
                'window_days' => $windowDays,
                'is_pho' => $this->isPHOAdmin,
            ],
        ];
    }

    private function getStockMovementData(): array
    {
        $months = [];
        $additions = [];
        $releases = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months[] = $month->format('M Y');

            $additionsCount = StockLog::query()
                ->when(! $this->isPHOAdmin, fn ($q) => $q->where('health_unit_id', $this->healthUnitId))
                ->where(function ($q) {
                    $q->whereNull('remarks')
                        ->orWhere('remarks', 'like', 'Added%')
                        ->orWhere('remarks', 'like', 'Stock%');
                })
                ->whereYear('date_added', $month->year)
                ->whereMonth('date_added', $month->month)
                ->sum('quantity_added');

            $releasesCount = StockLog::query()
                ->when(! $this->isPHOAdmin, fn ($q) => $q->where('health_unit_id', $this->healthUnitId))
                ->where(function ($q) {
                    $q->where('remarks', 'like', 'Released%')
                        ->orWhere('remarks', 'like', 'Dispensed%')
                        ->orWhere('remarks', 'like', 'Allocated%');
                })
                ->whereYear('date_added', $month->year)
                ->whereMonth('date_added', $month->month)
                ->sum('quantity_added');

            $additions[] = (int) $additionsCount;
            $releases[] = (int) $releasesCount;
        }

        return [
            'months' => $months,
            'additions' => $additions,
            'releases' => $releases,
        ];
    }

    private function getExpirationRiskData(): array
    {
        $now = now();

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
            ->get();

        foreach ($inventories as $inventory) {
            if (! $inventory->expiration_date) {
                $categories['No expiry']++;

                continue;
            }

            $daysDiff = $now->diffInDays($inventory->expiration_date, false);

            if ($daysDiff < 0) {
                continue;
            } elseif ($daysDiff <= 30) {
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

    private function getRedistributionData(): array
    {
        $allocations = Allocation::query()
            ->where('date_allocated', '>=', now()->subMonths(3))
            ->when(! $this->isPHOAdmin, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('health_unit_id', $this->healthUnitId)
                        ->orWhere('destination_health_unit_id', $this->healthUnitId);
                });
            })
            ->select('destination_health_unit_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('destination_health_unit_id')
            ->groupBy('destination_health_unit_id')
            ->with('destinationHealthUnit')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        return [
            'labels' => $allocations->map(fn ($a) => $a->destinationHealthUnit->name ?? 'Unknown')->all(),
            'data' => $allocations->pluck('count')->map(fn ($c) => (int) $c)->all(),
        ];
    }

    private function getLowStockByRHU(): array
    {
        if ($this->isPHOAdmin) {
            $lowStockItems = Inventory::with('healthUnit')
                ->where('available_stock', '<=', 10)
                ->where('available_stock', '>', 0)
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

        $rows = Inventory::query()
            ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
            ->where('inventory.health_unit_id', $this->healthUnitId)
            ->where('inventory.available_stock', '<=', 10)
            ->where('inventory.available_stock', '>', 0)
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

        return [
            'shortage' => (int) ($summary['forecast_shortage'] ?? 0),
            'low_stock_items' => (int) ($summary['low_stock_items'] ?? 0),
            'total_items' => (int) ($summary['total_items'] ?? 0),
            'scope' => $this->isPHOAdmin ? 'province' : 'rhu',
        ];
    }
}
