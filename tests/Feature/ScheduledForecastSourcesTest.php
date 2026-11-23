<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\DoHProgram;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\ImmunizationRecord;
use App\Models\Medicine;
use App\Models\PublicUser;
use App\Models\RhuImmunizationSchedule;
use App\Models\StockLog;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Services\ForecastService;
use Carbon\Carbon;
use Database\Seeders\ImmunizationScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduledForecastSourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 15:00:00', 'Asia/Manila'));
    }

    public function test_calendar_epi_national_programs_and_real_pending_dose_targets_use_the_current_schema(): void
    {
        $unit = HealthUnit::create(['name' => 'Scheduled RHU', 'facility_type' => 'Rural Health Unit',
            'immunization_pattern' => 'weekly', 'immunization_weekday' => Carbon::WEDNESDAY]);
        $actor = User::factory()->create(['health_unit_id' => $unit->id]);
        foreach ([['Training', today(), 'training', null], ['EPI area', '2026-11-18', 'immunization_day', 'Area One']] as [$title, $date, $type, $area]) {
            CalendarEvent::create(['health_unit_id' => $unit->id, 'title' => $title, 'event_date' => $date,
                'type' => $type, 'priority' => 'normal', 'area_label' => $area, 'created_by' => $actor->id]);
        }
        $deleted = CalendarEvent::create(['health_unit_id' => $unit->id, 'title' => 'Removed event',
            'event_date' => today(), 'type' => 'other', 'created_by' => $actor->id]);
        DB::table('calendar_events')->where('id', $deleted->id)->update(['deleted_at' => now()]);
        RhuImmunizationSchedule::create(['health_unit_id' => $unit->id, 'area_label' => 'Area One',
            'pattern' => 'weekly', 'weekday' => Carbon::WEDNESDAY, 'is_active' => true]);
        DoHProgram::create(['name' => 'Configured November program', 'recurrence' => 'yearly',
            'month' => 11, 'day' => 20, 'duration_days' => 1, 'is_active' => true]);
        $child = PublicUser::create(['health_unit_id' => $unit->id, 'first_name' => 'Target',
            'last_name' => 'Child', 'birth_date' => today()->subYear(), 'address' => 'Local']);
        foreach (['BCG', 'Hepatitis B', 'BCG'] as $vaccine) {
            VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $child->id,
                'vaccine_name' => $vaccine, 'dose_number' => 1, 'due_date' => today(), 'status' => 'pending']);
        }
        VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $child->id,
            'vaccine_name' => 'Completed vaccine', 'dose_number' => 1, 'due_date' => today(), 'status' => 'given']);

        $result = app(ForecastService::class)->getPlannedForecast($unit->id, 7);
        $this->assertSame(3, $result['summary']['total_events']);
        $this->assertSame(2, $result['summary']['total_targets']);
        $this->assertSame(1, $result['summary']['target_children']);
        $this->assertSame(2, $result['summary']['by_month']['2026-11']);
        $this->assertSame(3, $result['summary']['events_by_month']['2026-11']);
        $targets = collect($result['items'])->where('type', 'vaccine_targets');
        $this->assertSame(['2026-11-18'], $targets->pluck('date')->unique()->values()->all());
        $this->assertTrue(collect($result['items'])->where('type', 'training')->first()['target_count'] === null);
    }

    public function test_forecast_targets_are_facility_scoped_and_the_horizon_counts_exactly_ninety_dates(): void
    {
        $unit = HealthUnit::create(['name' => 'Scoped RHU']);
        $other = HealthUnit::create(['name' => 'Other RHU']);
        $actor = User::factory()->create();
        foreach ([[$unit->id, today()->addDays(89), 'Included'], [$unit->id, today()->addDays(90), 'Outside horizon'],
            [$other->id, today(), 'Foreign event']] as [$unitId, $date, $title]) {
            CalendarEvent::create(['health_unit_id' => $unitId, 'title' => $title, 'event_date' => $date,
                'type' => 'other', 'created_by' => $actor->id]);
        }
        $result = app(ForecastService::class)->getPlannedForecast($unit->id, 90);
        $this->assertSame(['Included'], array_column($result['items'], 'title'));
        $this->assertSame('2027-02-14', $result['summary']['through']);
    }

    public function test_event_only_forecast_explains_missing_dose_targets_and_uses_real_event_counts(): void
    {
        $unit = HealthUnit::create(['name' => 'Event-only RHU']);
        $actor = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        CalendarEvent::create(['health_unit_id' => $unit->id, 'title' => 'Stock delivery', 'event_date' => today(),
            'type' => 'stock_delivery', 'created_by' => $actor->id]);

        $this->withSession(['user_id' => $actor->id])->get(route('forecast.index'))->assertOk()
            ->assertSee('Stock delivery')->assertSee('No pending vaccine appointments fall in this window.')
            ->assertSee('Scheduled events')->assertSee('Not specified')
            ->assertDontSee('How to read this forecast')->assertDontSee('R&sup2;', false)
            ->assertDontSee('historical fit R-squared');
    }

    public function test_recorded_immunizations_and_antigen_aliases_do_not_duplicate_pending_targets(): void
    {
        $this->seed(ImmunizationScheduleSeeder::class);
        $unit = HealthUnit::create(['name' => 'Legacy dose RHU']);
        $child = PublicUser::create(['health_unit_id' => $unit->id, 'first_name' => 'Legacy',
            'last_name' => 'Child', 'birth_date' => today()->subYear(), 'address' => 'Local']);
        foreach (['BCG', 'Hepatitis B', 'hepb', 'Pentavalent'] as $vaccine) {
            VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $child->id,
                'vaccine_name' => $vaccine, 'dose_number' => 1, 'due_date' => today(), 'status' => 'pending']);
        }
        ImmunizationRecord::create(['health_unit_id' => $unit->id, 'public_user_id' => $child->id,
            'antigen' => 'bcg', 'dose_number' => 1, 'date_given' => today()->subMonth()]);
        VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $child->id,
            'vaccine_name' => 'penta', 'dose_number' => 1, 'due_date' => today()->subMonth(), 'status' => 'given']);

        $result = app(ForecastService::class)->getPlannedForecast($unit->id);
        $this->assertSame(1, $result['summary']['total_targets']);
        $this->assertSame(['Hepatitis B'], collect($result['items'])->where('type', 'vaccine_targets')->pluck('medicine_name')->all());
    }

    public function test_item_basis_exposes_total_need_history_and_additional_stock_as_separate_values(): void
    {
        $unit = HealthUnit::create(['name' => 'Basis RHU']);
        $medicine = Medicine::create(['medicine_name' => 'Basis medicine', 'unit' => 'tablet']);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 30]);
        for ($week = 0; $week < 12; $week++) {
            StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'quantity_added' => 70,
                'movement_type' => 'dispensing', 'date_added' => today()->subWeeks(12)->addWeeks($week)]);
        }
        $item = app(ForecastService::class)->getForecast($unit->id)['items']->first();
        $this->assertSame(840, $item['history_total']);
        $this->assertSame(900, $item['forecast_need_3mo']);
        $this->assertSame(870, $item['shortage']);
        $this->assertEqualsWithDelta(840, $item['forecast_full_weeks_need'], 1e-8);
        $this->assertEqualsWithDelta(60, $item['forecast_partial_week_need'], 1e-8);
        $actor = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $actor->id])->get(route('forecast.index'))->assertOk()
            ->assertSee('Demand basis')->assertSee('Additional stock needed 870')
            ->assertSee('id="forecast-item-'.$unit->id.'-'.$medicine->id.'"', false);
    }
}
