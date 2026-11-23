<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardKpiDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_pho_transaction_components_and_province_log_count_open_matching_rows(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 15:00', 'Asia/Manila'));
        $pho = HealthUnit::create(['name' => 'Provincial Health Office', 'facility_type' => 'Provincial Health Office']);
        $rhu = HealthUnit::create(['name' => 'Transaction RHU', 'facility_type' => 'RHU']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Transaction medicine']);

        foreach ([[$pho, today()->subDay(), null], [$rhu, today(), null], [$pho, today(), now()], [$pho, today()->addDay(), null]] as [$unit, $date, $voided]) {
            StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'date_added' => $date, 'quantity_added' => 50, 'movement_type' => 'addition', 'voided_at' => $voided]);
        }
        foreach ([$pho, $rhu] as $unit) {
            Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'quantity_allocated' => 5,
                'date_allocated' => today(), 'destination' => 'Test destination', 'allocated_by' => $viewer->id, 'status' => 'pending']);
        }
        $batch = Inventory::create(['health_unit_id' => $pho->id, 'medicine_id' => $medicine->id, 'available_stock' => 50]);
        DB::table('stock_movements')->insert(['inventory_id' => $batch->id, 'owner_type' => 'inventory', 'owner_id' => $batch->id,
            'available_delta' => 50, 'created_at' => now()]);
        Inventory::create(['health_unit_id' => $pho->id, 'medicine_id' => $medicine->id, 'available_stock' => 5, 'batch_no' => 'SECOND']);
        Inventory::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id, 'available_stock' => 9, 'expiration_date' => today()]);
        $expired = Medicine::create(['medicine_name' => 'Expired only']);
        Inventory::create(['health_unit_id' => $rhu->id, 'medicine_id' => $expired->id,
            'available_stock' => 80, 'expiration_date' => today()->subDay()]);
        $archived = Medicine::create(['medicine_name' => 'Archived item']);
        Inventory::create(['health_unit_id' => $rhu->id, 'medicine_id' => $archived->id, 'available_stock' => 100]);
        $archived->delete();

        $dashboard = $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk()
            ->assertSee('Province Overview')
            ->assertSee('Total RHUs')
            ->assertSee('Items with usable stock (per facility)')
            ->assertDontSee('How these totals are counted');
        $records = $dashboard->viewData('transactionRecords');
        $document = new \DOMDocument;
        $document->loadHTML($dashboard->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = new \DOMXPath($document);
        $cards = '//div[contains(@class, "dashboard-alerts-grid")]/*[contains(@class, "dashboard-kpi-card")]';
        $this->assertSame(4, $links->query($cards)->length);
        $this->assertSame(3, $links->query($cards.'[self::a]')->length);
        $this->assertSame('2', $links->evaluate('string(('.$cards.')[4]/strong)'));
        $this->assertSame(2, $dashboard->viewData('stats')['province_stock_logs_count']);
        $this->assertSame(3, $dashboard->viewData('stats')['province_saved_stock_logs_count']);
        $this->assertSame(2, $dashboard->viewData('stats')['usable_item_facility_count']);
        $this->assertSame([1, 1], array_column($records, 'count'));
        $this->assertSame(1, $this->get($records[0]['url'])->assertOk()->viewData('stockLogs')->total());
        $this->assertCount(1, $this->get($records[1]['url'])->assertOk()->viewData('allocations'));
        $filters = ['rhu_filter' => 'all', 'date_to' => '2026-11-18'];
        $this->assertSame(2, $this->get(route('stock_logs.index', $filters + ['status' => 'active']))->assertOk()->viewData('stockLogs')->total());
        $allLogsUrl = route('stock_logs.index', $filters + ['status' => 'all', 'source' => 'stock_logs']);
        $this->assertSame(3, $this->get($allLogsUrl)->assertOk()->viewData('stockLogs')->total());
        $this->assertSame(4, $this->get(route('stock_logs.index', $filters + ['status' => 'all']))->assertOk()->viewData('stockLogs')->total());
    }

    public function test_staff_activity_is_today_only_and_batch_cards_match_filtered_inventory(): void
    {
        $rhu = HealthUnit::create(['name' => 'KPI RHU', 'facility_type' => 'Rural Health Unit']);
        $outside = HealthUnit::create(['name' => 'Other KPI RHU', 'facility_type' => 'Rural Health Unit']);
        $viewer = User::factory()->create(['role' => 'staff', 'health_unit_id' => $rhu->id]);
        $medicine = Medicine::create(['medicine_name' => 'KPI medicine', 'min_stock' => 10, 'max_stock' => 60]);
        foreach ([[$rhu, 10, today()], [$rhu, 5, today()->subDay()], [$rhu, 80, null], [$outside, 1, null]] as [$unit, $quantity, $expiry]) {
            Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'available_stock' => $quantity, 'expiration_date' => $expiry]);
        }
        foreach ([today(), today()->subDay()] as $day) {
            DispensingRecord::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
                'quantity_dispensed' => 1, 'date_dispensed' => $day, 'dispensed_by' => $viewer->id]);
        }
        $dashboard = $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk()
            ->assertSee("Today's Activity")
            ->assertDontSee('How these totals are counted');
        $stats = $dashboard->viewData('stats');
        $this->assertSame(1, $stats['dispensing_count']);
        foreach (['low' => 'low_stock_count', 'overstock' => 'overstock_count'] as $level => $key) {
            $batches = $this->get(route('inventory.index', ['stock_level' => $level, 'rhu_filter' => $outside->id]))
                ->assertOk()->viewData('inventories');
            $this->assertSame(1, $stats[$key]);
            $this->assertSame($stats[$key], $batches->total());
            $this->assertSame($rhu->id, $batches->first()->health_unit_id);
        }
    }
}
