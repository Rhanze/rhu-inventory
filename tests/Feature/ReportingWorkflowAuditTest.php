<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DiseaseReport;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\DiseaseAnalysisService;
use App\Services\DiseasePeriodService;
use App\Services\ELMISExportService;
use App\Services\NotificationService;
use App\Services\RedistributionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'admin'): array
    {
        $unit = HealthUnit::create(['name' => 'Audit RHU', 'facility_type' => 'Rural Health Unit']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => $role]);
        $medicine = Medicine::create(['medicine_name' => 'Audit medicine', 'category' => 'medicine', 'min_stock' => 50]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $medicine];
    }

    public function test_rhu_user_search_and_autocomplete_only_return_manageable_accounts(): void
    {
        [$unit, $admin] = $this->fixture();
        $local = User::factory()->create(['name' => 'Find local', 'health_unit_id' => $unit->id, 'role' => 'midwife']);
        $other = HealthUnit::create(['name' => 'Outside RHU']);
        User::factory()->create(['name' => 'Find outside', 'health_unit_id' => $other->id]);
        User::factory()->create(['name' => 'Find emergency', 'health_unit_id' => $unit->id, 'is_emergency' => true]);
        $this->get(route('search.index', ['q' => 'Find', 'scope' => 'users']))->assertOk()
            ->assertViewHas('userResults', fn ($users) => $users->modelKeys() === [$local->id]);
        $response = $this->getJson(route('search.autocomplete', ['q' => 'Find', 'scope' => 'users']))->assertOk();
        $this->assertCount(1, $response->json());
        $response->assertJsonPath('0.text', $local->name.' ('.$local->username.')');
    }

    public function test_midwife_search_does_not_expose_modules_the_role_cannot_open(): void
    {
        [$unit, $midwife, $medicine] = $this->fixture('midwife');
        $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'available_stock' => 80]);
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'quantity_added' => 80, 'date_added' => today()]);
        Allocation::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'quantity_allocated' => 10,
            'allocated_by' => $midwife->id, 'destination' => 'Audit colleague', 'date_allocated' => today()]);
        $this->get(route('search.index', ['q' => 'Audit']))->assertOk()
            ->assertViewHas('inventoryResults', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('stockLogResults', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('allocationResults', fn ($rows) => $rows->isEmpty());
        $suggestions = $this->getJson(route('search.autocomplete', ['q' => 'Audit']))->assertOk()->json();
        $this->assertEmpty(array_intersect(['Inventory', 'Stock Log', 'Allocation'], array_column($suggestions, 'type')));
    }

    public function test_inventory_reports_attribute_dispensing_to_original_batches_and_skip_voided_logs(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $batches = collect(['FIRST', 'SECOND'])->map(fn ($name) => Inventory::create([
            'medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'batch_no' => $name, 'available_stock' => 40,
        ]));
        $record = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'dispensed_by' => $user->id, 'quantity_dispensed' => 15, 'date_dispensed' => today()]);
        foreach ([10, 5] as $index => $quantity) {
            StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'inventory_id' => $batches[$index]->id, 'batch_no' => $batches[$index]->batch_no,
                'dispensing_record_id' => $record->id, 'quantity_added' => $quantity, 'movement_type' => 'dispensing',
                'date_added' => today(), 'remarks' => 'Released — Dispensed']);
        }
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'inventory_id' => $batches[0]->id, 'dispensing_record_id' => $record->id,
            'quantity_added' => 999, 'date_added' => today(), 'voided_at' => now(), 'remarks' => 'Released — Dispensed']);
        $this->get(route('reports.inventory'))->assertOk()->assertViewHas('inventories', function ($rows) use ($batches) {
            return (int) $rows->firstWhere('id', $batches[0]->id)->total_dispensed === 10
                && (int) $rows->firstWhere('id', $batches[1]->id)->total_dispensed === 5;
        });
        $this->get(route('reports.stock'))->assertOk()->assertViewHas('stockLogs', fn ($rows) => $rows->count() === 2);
    }

    public function test_inventory_report_header_dropdowns_stay_native_and_keep_the_other_filter(): void
    {
        $unit = HealthUnit::create(['name' => 'Inventory report RHU', 'facility_type' => 'Rural Health Unit']);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $filters = ['category' => 'medicine', 'rhu_filter' => (string) $unit->id];
        $page = $this->withSession(['user_id' => $admin->id])->get(route('reports.inventory', $filters))->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $actions = '//*[contains(concat(" ", normalize-space(@class), " "), " page-heading-actions ")]/form[@method="GET"]';

        foreach (['category' => 'rhu_filter', 'rhu_filter' => 'category'] as $name => $peer) {
            $selects = $xpath->query($actions.'/select[@name="'.$name.'"][ancestor-or-self::*[@data-native-select]]');
            $this->assertCount(1, $selects);
            $select = $selects->item(0);
            $this->assertSame($filters[$name], $xpath->evaluate('string(./option[@selected]/@value)', $select));
            $this->assertSame($filters[$peer], $xpath->evaluate('string(../input[@type="hidden"][@name="'.$peer.'"]/@value)', $select));
        }
    }

    public function test_disease_recommendations_use_usable_stock_and_complete_calendar_periods(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
        [$unit, $user, $medicine] = $this->fixture();
        $disease = Disease::create(['name' => 'Audit condition', 'code' => 'AU']);
        DiseaseMedicineMap::create(['medicine_id' => $medicine->id, 'disease_id' => $disease->id]);
        foreach (['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'] as $index => $date) {
            $bounds = app(DiseasePeriodService::class)->bounds($date, 'weekly');
            DiseaseReport::create(['disease_id' => $disease->id, 'health_unit_id' => $unit->id,
                'report_date' => $date, 'period_type' => 'weekly', 'period_start' => $bounds['start'], 'period_end' => $bounds['end'],
                'is_complete' => true, 'case_count' => ($index + 1) * 10]);
        }
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'available_stock' => 10]);
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'available_stock' => 999, 'expiration_date' => '2026-11-05']);
        $service = app(DiseaseAnalysisService::class);
        $recommendations = $service->recommendations($unit->id);
        $this->assertCount(1, $recommendations);
        $this->assertSame(10, $recommendations[0]['current_stock']);
        // A Friday cutoff must include the entire oldest completed week, whose report was dated Monday.
        $this->assertSame(100, $service->topDiseases($unit->id, 4)[0]['total_cases']);
    }

    public function test_inventory_export_summary_counts_distinct_medicines_and_preserves_reserved_only_batches(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        foreach ([[10, 0], [20, 0], [0, 7]] as [$available, $allocated]) {
            Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'available_stock' => $available, 'allocated_stock' => $allocated]);
        }
        $report = app(ELMISExportService::class)->generateInventoryReport($unit->id);
        $this->assertCount(3, $report['rows']);
        $this->assertSame(1, $report['summary']['total_medicines']);
        $this->assertSame(30, $report['summary']['total_available']);
        $this->assertSame(7, $report['summary']['total_allocated']);
    }

    public function test_unmatched_batch_filters_return_empty_reports_instead_of_all_records(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'KNOWN', 'available_stock' => 10, 'expiration_date' => today()->addDay()]);
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'KNOWN', 'quantity_added' => 10, 'date_added' => today()]);
        foreach (['reports.inventory' => 'inventories', 'reports.stock' => 'stockLogs', 'reports.near_expiry' => 'inventories'] as $route => $variable) {
            $this->get(route($route, ['batch_input' => 'DOES-NOT-EXIST']))->assertOk()
                ->assertViewHas($variable, fn ($rows) => $rows->isEmpty());
        }
    }

    public function test_dashboard_months_do_not_overflow_and_reversed_movements_do_not_affect_totals(): void
    {
        $this->travelTo(Carbon::parse('2026-03-31 12:00:00', 'Asia/Manila'));
        [$unit, $user, $medicine] = $this->fixture();
        foreach ([[12, null], [999, now()]] as [$quantity, $voided]) {
            StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'quantity_added' => $quantity, 'date_added' => '2026-02-15', 'remarks' => 'Added fixture', 'voided_at' => $voided]);
        }
        $movement = app(DashboardService::class)->getDashboardData(false, $unit->id)['stockMovement'];
        $this->assertSame(['Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026', 'Feb 2026', 'Mar 2026'], $movement['months']);
        $this->assertSame([0, 0, 0, 0, 1, 0], $movement['additions']);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['stock_logs_count'] === 1);
    }

    public function test_recent_notification_limit_rejects_invalid_and_unbounded_values(): void
    {
        $this->fixture('staff');
        foreach (['invalid', '-5', '100000', ['bad']] as $limit) {
            $this->getJson(route('notifications.recent', ['limit' => $limit]))
                ->assertUnprocessable()->assertJsonValidationErrors('limit');
        }
        $this->getJson(route('notifications.recent', ['limit' => 5]))->assertOk();
    }

    public function test_search_dispensing_links_use_the_actual_module_page_size(): void
    {
        [$unit, $user, $medicine] = $this->fixture('staff');
        for ($index = 0; $index < 16; $index++) {
            DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'dispensed_by' => $user->id, 'date_dispensed' => today(), 'quantity_dispensed' => 1,
                'batch_no' => $index === 0 ? 'TARGET-BATCH' : 'OTHER-'.$index]);
        }
        $this->get(route('search.index', ['scope' => 'dispensing', 'q' => 'TARGET']))->assertOk()
            ->assertViewHas('dispensingResults', fn ($rows) => $rows->count() === 1 && $rows->first()->search_page === 2);
        $response = $this->getJson(route('search.autocomplete', ['scope' => 'dispensing', 'q' => 'TARGET']))->assertOk();
        $this->assertStringContainsString('page=2', $response->json('0.url'));
    }

    public function test_redistribution_demand_excludes_logistical_transfer_releases(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'available_stock' => 10]);
        foreach ([['dispensing', 30], ['transfer_out', 999]] as [$type, $quantity]) {
            StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'quantity_added' => $quantity, 'date_added' => today()->subDay(),
                'movement_type' => $type, 'remarks' => 'Released — '.($type === 'dispensing' ? 'Dispensed' : 'Transfer')]);
        }
        $suggestions = app(RedistributionService::class)->getSuggestions();
        $this->assertCount(0, $suggestions['understocked']);
    }

    public function test_low_stock_digest_uses_usable_medicine_totals_instead_of_individual_batches(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        foreach ([5, 80] as $quantity) {
            Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'available_stock' => $quantity]);
        }
        $expired = Medicine::create(['medicine_name' => 'No usable stock', 'category' => 'medicine']);
        Inventory::create(['medicine_id' => $expired->id, 'health_unit_id' => $unit->id,
            'available_stock' => 100, 'expiration_date' => today()->subDay()]);
        $deleted = Medicine::create(['medicine_name' => 'Removed', 'category' => 'medicine']);
        Inventory::create(['medicine_id' => $deleted->id, 'health_unit_id' => $unit->id, 'available_stock' => 5]);
        $deleted->delete();
        app(NotificationService::class)->scanLowStockDigest();
        $this->assertDatabaseHas('notifications', ['type' => 'low_stock_digest', 'health_unit_id' => $unit->id,
            'message' => '1 medicine at your RHU is below reorder level. Open Supply Requests to restock.']);
    }

    public function test_map_and_stock_lookup_categories_match_the_catalog_and_map_counts_use_exact_keys(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $planning = Medicine::create(['medicine_name' => 'Family planning stock', 'category' => 'family_planning', 'unit' => 'tablet']);
        $otherForm = Medicine::create(['medicine_name' => 'Family planning liquid', 'category' => 'family_planning', 'unit' => 'mL']);
        $similar = Medicine::create(['medicine_name' => 'Legacy similarly named category', 'category' => 'family_planning_extra']);
        foreach ([[5, null], [10, today()->subDay()]] as [$quantity, $expiry]) {
            Inventory::create(['medicine_id' => $planning->id, 'health_unit_id' => $unit->id,
                'available_stock' => $quantity, 'expiration_date' => $expiry]);
        }
        Inventory::create(['medicine_id' => $otherForm->id, 'health_unit_id' => $unit->id, 'available_stock' => 100,
            'expiration_date' => today()]);
        Inventory::create(['medicine_id' => $similar->id, 'health_unit_id' => $unit->id, 'available_stock' => 999]);
        foreach (['gis.index', 'stock_lookup.index'] as $route) {
            $this->get(route($route))->assertOk()->assertViewHas('categories', fn ($categories) => isset($categories[$planning->category])
                && $categories[$planning->category] === $planning->category_label);
        }
        $this->getJson(route('gis.category_count', ['rhu_id' => $unit->id, 'category' => $planning->category]))
            ->assertOk()->assertJsonPath('medicine_types', 2)
            ->assertJsonFragment(['unit' => 'tablet', 'quantity' => 5])->assertJsonFragment(['unit' => 'mL', 'quantity' => 100]);
        $this->getJson(route('gis.data'))->assertOk()->assertJsonPath('rhus.0.total_medicines', 3)
            ->assertJsonPath('rhus.0.stock_status', 'low');
    }
}
