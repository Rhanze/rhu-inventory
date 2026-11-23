<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\PublicUser;
use App\Models\RhuImmunizationSchedule;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Services\ForecastService;
use App\Services\VaccinationScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionPlanningConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function patient(HealthUnit $unit, string $name, ?string $area = null): PublicUser
    {
        return PublicUser::create(['health_unit_id' => $unit->id, 'first_name' => $name,
            'last_name' => 'Child', 'birth_date' => '2026-01-01', 'address' => 'Local', 'barangay' => $area]);
    }

    private function dose(PublicUser $child, string $date, array $attributes = []): VaccinationSchedule
    {
        return VaccinationSchedule::create($attributes + ['health_unit_id' => $child->health_unit_id,
            'public_user_id' => $child->id, 'vaccine_name' => 'BCG', 'dose_number' => 1,
            'due_date' => $date, 'status' => 'pending']);
    }

    public function test_calendar_and_forecast_place_pending_doses_on_the_matching_area_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Area RHU', 'immunization_pattern' => 'weekly',
            'immunization_weekday' => Carbon::WEDNESDAY]);
        $staff = User::factory()->create(['health_unit_id' => $unit->id]);
        RhuImmunizationSchedule::create(['health_unit_id' => $unit->id, 'area_label' => 'Area One',
            'pattern' => 'monthly_first', 'weekday' => Carbon::THURSDAY, 'is_active' => true]);
        RhuImmunizationSchedule::create(['health_unit_id' => $unit->id, 'area_label' => 'Area Two',
            'pattern' => 'monthly_nth', 'weekday' => Carbon::THURSDAY, 'week_number' => 2, 'is_active' => true]);
        $child = $this->patient($unit, 'AreaSpecific', '  area two  ');
        $dose = $this->dose($child, '2026-11-05');

        $this->withSession(['user_id' => $staff->id])->get(route('vaccination.calendar', [
            'month' => '2026-11', 'day' => '2026-11-12',
        ]))->assertOk()->assertSee('AreaSpecific Child')
            ->assertViewHas('selectedSchedules', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $dose->id);
        $planned = app(ForecastService::class)->getPlannedForecast($unit->id, 30);
        $targets = collect($planned['items'])->where('type', 'vaccine_targets');
        $this->assertSame(['2026-11-12'], $targets->pluck('date')->all());
        $this->assertSame(1, $planned['summary']['total_targets']);
        $this->assertSame('2026-11-05', $dose->fresh()->due_date->toDateString());
        $this->assertSame('pending', $dose->fresh()->status);
    }

    public function test_a_past_due_date_with_an_upcoming_session_is_included_in_future_targets(): void
    {
        $this->travelTo(Carbon::parse('2026-11-17 10:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Upcoming RHU', 'immunization_pattern' => 'weekly',
            'immunization_weekday' => Carbon::WEDNESDAY]);
        $staff = User::factory()->create(['health_unit_id' => $unit->id]);
        $child = $this->patient($unit, 'Upcoming');
        $dose = $this->dose($child, '2026-11-16');

        $this->withSession(['user_id' => $staff->id])->get(route('vaccination.calendar', [
            'month' => '2026-11', 'day' => '2026-11-18',
        ]))->assertOk()->assertViewHas('selectedSchedules', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $dose->id);
        $planned = app(ForecastService::class)->getPlannedForecast($unit->id, 7);
        $this->assertSame(1, $planned['summary']['total_targets']);
        $this->assertSame(['2026-11-18'], collect($planned['items'])->where('type', 'vaccine_targets')->pluck('date')->all());
    }

    public function test_area_sessions_do_not_hide_patients_using_the_rhu_default_on_the_same_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Fallback RHU', 'immunization_pattern' => 'weekly',
            'immunization_weekday' => Carbon::THURSDAY]);
        $staff = User::factory()->create(['health_unit_id' => $unit->id]);
        RhuImmunizationSchedule::create(['health_unit_id' => $unit->id, 'area_label' => 'Area Two',
            'pattern' => 'monthly_nth', 'weekday' => Carbon::THURSDAY, 'week_number' => 2, 'is_active' => true]);
        $this->dose($this->patient($unit, 'DefaultSession', 'No configured schedule'), '2026-11-12');
        $this->dose($this->patient($unit, 'AreaSession', 'Area Two'), '2026-11-12');

        $this->withSession(['user_id' => $staff->id])->get(route('vaccination.calendar', [
            'month' => '2026-11', 'day' => '2026-11-12',
        ]))->assertOk()->assertSee('DefaultSession Child')->assertSee('AreaSession Child')
            ->assertViewHas('selectedSchedules', fn ($rows) => count($rows) === 2);
    }

    public function test_area_lookup_ignores_inactive_and_foreign_patterns_and_defaults_when_unconfigured(): void
    {
        $unit = HealthUnit::create(['name' => 'Scoped RHU', 'immunization_pattern' => 'weekly',
            'immunization_weekday' => Carbon::WEDNESDAY]);
        $foreign = HealthUnit::create(['name' => 'Foreign RHU']);
        foreach ([[$unit->id, false], [$foreign->id, true]] as [$unitId, $active]) {
            RhuImmunizationSchedule::create(['health_unit_id' => $unitId, 'area_label' => 'Area One',
                'pattern' => 'weekly', 'weekday' => Carbon::FRIDAY, 'is_active' => $active]);
        }
        $service = app(VaccinationScheduleService::class);
        $this->assertSame('2026-11-18', $service->sessionDateFor(Carbon::parse('2026-11-16'), $unit->id, 'Area One')->toDateString());
        $this->assertSame('2026-11-18', $service->sessionDateFor(Carbon::parse('2026-11-16'), $unit->id, null)->toDateString());
    }

    public function test_area_specific_target_dates_are_cached_separately_and_completed_doses_stay_excluded(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Distinct RHU', 'immunization_pattern' => 'exact']);
        foreach ([['Area One', Carbon::THURSDAY], ['Area Two', Carbon::FRIDAY]] as [$area, $weekday]) {
            RhuImmunizationSchedule::create(['health_unit_id' => $unit->id, 'area_label' => $area,
                'pattern' => 'weekly', 'weekday' => $weekday, 'is_active' => true]);
            $this->dose($this->patient($unit, $area, $area), '2026-11-04');
        }
        $completed = $this->dose($this->patient($unit, 'Completed', 'Area One'), '2026-11-04',
            ['status' => 'given', 'given_date' => '2026-11-04']);
        $staff = User::factory()->create(['health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $staff->id])->get(route('vaccination.calendar', [
            'month' => '2026-11', 'day' => '2026-11-04',
        ]))->assertOk()->assertViewHas('selectedSchedules', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $completed->id);
        $planned = app(ForecastService::class)->getPlannedForecast($unit->id, 7);
        $this->assertSame(['2026-11-05', '2026-11-06'], collect($planned['items'])->where('type', 'vaccine_targets')->pluck('date')->all());
        $this->assertSame(2, $planned['summary']['total_targets']);
        $this->assertSame('given', $completed->fresh()->status);
        $this->assertSame('2026-11-04', $completed->fresh()->given_date->toDateString());
    }

    public function test_monthly_session_shift_from_the_previous_month_remains_in_upcoming_targets(): void
    {
        $this->travelTo(Carbon::parse('2026-11-17 10:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Monthly RHU', 'immunization_pattern' => 'monthly_last',
            'immunization_weekday' => Carbon::MONDAY]);
        $this->dose($this->patient($unit, 'MonthBoundary'), '2026-10-27');
        $planned = app(ForecastService::class)->getPlannedForecast($unit->id, 30);
        $this->assertSame(1, $planned['summary']['total_targets']);
        $this->assertSame(['2026-11-30'], collect($planned['items'])->where('type', 'vaccine_targets')->pluck('date')->all());
    }

    public function test_missed_dose_catch_up_uses_the_area_session_and_keeps_completed_doses_intact(): void
    {
        $this->travelTo(Carbon::parse('2026-11-17 10:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Catch-up RHU', 'immunization_pattern' => 'weekly',
            'immunization_weekday' => Carbon::WEDNESDAY]);
        RhuImmunizationSchedule::create(['health_unit_id' => $unit->id, 'area_label' => 'Area One',
            'pattern' => 'weekly', 'weekday' => Carbon::FRIDAY, 'is_active' => true]);
        $child = $this->patient($unit, 'Catchup', 'Area One');
        $missed = $this->dose($child, '2026-01-01', ['status' => 'missed']);
        $completed = $this->dose($child, '2026-01-01', ['vaccine_name' => 'MMR', 'status' => 'given', 'given_date' => '2026-10-01']);
        $this->dose($child, '2026-01-01', ['vaccine_name' => 'MMR', 'status' => 'missed']);
        $this->withSession(['health_unit_id' => $unit->id]);

        $service = app(VaccinationScheduleService::class);
        $this->assertSame(1, $service->rescheduleMissedDoses());
        $this->assertSame(0, $service->rescheduleMissedDoses());
        $this->assertSame('2026-11-20', VaccinationSchedule::where('status', 'pending')->sole()->due_date->toDateString());
        $this->assertSame('missed', $missed->fresh()->status);
        $this->assertSame('given', $completed->fresh()->status);
        $this->assertSame('2026-10-01', $completed->fresh()->given_date->toDateString());
    }
}
