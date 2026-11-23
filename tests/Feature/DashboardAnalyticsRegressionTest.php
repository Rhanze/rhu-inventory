<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnalyticsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 15:00:00', 'Asia/Manila'));
    }

    private function unit(): HealthUnit
    {
        return HealthUnit::create(['name' => 'Analytics RHU', 'facility_type' => 'Rural Health Unit']);
    }

    private function release(HealthUnit $unit, Medicine $medicine, int $quantity, $date, array $extra = []): void
    {
        StockLog::create($extra + ['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'quantity_added' => $quantity, 'movement_type' => 'dispensing', 'date_added' => $date]);
    }

    public function test_share_uses_all_recorded_usage_in_the_same_unit_even_beyond_the_top_ten(): void
    {
        $unit = $this->unit();
        for ($index = 1; $index <= 11; $index++) {
            $medicine = Medicine::create(['medicine_name' => 'Medicine '.$index, 'unit' => 'tablet']);
            $this->release($unit, $medicine, $index * 10, today());
        }
        $vaccine = Medicine::create(['medicine_name' => 'Vial medicine', 'unit' => 'vial']);
        $this->release($unit, $vaccine, 900, today());
        $unknown = Medicine::create(['medicine_name' => 'Unknown unit medicine']);
        $this->release($unit, $unknown, 800, today());
        $result = app(DashboardService::class)->getDashboardData(false, $unit->id, 90, 'usage')['utilization'];

        $this->assertCount(10, $result['details']);
        $this->assertArrayNotHasKey('top_share_pct', $result['summary']);
        $this->assertSame(100.0, $result['details'][0]['share_pct']);
        $this->assertNull($result['details'][1]['share_pct']);
        $this->assertSame(16.7, $result['details'][2]['share_pct']);
    }

    public function test_usage_combines_program_issues_and_legacy_dispensing_without_counting_a_dispensing_twice(): void
    {
        $unit = $this->unit();
        $medicine = Medicine::create(['medicine_name' => 'Usage medicine']);
        $logged = DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'quantity_dispensed' => 50, 'date_dispensed' => today()]);
        $this->release($unit, $medicine, 20, today(), ['dispensing_record_id' => $logged->id]);
        $this->release($unit, $medicine, 30, today(), ['dispensing_record_id' => $logged->id]);
        DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'quantity_dispensed' => 10, 'date_dispensed' => today()]);
        $this->release($unit, $medicine, 40, today(), ['movement_type' => 'allocation']);
        $this->release($unit, $medicine, 999, today(), ['movement_type' => 'addition']);
        $this->release($unit, $medicine, 999, today(), ['movement_type' => 'transfer_out']);
        $this->release($unit, $medicine, 999, today(), ['voided_at' => now()]);

        $result = app(DashboardService::class)->getDashboardData(false, $unit->id)['utilization'];
        $this->assertSame(100, $result['details'][0]['total_usage']);
    }

    public function test_trend_windows_do_not_overlap_or_include_future_usage(): void
    {
        $unit = $this->unit();
        $medicine = Medicine::create(['medicine_name' => 'Boundary medicine']);
        $this->release($unit, $medicine, 10, today()->subDays(30));
        $this->release($unit, $medicine, 20, today()->subDays(29));
        $this->release($unit, $medicine, 10, today());
        $this->release($unit, $medicine, 999, today()->addDay());

        $result = app(DashboardService::class)->getDashboardData(false, $unit->id, 30, 'usage')['utilization'];
        $item = $result['details'][0];
        $this->assertSame(30, $item['total_usage']);
        $this->assertSame(30, $item['last_30d']);
        $this->assertSame(10, $item['prev_30d']);
        $this->assertSame(200.0, $item['change_pct']);
        $this->assertSame('2026-10-19', $result['summary']['current_start']);
        $this->assertSame('2026-10-18', $result['summary']['previous_end']);
    }

    public function test_usage_and_demand_share_saved_issues_and_exclude_voided_dispensing_fallbacks(): void
    {
        $unit = $this->unit();
        $actor = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'Shared usage evidence', 'unit' => 'vial', 'category' => 'vaccine']);
        $valid = DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensed_by' => $actor->id, 'quantity_dispensed' => 10, 'date_dispensed' => today()->subWeek()]);
        $voided = DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensed_by' => $actor->id, 'quantity_dispensed' => 999, 'date_dispensed' => today()->subWeek()]);
        $this->release($unit, $medicine, 4, today()->subWeek(), ['dispensing_record_id' => $valid->id]);
        $this->release($unit, $medicine, 999, today()->subWeek(), ['dispensing_record_id' => $voided->id, 'voided_at' => now()]);
        Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'allocated_by' => $actor->id,
            'quantity_allocated' => 6, 'date_allocated' => today()->subWeek(), 'status' => 'completed', 'destination' => 'Local program']);

        $dashboard = app(DashboardService::class)->getDashboardData(false, $unit->id)['utilization'];
        $this->assertSame(16, $dashboard['details'][0]['total_usage']);
        $this->assertSame('vial', $dashboard['details'][0]['unit']);
        $history = app(\App\Services\StockTrendService::class)->analyze([$unit->id]);
        $this->assertSame(16, array_sum(array_column($history[$unit->id][$medicine->id]['history'], 'quantity')));
    }

    public function test_trend_ranking_can_prioritize_rising_demand_while_usage_totals_remain_actual(): void
    {
        $unit = $this->unit();
        $falling = Medicine::create(['medicine_name' => 'Previously high use']);
        $rising = Medicine::create(['medicine_name' => 'Rising demand']);
        $otherMedicines = collect(range(1, 10))->map(fn ($index) => Medicine::create(['medicine_name' => 'Older high usage '.$index]));
        for ($week = 0; $week < 12; $week++) {
            $date = today()->subWeeks(12)->addWeeks($week)->addDay();
            $this->release($unit, $falling, 130 - $week * 10, $date);
            $this->release($unit, $rising, ($week + 1) * 10, $date);
            foreach ($otherMedicines as $other) {
                $this->release($unit, $other, 65, $date);
            }
        }

        $service = app(DashboardService::class);
        $actual = $service->getDashboardData(false, $unit->id, 90, 'usage')['utilization'];
        $trend = $service->getDashboardData(false, $unit->id, 90, 'trend')['utilization'];
        $this->assertSame($falling->id, $actual['details'][0]['medicine_id']);
        $this->assertNotContains($rising->id, array_column($actual['details'], 'medicine_id'));
        $this->assertSame($rising->id, $trend['details'][0]['medicine_id']);
        $this->assertSame(780, $trend['details'][0]['total_usage']);
        $this->assertSame(629, $trend['details'][0]['forecast_30d']);
        $actor = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'admin']);
        $this->withSession(['user_id' => $actor->id])->get(route('dashboard', ['usage_days' => 30, 'usage_ranking' => 'trend']))
            ->assertOk()->assertSee('Medicine Usage and Demand Forecast')->assertSee('Projected 30-day demand')->assertSee('Yellow ▲')->assertSee('previous 30 days')
            ->assertSee('Usage (30d)');
    }

    public function test_forecast_filters_render_as_native_dropdowns_with_the_applied_values(): void
    {
        $actor = User::factory()->create(['health_unit_id' => $this->unit()->id, 'role' => 'admin']);
        $response = $this->withSession(['user_id' => $actor->id])
            ->get(route('dashboard', ['usage_days' => 180, 'usage_ranking' => 'usage']))
            ->assertOk()->assertSee('Usage (180d)')->assertSee('Ranked by recorded usage');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $form = '//div[@id="dashboard-utilization"]//form[@data-native-select][@method="GET"]';

        $this->assertSame(route('dashboard').'#dashboard-utilization', $xpath->evaluate('string('.$form.'/@action)'));
        $this->assertSame('180', $xpath->evaluate('string('.$form.'//select[@name="usage_days"]/option[@selected]/@value)'));
        $this->assertSame('usage', $xpath->evaluate('string('.$form.'//select[@name="usage_ranking"]/option[@selected]/@value)'));
    }
}
