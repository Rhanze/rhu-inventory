<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRecordVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-18 15:00:00', 'Asia/Manila'));
    }

    public function test_pho_source_total_and_province_movement_total_keep_their_distinct_definitions(): void
    {
        $pho = HealthUnit::create(['name' => 'Provincial Health Office', 'facility_type' => 'Provincial Health Office']);
        $rhu = HealthUnit::create(['name' => 'Verification RHU']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Verification medicine']);
        $cancelled = TransferRequest::create(['from_health_unit_id' => $pho->id, 'to_health_unit_id' => $rhu->id,
            'medicine_id' => $medicine->id, 'quantity' => 1, 'status' => 'cancelled', 'requested_by' => $viewer->id]);
        foreach ([$pho, $rhu, $rhu, $rhu, $rhu] as $unit) {
            StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'quantity_added' => 1, 'movement_type' => 'addition', 'date_added' => today()]);
        }
        StockLog::create(['health_unit_id' => $pho->id, 'medicine_id' => $medicine->id, 'transfer_id' => $cancelled->id,
            'quantity_added' => 1, 'movement_type' => 'transfer_out', 'date_added' => today()]);
        Allocation::create(['health_unit_id' => $pho->id, 'medicine_id' => $medicine->id,
            'quantity_allocated' => 1, 'date_allocated' => today(), 'allocated_by' => $viewer->id,
            'destination' => 'Verification program', 'status' => 'pending']);

        $dashboard = $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk()
            ->assertSee('Total Transactions')->assertSee('Stock and allocation records')
            ->assertDontSee('Province-wide stock movement count');
        $this->assertSame(2, array_sum(array_column($dashboard->viewData('transactionRecords'), 'count')));
        $this->assertSame(5, $dashboard->viewData('stats')['province_stock_logs_count']);
        $this->assertSame(6, $dashboard->viewData('stats')['province_saved_stock_logs_count']);
        foreach ($dashboard->viewData('transactionRecords') as $source) {
            $page = $this->get($source['url'])->assertOk();
            $actual = str_contains($source['url'], '/stock-logs') ? $page->viewData('stockLogs')->total()
                : $page->viewData('allocations')->count();
            $this->assertSame($source['count'], $actual);
        }
        $this->get(route('stock_logs.index', ['rhu_filter' => 'all', 'date_to' => '2026-11-18', 'status' => 'active']))
            ->assertOk()->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 5);
    }

    public function test_recent_activity_uses_inclusive_calendar_dates_and_excludes_future_and_voided_dispensing(): void
    {
        $rhu = HealthUnit::create(['name' => 'Recent verification RHU']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => $rhu->id]);
        $medicine = Medicine::create(['medicine_name' => 'Recent medicine']);
        foreach (['2026-10-19', '2026-10-20', '2026-11-18', '2026-11-19'] as $date) {
            StockLog::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
                'quantity_added' => 1, 'movement_type' => 'addition', 'date_added' => $date]);
            DispensingRecord::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
                'quantity_dispensed' => 1, 'date_dispensed' => $date, 'dispensed_by' => $viewer->id]);
        }
        $reversed = DispensingRecord::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
            'quantity_dispensed' => 99, 'date_dispensed' => today(), 'dispensed_by' => $viewer->id]);
        StockLog::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id, 'dispensing_record_id' => $reversed->id,
            'quantity_added' => 99, 'movement_type' => 'dispensing', 'date_added' => today(), 'voided_at' => now()]);

        $dashboard = $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk();
        $this->assertSame(2, $dashboard->viewData('recentStockLogs')->total());
        $this->assertSame(2, $dashboard->viewData('recentDispensing')->total());
        $this->assertSame(3, $dashboard->viewData('stats')['dispensing_count']);
        $sources = collect($dashboard->viewData('transactionRecords'))->keyBy('label');
        $this->get($sources['Dispensing records']['url'])->assertOk()
            ->assertViewHas('records', fn ($rows) => $rows->total() === 3);
    }

    public function test_recent_activity_shows_one_dispensing_event_and_keeps_independent_stock_movements(): void
    {
        $rhu = HealthUnit::create(['name' => 'Activity RHU']);
        $other = HealthUnit::create(['name' => 'Other activity RHU']);
        $administrator = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => $rhu->id]);
        $medicine = Medicine::create(['medicine_name' => 'Activity medicine', 'unit' => 'tablets']);
        $record = DispensingRecord::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
            'quantity_dispensed' => 11, 'date_dispensed' => '2026-11-17', 'dispensed_by' => $viewer->id]);
        $stockLog = ['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id, 'date_added' => '2026-11-17'];
        foreach ([4, 7] as $quantity) {
            StockLog::create($stockLog + ['movement_type' => 'dispensing', 'dispensing_record_id' => $record->id,
                'quantity_added' => $quantity]);
        }
        StockLog::create($stockLog + ['quantity_added' => 1, 'remarks' => 'Released — Dispensed']);
        StockLog::create($stockLog + ['quantity_added' => 1, 'movement_type' => 'dispensing']);
        StockLog::create($stockLog + ['quantity_added' => 11, 'movement_type' => 'receipt']);
        StockLog::create(array_replace($stockLog, ['health_unit_id' => $other->id, 'date_added' => '2026-11-18',
            'quantity_added' => 2, 'movement_type' => 'transfer_in']));

        $rhuDashboard = $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk();
        $rhuActivity = $rhuDashboard->viewData('recentActivity');
        $this->assertSame(['Dispensed', 'Stock received'], $rhuActivity->pluck('action')->all());
        $this->assertSame([11, 11], $rhuActivity->pluck('quantity')->all());
        $this->assertSame(5, $rhuDashboard->viewData('recentStockLogs')->total());

        $provinceDashboard = $this->withSession(['user_id' => $administrator->id])->get(route('dashboard'))->assertOk();
        $this->assertSame(['Transfer received', 'Dispensed', 'Stock received'], $provinceDashboard->viewData('recentActivity')->pluck('action')->all());
        $document = new \DOMDocument;
        $document->loadHTML($provinceDashboard->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $activity = new \DOMXPath($document);
        $rows = $activity->query('//section[@aria-labelledby="dashboard-recent-activity-title"]//li');
        $this->assertSame(3, $rows->length);
        $this->assertStringContainsString('11 tablets', $rows->item(1)->textContent);
        $this->assertStringContainsString('Activity RHU', $rows->item(1)->textContent);
        $this->assertStringContainsString('Nov 17, 2026', $rows->item(1)->textContent);
    }

    public function test_forecast_cards_link_to_the_exact_counted_items_instead_of_a_mixed_unit_shortage_sum(): void
    {
        $rhu = HealthUnit::create(['name' => 'Forecast verification RHU']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => $rhu->id]);
        foreach ([['Shortage tablets', 'tablet', 10], ['No stock vials', 'vial', 0], ['Plentiful bottles', 'bottle', 5000]] as [$name, $unit, $balance]) {
            $medicine = Medicine::create(['medicine_name' => $name, 'unit' => $unit]);
            Inventory::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
                'available_stock' => $balance, 'expiration_date' => today()->addYear()]);
            if ($balance === 10) {
                for ($week = 1; $week <= 4; $week++) {
                    StockLog::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
                        'quantity_added' => 70, 'movement_type' => 'dispensing', 'date_added' => today()->subWeeks($week)]);
                }
            }
        }
        $dashboard = $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk()
            ->assertSee('Items needing stock')->assertDontSee('Units projected to be short');
        $summary = $dashboard->viewData('forecastSummary');
        $this->assertSame([1, 2, 3], [$summary['shortage_items'], $summary['low_stock_items'], $summary['total_items']]);
        $document = new \DOMDocument;
        @$document->loadHTML($dashboard->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['shortage' => 1, 'low' => 2, 'all' => 3] as $filter => $count) {
            $url = $xpath->evaluate('string(//a[@id="dashboard-forecast-'.$filter.'"]/@href)');
            $this->assertNotSame('', $url);
            $this->get($url)->assertOk()->assertViewHas('stockFilter', $filter)
                ->assertViewHas('forecast', fn ($forecast) => $forecast['items']->total() === $count);
        }
        $filteredPage = $this->get(route('forecast.index', ['stock_filter' => 'shortage']))->assertOk();
        $forecastDocument = new \DOMDocument;
        @$forecastDocument->loadHTML($filteredPage->getContent());
        $forecastXPath = new \DOMXPath($forecastDocument);
        $stockFilter = '//select[@id="forecast-stock-filter"][@name="stock_filter"][@data-native-select]';
        $this->assertSame('shortage', $forecastXPath->evaluate('string('.$stockFilter.'/option[@selected]/@value)'));
        $this->assertSame('this.form.requestSubmit()', $forecastXPath->evaluate('string('.$stockFilter.'/@onchange)'));
        $filterForm = $stockFilter.'/ancestor::form[@method="GET"]';
        $this->assertSame(route('forecast.index').'#forecast-stock', $forecastXPath->evaluate('string('.$filterForm.'/@action)'));
        $this->assertCount(0, $forecastXPath->query($filterForm.'//button[@type="submit"]'));

        foreach (['critical' => 2, 'reorder' => 1, 'unknown' => 2] as $filter => $count) {
            $url = $forecastXPath->evaluate('string(//a[@id="forecast-kpi-'.$filter.'"]/@href)');
            $this->assertNotSame('', $url);
            $this->get($url)->assertOk()->assertViewHas('stockFilter', $filter)
                ->assertViewHas('forecast', fn ($forecast) => $forecast['items']->total() === $count);
        }
        $this->getJson(route('forecast.index', ['stock_filter' => 'invalid']))->assertUnprocessable();
    }
}
