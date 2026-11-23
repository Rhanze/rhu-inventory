<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseReport;
use App\Models\HealthUnit;
use App\Models\User;
use App\Services\DiseaseAnalysisService;
use App\Services\DiseasePeriodService;
use App\Services\ForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiseaseAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Disease test RHU', 'facility_type' => 'Rural Health Unit']);
        $disease = Disease::create(['code' => 'DTEST', 'name' => 'Disease fixture']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $this->withSession(['user_id' => $user->id, 'health_unit_id' => $unit->id, 'role' => 'staff']);

        return [$unit, $disease, $user];
    }

    private function report(HealthUnit $unit, Disease $disease, string $date, int $cases, string $type = 'weekly', array $extra = []): DiseaseReport
    {
        $period = app(DiseasePeriodService::class)->bounds($date, $type);

        return DiseaseReport::create(array_merge(['health_unit_id' => $unit->id, 'disease_id' => $disease->id, 'period_type' => $type,
            'report_date' => $date, 'period_start' => $period['start'], 'period_end' => $period['end'], 'is_complete' => true, 'case_count' => $cases], $extra));
    }

    public function test_period_boundaries_are_iso_weeks_and_calendar_months_including_leap_years(): void
    {
        $periods = app(DiseasePeriodService::class);
        $this->assertSame(['start' => '2025-12-29', 'end' => '2026-01-04'], $periods->bounds('2026-01-01', 'weekly'));
        $this->assertSame(['start' => '2024-02-01', 'end' => '2024-02-29'], $periods->bounds('2024-02-15', 'monthly'));
    }

    public function test_missing_provisional_unverified_and_confirmed_zero_have_distinct_meanings(): void
    {
        [$unit, $disease] = $this->fixture();
        $this->report($unit, $disease, '2026-09-28', 0);
        $this->report($unit, $disease, '2026-10-12', 10, 'weekly', ['is_complete' => false]);
        $this->report($unit, $disease, '2026-10-19', 20, 'weekly', ['period_start' => null, 'period_end' => null, 'is_complete' => false]);
        $series = app(DiseasePeriodService::class)->series($disease->id, $unit->id, 'weekly', 4);
        $this->assertSame([0, null, null, null], array_column($series['periods'], 'cases'));
        $this->assertSame(['reported', 'missing', 'provisional', 'unverified'], array_column($series['periods'], 'status'));
        $this->assertSame(1, $series['reported_periods']);
    }

    public function test_models_use_completed_same_frequency_reports_and_exclude_other_rhus(): void
    {
        [$unit, $disease] = $this->fixture();
        foreach (['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'] as $index => $date) {
            $this->report($unit, $disease, $date, ($index + 1) * 10);
        }
        $this->report($unit, $disease, '2026-10-26', 999, 'weekly', ['is_complete' => false]);
        $this->report($unit, $disease, '2026-09-01', 9999, 'monthly');
        $other = HealthUnit::create(['name' => 'Other RHU']);
        $this->report($other, $disease, '2026-10-19', 10000);
        $analysis = app(DiseaseAnalysisService::class)->analyze($disease->id, $unit->id, 'weekly', 4);
        $this->assertSame([10, 20, 30, 40], array_column($analysis['periods'], 'cases'));
        $this->assertSame('rising', $analysis['direction']);
        $this->assertEquals(50, $analysis['forecast'][0]['regression']);
        $this->assertEquals(50, $analysis['forecast'][0]['holt']);
        $this->assertSame('2026-10-26', $analysis['forecast'][0]['start']);
    }

    public function test_monthly_models_use_only_completed_calendar_months(): void
    {
        [$unit, $disease] = $this->fixture();
        foreach (['2026-06-01', '2026-07-01', '2026-08-01', '2026-09-01'] as $date) {
            $this->report($unit, $disease, $date, 10, 'monthly');
        }
        $this->report($unit, $disease, '2026-10-01', 999, 'monthly', ['is_complete' => false]);
        $analysis = app(DiseaseAnalysisService::class)->analyze($disease->id, $unit->id, 'monthly', 4);
        $this->assertSame([10, 10, 10, 10], array_column($analysis['periods'], 'cases'));
        $this->assertSame('2026-10-01', $analysis['forecast'][0]['start']);
        $this->assertSame('2026-10-31', $analysis['forecast'][0]['end']);
        $this->assertSame('stable', $analysis['direction']);
    }

    public function test_legacy_duplicates_make_a_period_conflicting_instead_of_summing_twice(): void
    {
        [$unit, $disease] = $this->fixture();
        foreach (['2026-10-19', '2026-10-21'] as $date) {
            $this->report($unit, $disease, $date, 10, 'weekly', ['period_start' => null, 'period_end' => null]);
        }
        $series = app(DiseasePeriodService::class)->series($disease->id, $unit->id, 'weekly', 4);
        $this->assertSame('conflict', $series['periods'][3]['status']);
        $this->assertNull($series['periods'][3]['cases']);
    }

    public function test_summary_forecasts_use_consumption_and_exclude_legacy_manual_case_reports(): void
    {
        [$unit, $disease] = $this->fixture();
        $this->report($unit, $disease, '2026-10-19', 0);
        $this->report($unit, $disease, '2026-10-12', 99, 'weekly', ['is_complete' => false]);
        $this->report($unit, $disease, '2026-09-01', 999, 'monthly');
        $this->report($unit, $disease, '2026-10-05', 999, 'weekly', ['period_start' => null, 'period_end' => null]);
        $forecast = app(ForecastService::class);
        $chart = $forecast->getTrendingDiseases($unit->id, 30);
        $this->assertSame([], $chart['datasets']);
        $this->assertSame('medicine_consumption', $chart['source']);
        $this->assertSame([], $forecast->getUnplannedForecast($unit->id)['items']);
        $this->assertDatabaseCount('disease_reports', 4);
    }
}
