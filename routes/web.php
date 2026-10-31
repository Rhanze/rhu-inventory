<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AllocationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiseaseReportController;
use App\Http\Controllers\DispensingController;
use App\Http\Controllers\ELMISExportController;
use App\Http\Controllers\FamilyPlanningController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\GisController;
use App\Http\Controllers\ImmunizationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\MidwifeDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Patient360Controller;
use App\Http\Controllers\PostpartumCareController as PostpartumController;
use App\Http\Controllers\PrenatalController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PublicUserController;
use App\Http\Controllers\RedistributionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RhuController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StockLogController;
use App\Http\Controllers\StockLookupController;
use App\Http\Controllers\SupplyRequestController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VaccinationForecastController;
use App\Http\Middleware\CheckSessionRole;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureNotMidwife;
use Illuminate\Support\Facades\Route;

// ============================================================
// 1. PUBLIC ROUTES (no login required)
// ============================================================

Route::domain(config('portals.enabled') ? config('portals.public_domain') : null)->group(function () {
    Route::view('/privacy', 'pages.privacy')->name('privacy');
    Route::view('/terms', 'pages.terms')->name('terms');
    Route::view('/accessibility', 'pages.accessibility')->name('accessibility');

    if (config('portals.enabled')) {
        Route::get('/', [PublicController::class, 'home'])->name('public.home');
    } else {
        Route::view('/', 'public.landing')->name('public.home');
    }

    Route::get('/public', [PublicController::class, 'home'])->name('public.home.citizen');
    Route::get('/medicine/{id}', [PublicController::class, 'show'])
        ->where('id', '[0-9]+')
        ->name('public.medicine.show');

    Route::get('/search/public-autocomplete', [PublicController::class, 'autocomplete'])->name('public.autocomplete');
});

