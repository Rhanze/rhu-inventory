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

    private function payload(Disease $disease, string $date, array $extra = []): array
    {
        return array_merge(['disease_id' => $disease->id, 'report_date' => $date, 'period_type' => 'weekly', 'case_count' => 0, 'is_complete' => true], $extra);
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

    public function test_report_validation_prevents_duplicate_periods_and_premature_confirmation(): void
    {
        [$unit, $disease] = $this->fixture();
        $this->post(route('disease_reports.store'), $this->payload($disease, '2026-10-19'))->assertSessionHas('success');
        $this->post(route('disease_reports.store'), $this->payload($disease, '2026-10-22'))->assertSessionHasErrors('disease_id');
        $this->post(route('disease_reports.store'), $this->payload($disease, '2026-10-30'))->assertSessionHasErrors('is_complete');
        $this->post(route('disease_reports.store'), $this->payload($disease, '2026-10-30', ['is_complete' => false]))->assertSessionHas('success');
        $this->assertDatabaseHas('disease_reports', ['period_start' => '2026-10-26', 'period_end' => '2026-11-01', 'is_complete' => false]);
        $this->post(route('disease_reports.store'), $this->payload($disease, '2026-10-19', ['period_type' => 'monthly', 'is_complete' => false]))->assertSessionHas('success');
    }

    public function test_legacy_report_can_be_reviewed_without_automatically_inventing_completeness(): void
    {
        [$unit, $disease] = $this->fixture();
        $legacy = $this->report($unit, $disease, '2026-10-19', 0, 'weekly', ['period_start' => null, 'period_end' => null, 'is_complete' => false]);
        $this->get(route('disease_reports.index'))->assertOk()->assertSee('Needs review')->assertSee("Holt's adaptive forecast", false);
        $this->assertNull($legacy->fresh()->period_start);
        $this->put(route('disease_reports.update', $legacy->id), $this->payload($disease, '2026-10-19'))->assertSessionHas('success');
        $this->assertSame('2026-10-19', $legacy->fresh()->period_start->toDateString());
        $this->assertTrue($legacy->fresh()->is_complete);
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

    public function test_outbreak_closure_preserves_case_counts_and_does_not_create_a_zero_report(): void
    {
        [$unit, $disease] = $this->fixture();
        $report = $this->report($unit, $disease, '2026-10-19', 25, 'weekly', ['is_outbreak' => true]);
        $this->post(route('disease_reports.close_outbreak', $disease->id), ['remarks' => 'Manual review completed'])->assertSessionHas('success');
        $this->assertSame(1, DiseaseReport::count());
        $this->assertSame(25, $report->fresh()->case_count);
        $this->assertTrue($report->fresh()->is_complete);
        $this->assertNotNull($report->fresh()->outbreak_closed_at);
        $this->get(route('disease_reports.index'))->assertOk()->assertDontSee('Manually flagged outbreaks');
    }

    public function test_api_and_updates_use_the_session_rhu_even_if_another_is_requested(): void
    {
        [$unit, $disease] = $this->fixture();
        $other = HealthUnit::create(['name' => 'Other RHU']);
        $report = $this->report($other, $disease, '2026-10-19', 999);
        $this->put(route('disease_reports.update', $report->id), $this->payload($disease, '2026-10-19'))->assertNotFound();
        $response = $this->getJson(route('disease_reports.chart_data', ['disease_id' => $disease->id, 'health_unit_id' => $other->id, 'periods' => 4]));
        $response->assertOk()->assertJsonPath('analysis.reported_periods', 0)->assertJsonPath('datasets.0.data.3', null);
    }

    public function test_summary_charts_exclude_monthly_legacy_and_partial_reports_and_keep_missing_null(): void
    {
        [$unit, $disease] = $this->fixture();
        $this->report($unit, $disease, '2026-10-19', 0);
        $this->report($unit, $disease, '2026-10-12', 99, 'weekly', ['is_complete' => false]);
        $this->report($unit, $disease, '2026-09-01', 999, 'monthly');
        $this->report($unit, $disease, '2026-10-05', 999, 'weekly', ['period_start' => null, 'period_end' => null]);
        $forecast = app(ForecastService::class);
        $chart = $forecast->getTrendingDiseases($unit->id, 30);
        $this->assertSame([null, null, null, null, 0], $chart['datasets'][0]['data']);
        $this->assertEquals(0, $forecast->getUnplannedForecast($unit->id)['items'][0]->total_cases);
    }
}
