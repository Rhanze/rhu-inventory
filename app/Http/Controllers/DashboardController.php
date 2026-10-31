<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function refreshData()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }
        $data = $this->index()->getData();

        return response()->json(['stats' => $data['stats'], 'charts' => json_decode($data['chartDataJson'], true)]);
    }

    protected DashboardService $dashboardService;

    protected NotificationService $notificationService;

    public function __construct(
        DashboardService $dashboardService,
        NotificationService $notificationService
    ) {
        $this->dashboardService = $dashboardService;
        $this->notificationService = $notificationService;
    }

    private function checkAccess()
    {
        if (! session()->has('user_id')) {
            return redirect()->route('login');
        }
        if (! in_array(session('role'), ['admin', 'staff'])) {
            return redirect()->route('login');
        }

        return null;
    }

    public function index()
    {
        if ($redirect = $this->checkAccess()) {
            return $redirect;
        }

        $user = User::find(session('user_id'));
        $healthUnitId = session('health_unit_id');
        $isPHOAdmin = is_null($healthUnitId);

        $defaultMinStock = (int) config('inventory.default_min_stock', 50);
        $defaultMaxStock = (int) config('inventory.default_max_stock', 500);

        $phoId = $isPHOAdmin ? HealthUnit::phoId() : null;
        $transactionScopeId = $isPHOAdmin ? $phoId : $healthUnitId;

        $scopedInventory = fn () => Inventory::query()
            ->when(! $isPHOAdmin, fn ($q) => $q->where('inventory.health_unit_id', $healthUnitId));

        // ===================== STATS =====================
        $stats = [
            'low_stock_count' => (clone $scopedInventory())
                ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
                ->whereNull('medicines.deleted_at')
                ->where('inventory.available_stock', '>', 0)
                ->whereRaw('inventory.available_stock <= COALESCE(medicines.min_stock, ?)', [$defaultMinStock])
                ->count(),

            'near_expiry_count' => (clone $scopedInventory())
                ->where('expiration_date', '<=', now()->addDays(180))
                ->where('expiration_date', '>', now())
                ->where('available_stock', '>', 0)
                ->count(),

            'overstock_count' => (clone $scopedInventory())
                ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
                ->whereNull('medicines.deleted_at')
                ->whereRaw('inventory.available_stock > COALESCE(medicines.max_stock, ?)', [$defaultMaxStock])
                ->count(),

            'stock_logs_count' => StockLog::query()
                ->when($transactionScopeId, fn ($q) => $q->where('health_unit_id', $transactionScopeId))
                ->count(),

            'allocations_count' => Allocation::query()
                ->when($transactionScopeId, fn ($q) => $q->where('health_unit_id', $transactionScopeId))
                ->count(),

            'dispensing_count' => DispensingRecord::query()
                ->when($transactionScopeId, fn ($q) => $q->where('health_unit_id', $transactionScopeId))
                ->count(),

            'users_count' => User::count(),
            'medicines_count' => Medicine::count(),

            'inventory_count' => (clone $scopedInventory())->count(),
            'total_available_units' => (clone $scopedInventory())->sum('available_stock'),

            'rhus_count' => HealthUnit::where('facility_type', 'Rural Health Unit')
                ->orWhere('facility_type', 'RHU')
                ->count(),
        ];

        // ===================== LOW STOCK ITEMS =====================
        $lowStockItems = Inventory::query()
            ->when(! $isPHOAdmin, fn ($q) => $q->where('inventory.health_unit_id', $healthUnitId))
            ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
            ->whereNull('medicines.deleted_at')
            ->where('inventory.available_stock', '>', 0)
            ->whereRaw('inventory.available_stock <= COALESCE(medicines.min_stock, ?)', [$defaultMinStock])
            ->with(['medicine', 'healthUnit'])
            ->orderBy('inventory.available_stock', 'asc')
            ->paginate(10, ['inventory.*'], 'low_stock_page')
            ->withQueryString()->fragment('dashboard-low-stock');

        // ===================== NEAR EXPIRY ITEMS =====================
        $nearExpiryItems = (clone $scopedInventory())
            ->with(['medicine', 'healthUnit'])
            ->where('expiration_date', '<=', now()->addDays(180))
            ->where('expiration_date', '>', now())
            ->where('available_stock', '>', 0)
            ->orderBy('expiration_date', 'asc')
            ->paginate(10, ['*'], 'near_expiry_page')
            ->withQueryString()->fragment('dashboard-near-expiry');

        // ===================== RECENT STOCK LOGS =====================
        $recentStockLogs = StockLog::with(['medicine', 'addedByUser', 'healthUnit'])
            ->when(! $isPHOAdmin, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->where('date_added', '>=', now()->subDays(30))
            ->orderBy('date_added', 'desc')
            ->paginate(10, ['*'], 'stock_logs_page')
            ->withQueryString()->fragment('dashboard-stock-activity');

        // ===================== RECENT DISPENSING =====================
        $recentDispensing = DispensingRecord::with(['medicine', 'healthUnit'])
            ->when(! $isPHOAdmin, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->where('date_dispensed', '>=', now()->subDays(30))
            ->orderBy('date_dispensed', 'desc')
            ->paginate(10, ['*'], 'dispensing_page')
            ->withQueryString()->fragment('dashboard-dispensing');

        // ===================== CHART DATA =====================
        $chartData = $this->dashboardService->getDashboardData($isPHOAdmin, $healthUnitId);

        $forecastSummary = $this->dashboardService->getForecastSummary();

        $lowStockChart = $this->buildLowStockChart($isPHOAdmin, $healthUnitId, $defaultMinStock);
        $overstockChart = $this->buildOverstockChart($isPHOAdmin, $healthUnitId, $defaultMaxStock);

        $chartDataJson = json_encode([
            'utilization' => $chartData['utilization'] ?? ['labels' => [], 'data' => []],
            'stockMovement' => $chartData['stockMovement'] ?? ['months' => [], 'additions' => [], 'releases' => []],
            'expiration' => $chartData['expirationRisk'] ?? ['labels' => ['0-30 days', '31-60 days', '61-90 days', '>90 days', 'No expiry'], 'data' => [0, 0, 0, 0, 0]],
            'lowStock' => $lowStockChart,
            'overstock' => $overstockChart,
            'redistribution' => $chartData['redistribution'] ?? ['labels' => [], 'data' => []],
        ]);

        // ===================== NAVIGATION LINKS =====================
        $distributionIcon = 'M17 1l4 4-4 4M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 0 1-4 4H3';

        if ($isPHOAdmin && session('role') === 'admin') {
            $navLinks = [
                ['medicine.index', 'Medicines/Vaccines', 'Manage medicine catalog', 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18'],
                ['stock_logs.index', 'Stock Logs', 'Track stock additions', 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M14 2l0 6 6 0'],
                ['inventory.index', 'Inventory', 'View all RHU stock', 'M2 3h20v14H2zM8 21h8M12 17v4'],
                ['allocation.index', 'Allocation', 'Allocate to RHUs', 'M22 12l-4 0-3 9-6-18-3 9-4 0'],
                ['dispensing.index', 'Dispensing', 'Dispense to patients', 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 11.5 5c-.57.55-1.08 1.15-1.5 1.79M5 10c-1.5 1.46-3 3.21-3 5.5a5.5 5.5 0 0 0 10.95 1'],
                ['transfers.index', 'Distribution', 'Distribute stock to RHUs', $distributionIcon],
                ['reports.index', 'Reports', 'Province‑wide reports', 'M18 20V10M12 20V4M6 20v-6'],
                ['activity_logs.index', 'Activity Logs', 'Track user actions', 'M12 12m-10 0a10 10 0 1 0 20 0 10 10 0 1 0-20 0M12 6v6l4 2'],
            ];
        } elseif (session('role') === 'staff') {
            $navLinks = [
                ['medicine.index', 'Medicines', 'Medicines & vaccines', 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18'],
                ['stock_logs.index', 'Stock Logs', 'Track stock additions', 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M14 2l0 6 6 0'],
                ['inventory.index', 'Inventory', 'RHU stock records', 'M2 3h20v14H2zM8 21h8M12 17v4'],
                ['allocation.index', 'Allocation', 'Allocate stock', 'M22 12l-4 0-3 9-6-18-3 9-4 0'],
                ['dispensing.index', 'Dispensing', 'Dispense to patients', 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 11.5 5c-.57.55-1.08 1.15-1.5 1.79M5 10c-1.5 1.46-3 3.21-3 5.5a5.5 5.5 0 0 0 10.95 1'],
                ['transfers.index', 'Transfer', 'Request & receive stock', $distributionIcon],
                ['forecast.index', 'Forecasting', 'Demand predictions', 'M3 3v18h18M7 14l4-4 4 4 4-4'],
                ['reports.index', 'Reports', 'Inventory & activity reports', 'M18 20V10M12 20V4M6 20v-6'],
                ['activity_logs.index', 'Activity Logs', 'Track user actions', 'M12 12m-10 0a10 10 0 1 0 20 0 10 10 0 1 0-20 0M12 6v6l4 2'],
            ];
        } else {
            $navLinks = [
                ['medicine.index', 'Medicines/Vaccines', 'Medicines & vaccines', 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18'],
                ['stock_logs.index', 'Stock Logs', 'Track stock additions', 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M14 2l0 6 6 0'],
                ['inventory.index', 'Inventory', 'RHU stock records', 'M2 3h20v14H2zM8 21h8M12 17v4'],
                ['allocation.index', 'Allocation', 'Allocate stock', 'M22 12l-4 0-3 9-6-18-3 9-4 0'],
                ['dispensing.index', 'Dispensing', 'Dispense to patients', 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 11.5 5c-.57.55-1.08 1.15-1.5 1.79M5 10c-1.5 1.46-3 3.21-3 5.5a5.5 5.5 0 0 0 10.95 1'],
                ['transfers.index', 'Transfer', 'Request & receive stock', $distributionIcon],
                ['forecast.index', 'Forecasting', 'Demand predictions', 'M3 3v18h18M7 14l4-4 4 4 4-4'],
                ['reports.index', 'Reports', 'Inventory & activity reports', 'M18 20V10M12 20V4M6 20v-6'],
            ];
        }

        return view('dashboard.index', array_merge(
            compact(
                'user', 'stats', 'lowStockItems', 'nearExpiryItems',
                'recentStockLogs', 'recentDispensing',
                'navLinks', 'isPHOAdmin', 'forecastSummary'
            ),
            ['chartDataJson' => $chartDataJson]
        ));
    }

    // ================================================================
    // LOW STOCK CHART
    // ================================================================
    private function buildLowStockChart(bool $isPHOAdmin, ?int $healthUnitId, int $defaultMinStock): array
    {
        if ($isPHOAdmin) {
            $phoId = HealthUnit::phoId();

            $rows = Inventory::query()
                ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
                ->whereNull('medicines.deleted_at')
                ->where('inventory.available_stock', '>', 0)
                ->whereRaw('inventory.available_stock <= COALESCE(medicines.min_stock, ?)', [$defaultMinStock])
                ->when($phoId, fn ($q) => $q->where('inventory.health_unit_id', '!=', $phoId))
                ->select('inventory.health_unit_id', DB::raw('COUNT(*) as count'))
                ->groupBy('inventory.health_unit_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            $rhuNames = HealthUnit::whereIn('id', $rows->pluck('health_unit_id'))
                ->pluck('name', 'id');

            return [
                'labels' => $rows->map(fn ($r) => $rhuNames[$r->health_unit_id] ?? 'Unknown RHU')->values()->all(),
                'data' => $rows->pluck('count')->values()->all(),
            ];
        }

        $rows = Inventory::query()
            ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
            ->where('inventory.health_unit_id', $healthUnitId)
            ->where('inventory.available_stock', '>', 0)
            ->whereRaw('inventory.available_stock <= COALESCE(medicines.min_stock, ?)', [$defaultMinStock])
            ->whereNull('medicines.deleted_at')
            ->select('medicines.category', DB::raw('COUNT(*) as count'))
            ->groupBy('medicines.category')
            ->orderByDesc('count')
            ->get();

        return [
            'labels' => $rows->map(fn ($r) => $this->humanizeCategory($r->category))->values()->all(),
            'data' => $rows->pluck('count')->values()->all(),
        ];
    }

    // ================================================================
    // OVERSTOCK CHART
    // ================================================================
    private function buildOverstockChart(bool $isPHOAdmin, ?int $healthUnitId, int $defaultMaxStock): array
    {
        if ($isPHOAdmin) {
            $phoId = HealthUnit::phoId();

            $rows = Inventory::query()
                ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
                ->whereNull('medicines.deleted_at')
                ->whereRaw('inventory.available_stock > COALESCE(medicines.max_stock, ?)', [$defaultMaxStock])
                ->when($phoId, fn ($q) => $q->where('inventory.health_unit_id', '!=', $phoId))
                ->select('inventory.health_unit_id', DB::raw('COUNT(*) as count'))
                ->groupBy('inventory.health_unit_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            $rhuNames = HealthUnit::whereIn('id', $rows->pluck('health_unit_id'))
                ->pluck('name', 'id');

            return [
                'labels' => $rows->map(fn ($r) => $rhuNames[$r->health_unit_id] ?? 'Unknown RHU')->values()->all(),
                'data' => $rows->pluck('count')->values()->all(),
            ];
        }

        $rows = Inventory::query()
            ->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
            ->where('inventory.health_unit_id', $healthUnitId)
            ->whereRaw('inventory.available_stock > COALESCE(medicines.max_stock, ?)', [$defaultMaxStock])
            ->whereNull('medicines.deleted_at')
            ->select('medicines.category', DB::raw('COUNT(*) as count'))
            ->groupBy('medicines.category')
            ->orderByDesc('count')
            ->get();

        return [
            'labels' => $rows->map(fn ($r) => $this->humanizeCategory($r->category))->values()->all(),
            'data' => $rows->pluck('count')->values()->all(),
        ];
    }

    private function humanizeCategory(?string $category): string
    {
        if (! $category) {
            return 'Uncategorized';
        }

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

        return $map[$category] ?? ucfirst(str_replace('_', ' ', $category));
    }
}