Route::domain(config('portals.enabled') ? config('portals.management_domain') : null)->group(function () {
    if (config('portals.enabled')) {
        Route::get('/', fn () => redirect()->route(session('user_id') ? 'dashboard' : 'login'))->name('management.home');
    }
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/internal/scheduler', SchedulerController::class)->name('internal.scheduler');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    // ============================================================
    // 2. AUTHENTICATED ROUTES (admin + staff + midwife)
    // ============================================================
    Route::middleware(CheckSessionRole::class)->group(function () {

        Route::get('/public-users/search-suggestions', [PublicUserController::class, 'searchSuggestions'])->name('public_users.search.suggestions');

        Route::get('/dashboard', function () {
            if (session('role') === 'midwife') {
                return redirect()->route('dashboard.midwife');
            }

            return app(DashboardController::class)->index();
        })->name('dashboard');

        Route::get('/midwife/dashboard', [MidwifeDashboardController::class, 'index'])
            ->name('dashboard.midwife');

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/search', [SearchController::class, 'index'])->name('search.index');
        Route::get('/search/autocomplete', [SearchController::class, 'autocomplete'])->name('search.autocomplete');

        // ========== Medicines ==========
        Route::get('/medicine', [MedicineController::class, 'index'])->name('medicine.index');
        Route::get('/medicine/stock-on-hand', [MedicineController::class, 'stockOnHand'])->name('medicine.stock_on_hand');
        Route::get('/medicine/stock-on-hand/csv', [MedicineController::class, 'stockOnHandCsv'])->name('medicine.stock_on_hand.csv');
        Route::get('/medicine/export/csv', [MedicineController::class, 'exportCsv'])->name('medicine.export');

        // ========== Dispensing ==========
        Route::get('/dispensing', [DispensingController::class, 'index'])->name('dispensing.index');
        Route::get('/dispensing/create', [DispensingController::class, 'create'])->name('dispensing.create');
        Route::post('/dispensing/store', [DispensingController::class, 'store'])->name('dispensing.store');
        Route::get('/dispensing/edit/{id}', [DispensingController::class, 'edit'])->name('dispensing.edit');
        Route::put('/dispensing/update/{id}', [DispensingController::class, 'update'])->name('dispensing.update');
        Route::delete('/dispensing/delete/{id}', [DispensingController::class, 'destroy'])->name('dispensing.destroy');
        Route::get('/dispensing/export/csv', [DispensingController::class, 'exportCsv'])->name('dispensing.export');

        Route::get('/dispensing/{id}/prescription', [DispensingController::class, 'showPrescription'])
            ->middleware('throttle:60,1')
            ->name('dispensing.prescription');

        // ========== Public Users ==========
        Route::get('/public-users', [PublicUserController::class, 'index'])->name('public_users.index');
        Route::get('/public-users/create', [PublicUserController::class, 'create'])->name('public_users.create');
        Route::post('/public-users', [PublicUserController::class, 'store'])->name('public_users.store');
        Route::get('/public-users/{id}/edit', [PublicUserController::class, 'edit'])->name('public_users.edit');
        Route::put('/public-users/{id}', [PublicUserController::class, 'update'])->name('public_users.update');
        Route::delete('/public-users/{id}', [PublicUserController::class, 'destroy'])->name('public_users.destroy');

        // ========== Patient 360 ==========
        Route::get('/patients/{id}', [Patient360Controller::class, 'show'])
            ->where('id', '[0-9]+')
            ->name('patients.show');

        // ========== Notifications ==========
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
            Route::get('/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
            Route::post('/mark-read/{id}', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
            Route::delete('/delete-all', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
            Route::delete('/delete/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
            Route::post('/archive/{id}', [NotificationController::class, 'archive'])->name('notifications.archive');
            Route::post('/unarchive/{id}', [NotificationController::class, 'unarchive'])->name('notifications.unarchive');
            Route::delete('/delete-archived', [NotificationController::class, 'destroyArchived'])->name('notifications.destroy-archived');
        });

        // ========== Disease Reports ==========
        Route::prefix('disease-reports')->group(function () {
            Route::get('/', [DiseaseReportController::class, 'index'])->name('disease_reports.index');
            Route::post('/', [DiseaseReportController::class, 'store'])->name('disease_reports.store');
            Route::put('/{id}', [DiseaseReportController::class, 'update'])->name('disease_reports.update');
            Route::get('/chart-data', [DiseaseReportController::class, 'chartData'])->name('disease_reports.chart_data');
            Route::get('/export', [DiseaseReportController::class, 'exportCsv'])->name('disease_reports.export');
            Route::delete('/{id}', [DiseaseReportController::class, 'destroy'])->name('disease_reports.destroy');

            Route::post('/close-outbreak/{diseaseId}', [DiseaseReportController::class, 'closeOutbreak'])
                ->name('disease_reports.close_outbreak');
        });

        // ========== Immunization ==========
        Route::prefix('immunization')->group(function () {
            Route::get('/tcl', [ImmunizationController::class, 'tcl'])
                ->name('immunization.tcl');

            Route::post('/record', [ImmunizationController::class, 'store'])
                ->name('immunization.store');

            Route::get('/history/{patient}', [ImmunizationController::class, 'history'])
                ->name('immunization.history');
        });

        // ========== Vaccination ==========
        Route::prefix('vaccination')->group(function () {
            Route::get('/forecast', [VaccinationForecastController::class, 'index'])
                ->name('vaccination.forecast');

            Route::get('/calendar', [VaccinationForecastController::class, 'calendar'])
                ->name('vaccination.calendar');

            Route::get('/child/{patient}', [VaccinationForecastController::class, 'childSchedule'])
                ->name('vaccination.child');

            Route::post('/regenerate', [VaccinationForecastController::class, 'regenerate'])
                ->name('vaccination.regenerate');

            Route::get('/monthly', [VaccinationForecastController::class, 'monthly'])
                ->name('vaccination.monthly');

            Route::get('/monthly/export', [VaccinationForecastController::class, 'monthlyExport'])
                ->name('vaccination.monthly.export');

            Route::post('/session-pattern', [VaccinationForecastController::class, 'updateSessionPattern'])
                ->name('vaccination.session_pattern');

            Route::post('/generate-epi-days', [VaccinationForecastController::class, 'generateEpiDays'])
                ->name('vaccination.generate_epi_days');

            Route::post('/clear-epi-days', [VaccinationForecastController::class, 'clearEpiDays'])
                ->name('vaccination.clear_epi_days');

            // ── Per-area EPI schedules ──
            Route::post('/epi-schedule', [VaccinationForecastController::class, 'storeEpiSchedule'])
                ->name('vaccination.epi_schedule.store');
            Route::delete('/epi-schedule/{id}', [VaccinationForecastController::class, 'destroyEpiSchedule'])
                ->name('vaccination.epi_schedule.destroy');
        });

        // ========== Calendar Events ==========
        Route::prefix('calendar-events')->group(function () {
            Route::post('/', [CalendarEventController::class, 'store'])->name('calendar_events.store');
            Route::put('/{id}', [CalendarEventController::class, 'update'])->name('calendar_events.update');
            Route::delete('/{id}', [CalendarEventController::class, 'destroy'])->name('calendar_events.destroy');
        });

        // ========== Maternal Care ==========
        Route::prefix('prenatal')->group(function () {
            Route::get('/', [PrenatalController::class, 'index'])->name('prenatal.index');
            Route::post('/', [PrenatalController::class, 'store'])->name('prenatal.store');
            Route::delete('/{id}', [PrenatalController::class, 'destroy'])->name('prenatal.destroy');
        });

        Route::prefix('postpartum')->group(function () {
            Route::get('/', [PostpartumController::class, 'index'])->name('postpartum.index');
            Route::post('/', [PostpartumController::class, 'store'])->name('postpartum.store');
            Route::delete('/{id}', [PostpartumController::class, 'destroy'])->name('postpartum.destroy');
        });

        Route::prefix('family-planning')->group(function () {
            Route::get('/', [FamilyPlanningController::class, 'index'])->name('family_planning.index');
            Route::post('/', [FamilyPlanningController::class, 'store'])->name('family_planning.store');
            Route::delete('/{id}', [FamilyPlanningController::class, 'destroy'])->name('family_planning.destroy');
        });

        // ========== Supply Requests ==========
        Route::prefix('supply-requests')->group(function () {
            Route::get('/', [SupplyRequestController::class, 'index'])->name('supply_requests.index');
            Route::get('/create', [SupplyRequestController::class, 'create'])->name('supply_requests.create');
            Route::post('/', [SupplyRequestController::class, 'store'])->name('supply_requests.store');
            Route::delete('/{id}', [SupplyRequestController::class, 'destroy'])->name('supply_requests.destroy');
        });

        // ========== Allocation ==========
        Route::get('/allocation', [AllocationController::class, 'index'])->name('allocation.index');
        Route::get('/allocation/create', [AllocationController::class, 'create'])->name('allocation.create');
        Route::post('/allocation/store', [AllocationController::class, 'store'])->name('allocation.store');
        Route::get('/allocation/export/csv', [AllocationController::class, 'exportCsv'])->name('allocation.export');

        // ═══ NEW — Recipient confirms receipt of an allocation ═══
        // Open to any authenticated user. The controller enforces recipient ownership.
        Route::post('/allocation/{id}/receive', [AllocationController::class, 'markReceived'])
            ->name('allocation.mark_received');

        // ========== Transfers ==========
        Route::prefix('transfers')->group(function () {
            Route::get('/', [TransferController::class, 'index'])->name('transfers.index');
            Route::get('/create', [TransferController::class, 'create'])->name('transfers.create');
            Route::post('/store', [TransferController::class, 'store'])->name('transfers.store');
            Route::post('/{id}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
            Route::post('/{id}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
        });

        // ========== Account Settings ==========
        Route::get('/settings', [UserController::class, 'editOwn'])->name('settings');
        Route::post('/settings', [UserController::class, 'updateOwn']);

        // ============================================================
        // 2b. MIDWIFE-BLOCKED ROUTES (admin + staff only)
        // ============================================================
        Route::middleware(EnsureNotMidwife::class)->group(function () {

            Route::get('/dashboard/refresh', [DashboardController::class, 'refreshData'])->name('dashboard.refresh');

            Route::get('/medicine/create', [MedicineController::class, 'create'])->name('medicine.create');
            Route::post('/medicine/store', [MedicineController::class, 'store'])->name('medicine.store');
            Route::get('/medicine/edit/{id}', [MedicineController::class, 'edit'])->name('medicine.edit');
            Route::put('/medicine/update/{id}', [MedicineController::class, 'update'])->name('medicine.update');

            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
            Route::post('/inventory/store', [InventoryController::class, 'store'])->name('inventory.store');
            Route::get('/inventory/edit/{id}', [InventoryController::class, 'edit'])->name('inventory.edit');
            Route::put('/inventory/update/{id}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::delete('/inventory/delete/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
            Route::get('/inventory/export/csv', [InventoryController::class, 'exportCsv'])->name('inventory.export');

            Route::get('/stock-logs', [StockLogController::class, 'index'])->name('stock_logs.index');
            Route::get('/stock-logs/create', [StockLogController::class, 'create'])->name('stock_logs.create');
            Route::post('/stock-logs/store', [StockLogController::class, 'store'])->name('stock_logs.store');
            Route::get('/stock-logs/export/csv', [StockLogController::class, 'exportCsv'])->name('stock_logs.export');
            Route::get('/stock-logs/autocomplete', [StockLogController::class, 'autocompleteMedicines'])->name('stock_logs.autocomplete');

            Route::get('/allocation/edit/{id}', [AllocationController::class, 'edit'])->name('allocation.edit');
            Route::put('/allocation/update/{id}', [AllocationController::class, 'update'])->name('allocation.update');
            Route::delete('/allocation/delete/{id}', [AllocationController::class, 'destroy'])->name('allocation.destroy');
            Route::get('/allocation/province', [AllocationController::class, 'createProvincialAllocation'])->name('allocation.province');
            Route::post('/allocation/province', [AllocationController::class, 'storeProvincialAllocation'])->name('allocation.province.store');

            // ── Supply request approval (RHU admin acts on midwife requests) ──
            Route::post('/allocation/{id}/approve-supply-request',
                [AllocationController::class, 'approveSupplyRequest'])
                ->name('allocation.approve_supply_request');

            Route::post('/allocation/{id}/reject-supply-request',
                [AllocationController::class, 'rejectSupplyRequest'])
                ->name('allocation.reject_supply_request');

            Route::post('/transfers/{id}/accept', [TransferController::class, 'accept'])->name('transfers.accept');
            Route::post('/transfers/{id}/reject', [TransferController::class, 'reject'])->name('transfers.reject');
            Route::get('/transfers/distribute', [TransferController::class, 'distribute'])->name('transfers.distribute');
            Route::post('/transfers/distribute', [TransferController::class, 'distributeStore'])->name('transfers.distribute.store');

            Route::prefix('stock-lookup')->group(function () {
                Route::get('/', [StockLookupController::class, 'index'])->name('stock_lookup.index');
                Route::get('/search', [StockLookupController::class, 'search'])->name('stock_lookup.search');
                Route::post('/request', [StockLookupController::class, 'requestTransfer'])->name('stock_lookup.request');
            });

            Route::get('/reports', function () {
                return redirect()->route('reports.inventory');
            })->name('reports.index');
            Route::get('/reports/inventory', [ReportController::class, 'inventoryReport'])->name('reports.inventory');
            Route::get('/reports/stock-logs', [ReportController::class, 'stockLogsReport'])->name('reports.stock');
            Route::get('/reports/allocations', [ReportController::class, 'allocationReport'])->name('reports.allocation');
            Route::get('/reports/dispensing', [ReportController::class, 'dispensingReport'])->name('reports.dispensing');
            Route::get('/reports/near-expiry', [ReportController::class, 'nearExpiryReport'])->name('reports.near_expiry');
            Route::get('/reports/inventory/csv', [ReportController::class, 'inventoryCsv'])->name('reports.inventory.csv');
            Route::get('/reports/stock-logs/csv', [ReportController::class, 'stockLogsCsv'])->name('reports.stock.csv');
            Route::get('/reports/allocations/csv', [ReportController::class, 'allocationCsv'])->name('reports.allocation.csv');
            Route::get('/reports/dispensing/csv', [ReportController::class, 'dispensingCsv'])->name('reports.dispensing.csv');
            Route::get('/reports/near-expiry/csv', [ReportController::class, 'nearExpiryCsv'])->name('reports.near_expiry.csv');

            Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity_logs.index');
            Route::get('/activity-logs/export', [ActivityLogController::class, 'exportCsv'])->name('activity_logs.export');

            Route::prefix('redistribution')->group(function () {
                Route::get('/', [RedistributionController::class, 'index'])->name('redistribution.index');
                Route::get('/export', [RedistributionController::class, 'exportCsv'])->name('redistribution.export');
            });

            Route::get('/forecast', [ForecastController::class, 'index'])->name('forecast.index');

            Route::prefix('elmis')->group(function () {
                Route::get('/', [ELMISExportController::class, 'index'])->name('elmis.export');
                Route::get('/inventory', [ELMISExportController::class, 'exportInventory'])->name('elmis.export.inventory');
                Route::get('/movement', [ELMISExportController::class, 'exportStockMovement'])->name('elmis.export.movement');
            });
        });

        // ============================================================
        // 3. ADMIN-ONLY ROUTES
        // ============================================================
        Route::middleware(EnsureAdminRole::class)->group(function () {

            Route::get('/rhu', [RhuController::class, 'index'])->name('rhu.index');
            Route::get('/rhu/create', [RhuController::class, 'create'])->name('rhu.create');
            Route::post('/rhu/store', [RhuController::class, 'store'])->name('rhu.store');
            Route::get('/rhu/edit/{id}', [RhuController::class, 'edit'])->name('rhu.edit');
            Route::put('/rhu/update/{id}', [RhuController::class, 'update'])->name('rhu.update');
            Route::delete('/rhu/delete/{id}', [RhuController::class, 'destroy'])->name('rhu.destroy');

            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users/store', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/edit/{id}', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/update/{id}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/delete/{id}', [UserController::class, 'destroy'])->name('users.destroy');

            Route::delete('/medicine/delete/{id}', [MedicineController::class, 'destroy'])
                ->name('medicine.destroy');

            Route::get('/gis', [GisController::class, 'index'])->name('gis.index');
            Route::get('/gis/data', [GisController::class, 'data'])->name('gis.data');
            Route::get('/gis/category-count', [GisController::class, 'categoryCount'])->name('gis.category_count');
            Route::post('/gis/coordinates/{id}', [GisController::class, 'updateCoordinates'])->name('gis.coordinates.update');

            Route::prefix('inventory/pho-receive')->group(function () {
                Route::get('/', [InventoryController::class, 'phoReceive'])->name('inventory.pho_receive');
                Route::get('/create', [InventoryController::class, 'phoReceiveCreate'])->name('inventory.pho_receive.create');
                Route::post('/', [InventoryController::class, 'storePhoReceive'])->name('inventory.pho_receive.store');
                Route::get('/export/csv', [InventoryController::class, 'exportDohShipmentsCsv'])->name('inventory.pho_receive.export');
                Route::get('/{id}', [InventoryController::class, 'showDohShipment'])->name('inventory.pho_receive.show');
                Route::delete('/{id}', [InventoryController::class, 'destroyDohShipment'])->name('inventory.pho_receive.destroy');
            });
        });
    });
});
