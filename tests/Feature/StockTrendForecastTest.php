<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\ForecastService;
use App\Services\StockTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTrendForecastTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Trend RHU', 'facility_type' => 'Rural Health Unit']);
        $medicine = Medicine::create(['medicine_name' => 'Trend medicine', 'category' => 'medicine']);
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'available_stock' => 1000,
            'allocated_stock' => 0, 'expiration_date' => today()->addYears(2)]);

        return [$unit, $medicine];
    }

    private function release(HealthUnit $unit, Medicine $medicine, int $quantity, $date, array $extra = []): void
    {
        StockLog::create(array_merge(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'quantity_added' => $quantity, 'date_added' => $date, 'movement_type' => 'dispensing', 'remarks' => 'Released — Dispensed'], $extra));
    }

    public function test_the_weekly_trend_actually_drives_need_coverage_and_reorder_quantity(): void
    {
        [$unit, $medicine] = $this->fixture();
        for ($week = 0; $week < 12; $week++) {
            $this->release($unit, $medicine, ($week + 1) * 10, today()->subWeeks(12)->addWeeks($week)->addDay());
        }
        $item = app(ForecastService::class)->getForecast($unit->id)['items']->first();
        $this->assertSame('linear_regression', $item['model']['method']);
        $this->assertEquals(10, $item['model']['slope']);
        $this->assertSame('rising', $item['trend']);
        $this->assertSame(2435, $item['forecast_need_3mo']);
        $this->assertSame(629, $item['forecast_next_30_days']);
        $this->assertSame(420, $item['reorder_point']);
        $this->assertSame(44, $item['days_remaining']);
        $this->assertSame(1435, $item['shortage']);
    }

    public function test_only_completed_periods_and_valid_demand_movements_enter_the_model(): void
    {
        [$unit, $medicine] = $this->fixture();
        $this->release($unit, $medicine, 70, today()->subWeeks(4));
        foreach ([['movement_type' => 'addition'], ['movement_type' => 'transfer_out'], ['movement_type' => 'adjustment'],
            ['voided_at' => now()], ['quantity_added' => -100]] as $extra) {
            $this->release($unit, $medicine, 9999, today()->subWeeks(3), $extra);
        }
        $this->release($unit, $medicine, 9999, today());
        $this->release($unit, $medicine, 9999, today()->addDay());
        $this->release($unit, $medicine, 9999, today()->subWeeks(12)->subDay());
        $result = app(StockTrendService::class)->analyze([$unit->id])[$unit->id][$medicine->id];
        $this->assertSame([70, 0, 0, 0], array_column($result['history'], 'quantity'));
        $this->assertSame(today()->subDay()->toDateString(), $result['history_end']);
        $this->assertSame(4, $result['fit']['periods']);
    }

    public function test_legacy_dispensing_is_included_but_generic_inventory_corrections_are_excluded(): void
    {
        [$unit, $medicine] = $this->fixture();
        $this->release($unit, $medicine, 14, today()->subWeek(), ['movement_type' => null]);
        $this->release($unit, $medicine, 999, today()->subWeek(), ['movement_type' => null, 'remarks' => 'Released — Inventory adjusted']);
        $item = app(ForecastService::class)->getForecast($unit->id)['items']->first();
        $this->assertSame('weekly_average', $item['model']['method']);
        $this->assertSame([14], array_column($item['weekly_history'], 'quantity'));
        $this->assertSame(180, $item['forecast_need_3mo']);
    }

    public function test_facilities_and_medicines_are_fitted_independently(): void
    {
        [$unit, $medicine] = $this->fixture();
        $other = HealthUnit::create(['name' => 'Other RHU']);
        $otherMedicine = Medicine::create(['medicine_name' => 'Other medicine', 'category' => 'medicine']);
        for ($week = 0; $week < 4; $week++) {
            $date = today()->subWeeks(4)->addWeeks($week);
            $this->release($unit, $medicine, ($week + 1) * 10, $date);
            $this->release($other, $medicine, (4 - $week) * 10, $date);
            $this->release($unit, $otherMedicine, 7, $date);
        }
        $trends = app(StockTrendService::class)->analyze([$unit->id]);
        $this->assertArrayNotHasKey($other->id, $trends);
        $this->assertSame('rising', $trends[$unit->id][$medicine->id]['fit']['trend']);
        $this->assertSame('stable', $trends[$unit->id][$otherMedicine->id]['fit']['trend']);
    }

    public function test_the_forecast_page_exposes_the_model_and_weekly_chart(): void
    {
        [$unit, $medicine] = $this->fixture();
        $this->release($unit, $medicine, 14, today()->subWeek());
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'admin']);
        $this->withSession(['user_id' => $user->id, 'role' => 'admin', 'health_unit_id' => $unit->id])
            ->get(route('forecast.index'))->assertOk()->assertSee('Weekly release trend')->assertSee('stockTrendChart', false)->assertSee('Limited history');
    }

    public function test_absent_history_does_not_claim_infinite_stock_coverage(): void
    {
        [$unit] = $this->fixture();
        $item = app(ForecastService::class)->getForecast($unit->id)['items']->first();
        $this->assertSame('no_history', $item['trend']);
        $this->assertSame('unknown', $item['status']);
        $this->assertSame('N/A', $item['days_remaining']);
        $this->assertNull($item['reorder_point']);
    }
}
