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

        $phoId = $isPHOAdmin ? HealthUnit::phoId() : null;
        $transactionScopeId = $isPHOAdmin ? ($phoId ?? -1) : $healthUnitId;
        $asOfDate = today()->toDateString();
        $todayOnly = ! $isPHOAdmin && session('role') === 'staff';
        $transactionDates = $todayOnly ? ['date_from' => $asOfDate, 'date_to' => $asOfDate] : ['date_to' => $asOfDate];
        $inTransactionPeriod = fn ($query, string $dateColumn) => $query->whereDate($dateColumn, '<=', $asOfDate)
            ->when($todayOnly, fn ($rows) => $rows->whereDate($dateColumn, '>=', $asOfDate));
        $stockLogs = $inTransactionPeriod(StockLog::activeForReporting(), 'date_added');
        $allocations = $inTransactionPeriod(Allocation::query(), 'date_allocated');
        $dispensing = $inTransactionPeriod(DispensingRecord::qualifying(), 'date_dispensed');

        $scopedInventory = fn () => Inventory::query()
            ->when(! $isPHOAdmin, fn ($q) => $q->where('inventory.health_unit_id', $healthUnitId));
        $nearExpiryFrom = today();
        $nearExpiryThrough = $nearExpiryFrom->copy()->addDays(Inventory::NEAR_EXPIRY_DAYS);
        $nearExpiry = $scopedInventory()->expiringWithin(from: $nearExpiryFrom);

        // ===================== STATS =====================
        $stats = [
            'low_stock_count' => $scopedInventory()->stockLevel('low')->count(),

            'near_expiry_count' => (clone $nearExpiry)->count(),

            'overstock_count' => $scopedInventory()->stockLevel('overstock')->count(),

            'stock_logs_count' => (clone $stockLogs)->where('health_unit_id', $transactionScopeId)->count(),

            'allocations_count' => $allocations->where('health_unit_id', $transactionScopeId)->count(),

            'dispensing_count' => $dispensing->where('health_unit_id', $transactionScopeId)->count(),

            'province_stock_logs_count' => $isPHOAdmin ? $stockLogs->count() : null,
            'province_saved_stock_logs_count' => $isPHOAdmin ? StockLog::whereDate('date_added', '<=', $asOfDate)->count() : null,

            'users_count' => User::where('is_emergency', false)->whereKeyNot($user->id)->count(),
            'facility_count' => HealthUnit::count(),
            'medicines_count' => Medicine::count(),

            'inventory_count' => (clone $scopedInventory())->count(),
            'usable_batches_count' => $scopedInventory()->stockLevel('available')->count(),
            'total_available_units' => (clone $scopedInventory())->sum('available_stock'),

            'rhus_count' => HealthUnit::where('facility_type', 'Rural Health Unit')
                ->orWhere('facility_type', 'RHU')
                ->count(),
        ];

        $transactionRecords = [];
        if (! $todayOnly) {
            $stockFilters = $isPHOAdmin ? ['rhu_filter' => 'pho'] : [];
            $transactionRecords[] = ['label' => 'Active stock log rows', 'count' => $stats['stock_logs_count'],
                'url' => route('stock_logs.index', $transactionDates + $stockFilters + ['status' => 'active'])];
        }
        $allocationFilters = $isPHOAdmin ? ['rhu_filter' => $transactionScopeId] : [];
        $transactionRecords[] = ['label' => 'Allocation records (all saved statuses)', 'count' => $stats['allocations_count'],
            'url' => route('reports.allocation', $transactionDates + $allocationFilters)];
        if (! $isPHOAdmin) {
            $transactionRecords[] = ['label' => 'Dispensing records', 'count' => $stats['dispensing_count'],
                'url' => route('dispensing.index', $transactionDates)];
        }

        // ===================== LOW STOCK ITEMS =====================
        $lowStockItems = $scopedInventory()->stockLevel('low')
            ->with(['medicine', 'healthUnit'])
            ->orderBy('inventory.available_stock', 'asc')
            ->orderBy('inventory.id')
            ->paginate(10, ['inventory.*'], 'low_stock_page')
            ->withQueryString()->fragment('dashboard-low-stock');

        // ===================== NEAR EXPIRY ITEMS =====================
        $nearExpiryItems = $nearExpiry
            ->with(['medicine', 'healthUnit'])
            ->fefo()
            ->paginate(10, ['*'], 'near_expiry_page')
            ->withQueryString()->fragment('dashboard-near-expiry');

        // ===================== RECENT STOCK LOGS =====================
        $recentFrom = today()->subDays(29)->toDateString();
        $stockActivity = StockLog::activeForReporting()->with(['medicine', 'healthUnit'])
            ->when(! $isPHOAdmin, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->whereDate('date_added', '>=', $recentFrom)
            ->whereDate('date_added', '<=', $asOfDate)
            ->orderBy('date_added', 'desc')
            ->orderByDesc('id');
        $recentStockLogs = (clone $stockActivity)->with('addedByUser')
            ->paginate(10, ['*'], 'stock_logs_page')
            ->withQueryString()->fragment('dashboard-stock-activity');

        // ===================== RECENT DISPENSING =====================
        $dispensingActivity = DispensingRecord::qualifying()->with(['medicine', 'healthUnit'])
            ->when(! $isPHOAdmin, fn ($q) => $q->where('health_unit_id', $healthUnitId))
            ->whereDate('date_dispensed', '>=', $recentFrom)
            ->whereDate('date_dispensed', '<=', $asOfDate)
            ->orderBy('date_dispensed', 'desc')
            ->orderByDesc('id');
        $recentDispensing = (clone $dispensingActivity)
            ->paginate(10, ['*'], 'dispensing_page')
            ->withQueryString()->fragment('dashboard-dispensing');

        // The summary uses the dispensing record once, even when several batches were issued.
        $activityLimit = 6;
        $dispensingEntries = $dispensingActivity->limit($activityLimit)->get()->map(fn ($record) => [
            'action' => 'Dispensed',
            'medicine' => $record->medicine,
            'facility' => $record->healthUnit?->name,
            'quantity' => $record->quantity_dispensed,
            'date' => $record->date_dispensed,
        ]);
        $stockEntries = $stockActivity->whereNull('dispensing_record_id')
            ->where(fn ($movement) => $movement->where('movement_type', '<>', 'dispensing')
                ->orWhere(fn ($legacy) => $legacy->whereNull('movement_type')
                    ->whereRaw("LOWER(COALESCE(remarks, '')) NOT LIKE ?", ['dispensed%'])
                    ->whereRaw("LOWER(COALESCE(remarks, '')) NOT LIKE ?", ['released%dispensed%'])))
            ->limit($activityLimit)->get()->map(fn ($log) => [
                'action' => match ($log->movement_type) {
                    'receipt' => 'Stock received',
                    'transfer_in' => 'Transfer received',
                    'transfer_out' => 'Transfer sent',
                    'allocation' => 'Allocated',
                    'adjustment_in' => 'Stock increased',
                    'adjustment_out' => 'Stock reduced',
                    default => match ($log->movement_direction) {
                        'addition' => 'Stock added',
                        'release' => 'Stock released',
                        default => 'Stock movement',
                    },
                },
                'medicine' => $log->medicine,
                'facility' => $log->healthUnit?->name,
                'quantity' => $log->quantity_added,
                'date' => $log->date_added,
            ]);
        $recentActivity = $dispensingEntries->concat($stockEntries)->sortByDesc('date')->take($activityLimit);

        // ===================== CHART DATA =====================
        $analyticsFilters = request()->validate([
            'usage_days' => ['nullable', 'integer', 'in:30,90,180'],
            'usage_ranking' => ['nullable', 'in:usage,trend'],
        ]);
        $chartData = $this->dashboardService->getDashboardData($isPHOAdmin, $healthUnitId,
            (int) ($analyticsFilters['usage_days'] ?? 90), $analyticsFilters['usage_ranking'] ?? 'trend');

        $forecastSummary = $chartData['forecast'];

        $lowStockChart = $this->buildStockLevelChart('low', $healthUnitId);
        $overstockChart = $this->buildStockLevelChart('overstock', $healthUnitId);

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
                'user', 'stats', 'lowStockItems', 'nearExpiryItems', 'nearExpiryFrom', 'nearExpiryThrough',
                'recentActivity', 'recentStockLogs', 'recentDispensing', 'recentFrom', 'asOfDate', 'todayOnly', 'transactionRecords',
                'navLinks', 'isPHOAdmin', 'forecastSummary'
            ),
            ['chartDataJson' => $chartDataJson]
        ));
    }

    private function buildStockLevelChart(string $level, ?int $healthUnitId): array
    {
        $query = Inventory::query()->stockLevel($level);
        if ($healthUnitId === null) {
            $rows = $query->select('inventory.health_unit_id', DB::raw('COUNT(*) as count'))
                ->groupBy('inventory.health_unit_id')->orderByDesc('count')->orderBy('inventory.health_unit_id')->limit(10)->get();
            $facilityNames = HealthUnit::whereIn('id', $rows->pluck('health_unit_id'))->pluck('name', 'id');
            $labels = $rows->map(fn ($row) => $facilityNames[$row->health_unit_id] ?? 'Unknown facility');
        } else {
            $rows = $query->join('medicines', 'medicines.id', '=', 'inventory.medicine_id')
                ->where('inventory.health_unit_id', $healthUnitId)
                ->select('medicines.category', DB::raw('COUNT(*) as count'))
                ->groupBy('medicines.category')->orderByDesc('count')->orderBy('medicines.category')->get();
            $labels = $rows->map(fn ($row) => $this->humanizeCategory($row->category));
        }

        return ['labels' => $labels->values()->all(), 'data' => $rows->pluck('count')->values()->all()];
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
