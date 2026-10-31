<?php

namespace Tests\Unit;

use App\Services\DiseaseRegressionModel;
use App\Services\HoltDiseaseModel;
use Tests\TestCase;

class DiseaseForecastModelsTest extends TestCase
{
    public function test_ols_keeps_missing_periods_at_their_original_time_positions(): void
    {
        $fit = (new DiseaseRegressionModel)->fit([10, null, 30, 40, null, 60]);
        $this->assertSame(4, $fit['observations']);
        $this->assertEqualsWithDelta(10, $fit['slope'], 1e-9);
        $this->assertEqualsWithDelta(0, $fit['intercept'], 1e-9);
        $this->assertEqualsWithDelta(1, $fit['r_squared'], 1e-9);
        $this->assertSame([70.0, 80.0, 90.0, 100.0], $fit['predictions']);
        $this->assertNull($fit['fitted'][1]);
    }

    public function test_latest_missing_period_suppresses_projections_and_zero_is_observed(): void
    {
        $model = new DiseaseRegressionModel;
        $missing = $model->fit([10, 20, 30, 40, null]);
        $this->assertSame('latest_period_missing', $missing['status']);
        $this->assertSame([null, null, null, null], $missing['predictions']);
        $zero = $model->fit([0, 0, 0, 0]);
        $this->assertSame('ready', $zero['status']);
        $this->assertSame('stable', $zero['direction']);
        $this->assertEquals([0, 0, 0, 0], $zero['predictions']);
        $this->assertSame('insufficient_history', $model->fit([10, null, 20, null])['status']);
    }

    public function test_holt_update_matches_the_level_and_trend_equations(): void
    {
        $state = (new HoltDiseaseModel)->update(['level' => 20, 'slope' => 2], 30, 0.4, 0.2);
        $this->assertEqualsWithDelta(25.2, $state['level'], 1e-9);
        $this->assertEqualsWithDelta(2.64, $state['slope'], 1e-9);
    }

    public function test_holt_tracks_a_known_line_and_clamps_declining_case_projections(): void
    {
        $model = new HoltDiseaseModel;
        $this->assertEquals([50, 60, 70, 80], $model->fit([10, 20, 30, 40])['predictions']);
        $this->assertEquals([0, 0, 0, 0], $model->fit([40, 30, 20, 10])['predictions']);
        $this->assertEquals([0, 0, 0, 0], $model->fit([0, 0, 0, 0])['predictions']);
    }

    public function test_holt_adapts_to_recent_changes_and_requires_a_contiguous_recent_run(): void
    {
        $model = new HoltDiseaseModel;
        $this->assertEqualsWithDelta(19.6, $model->fit([10, 10, 10, 30])['predictions'][0], 1e-9);
        $this->assertEquals([50, 60, 70, 80], $model->fit([1000, 1000, null, 10, 20, 30, 40])['predictions']);
        $this->assertSame('insufficient_history', $model->fit([10, 20, 30, null, 40, 50])['status']);
        $this->assertSame('latest_period_missing', $model->fit([10, 20, 30, 40, null])['status']);
    }

    public function test_holt_holdout_is_evaluated_in_time_order_and_does_not_select_weights(): void
    {
        $model = new HoltDiseaseModel;
        $linear = $model->fit(range(10, 120, 10));
        $this->assertEqualsWithDelta(0, $linear['validation_mae'], 1e-9);
        $this->assertEqualsWithDelta(10, $linear['naive_mae'], 1e-9);
        $this->assertSame(3, $linear['validation_periods']);
        $training = [10, 9, 12, 11, 17, 15, 14, 20, 18];
        $a = $model->fit(array_merge($training, [100, 0, 200]));
        $b = $model->fit(array_merge($training, [0, 200, 0]));
        $this->assertSame([$a['alpha'], $a['beta']], [$b['alpha'], $b['beta']]);
        $this->assertNull($model->fit([10, 20, 30, 40])['validation_mae']);
    }
}
