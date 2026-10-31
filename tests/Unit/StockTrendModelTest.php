<?php

namespace Tests\Unit;

use App\Services\StockTrendModel;
use App\Services\StockTrendService;
use Tests\TestCase;

class StockTrendModelTest extends TestCase
{
    public function test_rising_weekly_releases_fit_the_expected_line_and_partial_week_forecast(): void
    {
        $model = new StockTrendModel;
        $fit = $model->fit([10, 20, 30, 40]);
        $this->assertEqualsWithDelta(10, $fit['slope'], 1e-10);
        $this->assertEqualsWithDelta(0, $fit['intercept'], 1e-10);
        $this->assertSame('rising', $fit['trend']);
        $this->assertSame(1.0, $fit['r_squared']);
        $this->assertEqualsWithDelta(50 + 60 * 3 / 7, array_sum($model->dailyPredictions($fit, 10)), 1e-9);
    }

    public function test_declining_releases_never_produce_negative_demand(): void
    {
        $model = new StockTrendModel;
        $fit = $model->fit([40, 30, 20, 10]);
        $this->assertEqualsWithDelta(-10, $fit['slope'], 1e-10);
        $this->assertSame('falling', $fit['trend']);
        $this->assertEquals(0, array_sum($model->dailyPredictions($fit, 90)));
    }

    public function test_constant_releases_are_stable_with_exact_ninety_day_demand(): void
    {
        $model = new StockTrendModel;
        $fit = $model->fit([14, 14, 14, 14]);
        $this->assertSame('stable', $fit['trend']);
        $this->assertSame(1.0, $fit['r_squared']);
        $this->assertEqualsWithDelta(180, array_sum($model->dailyPredictions($fit, 90)), 1e-9);
    }

    public function test_short_history_uses_a_labelled_average_without_a_claimed_regression_fit(): void
    {
        $model = new StockTrendModel;
        $fit = $model->fit([7, 14]);
        $this->assertSame('weekly_average', $fit['method']);
        $this->assertSame('insufficient_history', $fit['trend']);
        $this->assertNull($fit['r_squared']);
        $this->assertEqualsWithDelta(10.5, $model->weeklyPrediction($fit, 1), 1e-10);
        $this->assertSame('no_history', $model->fit([])['trend']);
    }

    public function test_noisy_series_has_the_correct_least_squares_coefficients_and_r_squared(): void
    {
        $fit = (new StockTrendModel)->fit([10, 18, 35, 39]);
        $this->assertEqualsWithDelta(10.4, $fit['slope'], 1e-10);
        $this->assertEqualsWithDelta(-0.5, $fit['intercept'], 1e-10);
        $this->assertEqualsWithDelta(1 - 28.2 / 569, $fit['r_squared'], 1e-10);
    }

    public function test_coverage_sums_the_forecast_and_returns_a_bounded_horizon_when_not_depleted(): void
    {
        $service = app(StockTrendService::class);
        $this->assertEquals(['days' => 7, 'capped' => false], $service->coverage(14, array_fill(0, 365, 2)));
        $this->assertSame(['days' => 365, 'capped' => true], $service->coverage(1000, array_fill(0, 365, 2)));
        $this->assertSame(['days' => 0, 'capped' => false], $service->coverage(0, [10]));
    }

    public function test_invalid_negative_movement_totals_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new StockTrendModel)->fit([10, -5, 20, 30]);
    }
}
