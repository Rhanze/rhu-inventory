<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\HealthUnit;
use App\Models\ImmunizationRecord;
use App\Models\ImmunizationSchedule;
use App\Models\PostpartumVisit;
use App\Models\PublicUser;
use App\Models\RhuImmunizationSchedule;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Services\VaccinationScheduleService;
use App\Support\ClinicalAudit;
use Carbon\Carbon;
use Database\Seeders\ImmunizationScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaccinationSchedulingAuditTest extends TestCase
{
    use RefreshDatabase;

    private function unit(array $attributes = []): HealthUnit
    {
        return HealthUnit::create($attributes + ['name' => 'Scheduling RHU']);
    }

    private function patient(HealthUnit $unit): PublicUser
    {
        return PublicUser::create(['health_unit_id' => $unit->id, 'first_name' => 'Schedule',
            'last_name' => 'Patient', 'birth_date' => '2026-01-01', 'address' => 'Local address']);
    }

    public function test_weekly_epi_dates_include_every_remaining_week_and_the_first_of_month(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 14:00:00', 'Asia/Manila'));
        $unit = $this->unit(['immunization_pattern' => 'weekly', 'immunization_weekday' => Carbon::SUNDAY]);

        $dates = app(VaccinationScheduleService::class)->upcomingSessionDates($unit, 1);

        $this->assertSame(['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29'],
            array_map(fn ($date) => $date->toDateString(), $dates));
    }

    public function test_sessions_use_the_first_matching_day_on_or_after_the_dose_due_date(): void
    {
        $service = app(VaccinationScheduleService::class);
        $weekly = $this->unit(['immunization_pattern' => 'weekly', 'immunization_weekday' => Carbon::WEDNESDAY]);
        $monthly = $this->unit(['immunization_pattern' => 'monthly_first', 'immunization_weekday' => Carbon::MONDAY]);

        $this->assertSame('2026-11-18', $service->sessionDateFor(Carbon::parse('2026-11-12'), $weekly->id)->toDateString());
        $this->assertSame('2026-11-18', $service->sessionDateFor(Carbon::parse('2026-11-18'), $weekly->id)->toDateString());
        $this->assertSame('2026-12-07', $service->sessionDateFor(Carbon::parse('2026-11-03'), $monthly->id)->toDateString());
        $this->assertSame('2026-02-02', $service->sessionDateFor(Carbon::parse('2026-01-31'), $monthly->id)->toDateString());
    }

    public function test_rescheduling_uses_an_upcoming_session_and_does_not_repeat_completed_doses(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $unit = $this->unit(['immunization_pattern' => 'weekly', 'immunization_weekday' => Carbon::WEDNESDAY]);
        $patient = $this->patient($unit);
        $base = ['health_unit_id' => $unit->id, 'public_user_id' => $patient->id, 'dose_number' => 1];
        VaccinationSchedule::create($base + ['vaccine_name' => 'BCG', 'due_date' => '2026-01-01', 'status' => 'missed']);
        VaccinationSchedule::create($base + ['vaccine_name' => 'MMR', 'due_date' => '2026-01-01', 'status' => 'missed']);
        VaccinationSchedule::create($base + ['vaccine_name' => 'MMR', 'due_date' => '2026-09-01', 'status' => 'given']);
        $this->withSession(['health_unit_id' => $unit->id]);
        $service = app(VaccinationScheduleService::class);

        $this->assertSame(1, $service->rescheduleMissedDoses());
        $this->assertSame(0, $service->rescheduleMissedDoses());
        $pending = VaccinationSchedule::where('status', 'pending')->sole();
        $this->assertSame('BCG', $pending->vaccine_name);
        $this->assertSame('2026-11-11', $pending->due_date->toDateString());
    }

    public function test_report_reads_leave_appointments_unchanged_and_maintenance_creates_one_linked_replacement(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $unit = $this->unit();
        $patient = $this->patient($unit);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $base = ['health_unit_id' => $unit->id, 'public_user_id' => $patient->id, 'dose_number' => 1];
        $earlier = VaccinationSchedule::create($base + ['vaccine_name' => 'bcg', 'due_date' => '2026-09-01', 'status' => 'missed']);
        $current = VaccinationSchedule::create($base + ['vaccine_name' => 'BCG', 'due_date' => '2026-10-01', 'status' => 'pending']);
        $this->withSession(['user_id' => $staff->id]);
        foreach (['vaccination.forecast', 'vaccination.calendar', 'vaccination.monthly', 'vaccination.monthly.export'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->assertSame('pending', $current->fresh()->status);
        $this->assertDatabaseCount('vaccination_schedules', 2);

        $this->artisan('vaccinations:maintain-schedule')->expectsOutput('Appointments marked missed: 1')
            ->expectsOutput('Replacement appointments created: 1')->assertSuccessful();
        $this->artisan('vaccinations:maintain-schedule')->expectsOutput('Replacement appointments created: 0')->assertSuccessful();
        $this->assertSame('missed', $current->fresh()->status);
        $this->assertNull($earlier->replacement);
        $replacement = $current->replacement()->sole();
        $this->assertSame('pending', $replacement->status);
        $this->assertSame('2026-11-06', $replacement->due_date->toDateString());
        $this->assertDatabaseCount('vaccination_schedules', 3);
        $this->assertDatabaseHas('activity_logs', ['module' => ClinicalAudit::MODULE, 'user_id' => null,
            'action' => 'Reschedule', 'health_unit_id' => $unit->id,
            'description' => 'vaccination_schedule #'.$replacement->id.' (schedule maintenance)']);
    }

    public function test_postpartum_planning_counts_one_recipient_per_delivery(): void
    {
        $this->travelTo(Carbon::parse('2026-04-10 14:00:00', 'Asia/Manila'));
        $unit = $this->unit();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $patient = $this->patient($unit);
        $patient->update(['birth_date' => '2000-01-01']);
        $base = ['health_unit_id' => $unit->id, 'patient_id' => $patient->id, 'delivery_date' => '2026-04-01'];
        PostpartumVisit::create($base + ['visit_date' => '2026-04-02', 'visit_number' => 1]);
        PostpartumVisit::create($base + ['visit_date' => '2026-04-08', 'visit_number' => 2]);

        $this->withSession(['user_id' => $staff->id])->get(route('vaccination.calendar', ['month' => '2026-04']))
            ->assertOk()->assertViewHas('vitaminAEligibility', fn ($rows) => $rows['postpartum_4w'] === 1 && $rows['capsules_200k'] === 1);
    }

    public function test_overdue_tcl_results_include_children_after_the_first_three_hundred(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $unit = $this->unit();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $older = $this->patient($unit);
        $older->update(['birth_date' => '2024-01-01']);
        ImmunizationSchedule::create(['antigen' => 'fictional_dose', 'antigen_label' => 'Synthetic dose', 'dose_number' => 1,
            'min_age_days' => 365, 'recommended_age_label' => 'One year']);
        $younger = ['health_unit_id' => $unit->id, 'first_name' => 'Fictional', 'last_name' => 'Infant',
            'address' => 'Local address', 'birth_date' => '2026-08-01'];
        foreach (range(1, 3) as $batch) {
            PublicUser::insert(array_fill(0, 100, $younger));
        }

        $this->withSession(['user_id' => $staff->id])->get(route('immunization.tcl', ['page' => 7]))
            ->assertOk()->assertViewHas('children', fn ($rows) => $rows->total() === 301 && $rows->sole()->id === $older->id);
        $this->get(route('immunization.tcl', ['filter' => 'overdue']))
            ->assertOk()->assertViewHas('children', fn ($rows) => $rows->total() === 1 && $rows->sole()->id === $older->id);
    }

    public function test_calendar_includes_a_dose_shifted_from_the_previous_month(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $unit = $this->unit(['immunization_pattern' => 'monthly_last', 'immunization_weekday' => Carbon::MONDAY]);
        $patient = $this->patient($unit);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $patient->id,
            'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => '2026-10-27', 'status' => 'pending']);

        $this->withSession(['user_id' => $staff->id])->get(route('vaccination.calendar', ['month' => '2026-11', 'day' => '2026-11-30']))
            ->assertOk()->assertViewHas('selectedSchedules', fn ($rows) => count($rows) === 1 && $rows[0]['vaccine'] === 'BCG');
    }

    public function test_calendar_record_dose_links_to_the_exact_child_when_names_repeat(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $this->seed(ImmunizationScheduleSeeder::class);
        $unit = $this->unit();
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $patient = $this->patient($unit);
        $sameName = $this->patient($unit);
        $unassigned = $this->patient($unit);
        foreach ([[$patient, $midwife], [$sameName, $midwife], [$unassigned, $colleague]] as [$child, $viewer]) {
            $child->update(['assigned_midwife_id' => $viewer->id]);
        }
        VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $patient->id,
            'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => '2026-11-06', 'status' => 'pending']);

        $calendar = route('vaccination.calendar', ['month' => '2026-11', 'day' => '2026-11-06']);
        $target = route('immunization.tcl', ['patient' => $patient->id]);
        $this->withSession(['user_id' => $midwife->id])->get($calendar)->assertOk()->assertSee('Record Dose')->assertSee($target);
        $this->get($target)->assertOk()->assertSee('Reset')->assertViewHas('children', fn ($rows) => $rows->pluck('id')->all() === [$patient->id]);
        $this->get(route('immunization.tcl', ['patient' => $unassigned->id]))->assertOk()
            ->assertViewHas('children', fn ($rows) => $rows->isEmpty());
        $this->from($target)->post(route('immunization.store'), ['public_user_id' => $patient->id,
            'vaccine' => 'bcg', 'dose_number' => 1, 'date_given' => '2026-11-06'])->assertSessionHas('success');
        $this->assertSame($patient->id, ImmunizationRecord::sole()->public_user_id);
        $this->assertDatabaseHas('vaccination_schedules', ['public_user_id' => $patient->id, 'status' => 'given']);
    }

    public function test_rhu_calendar_keeps_epi_events_visible_but_omits_the_area_schedule_card_for_admins(): void
    {
        $unit = $this->unit();
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $schedule = RhuImmunizationSchedule::create(['health_unit_id' => $unit->id, 'area_label' => 'Poblacion 1',
            'pattern' => 'monthly_first', 'weekday' => Carbon::THURSDAY, 'is_active' => true]);
        $date = '2026-11-05';
        $eventAttributes = ['health_unit_id' => $unit->id, 'event_date' => $date, 'priority' => 'normal', 'created_by' => $midwife->id];
        $epi = CalendarEvent::create($eventAttributes + ['title' => 'Barangay Immunization Day', 'type' => 'immunization_day', 'area_label' => $schedule->area_label]);
        $ordinary = CalendarEvent::create($eventAttributes + ['title' => 'Calendar training', 'type' => 'training']);

        foreach (['admin', 'staff'] as $role) {
            $viewer = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
            $page = $this->withSession(['user_id' => $viewer->id])->get(route('vaccination.calendar', ['month' => '2026-11', 'day' => $date]))
                ->assertOk()
                ->assertSee($epi->title)->assertSee('Add Event')->assertSee('Forecast')
                ->assertDontSee('Generate EPI Days')->assertDontSee('Clear EPI Days')->assertDontSee('Add Schedule')
                ->assertDontSee('value="'.CalendarEvent::TYPE_EPI_DAY.'"', false)
                ->assertDontSee(route('vaccination.epi_schedule.destroy', $schedule->id))
                ->assertDontSee(route('calendar_events.destroy', $epi->id))
                ->assertSee(route('calendar_events.destroy', $ordinary->id));

            if ($role === 'admin') {
                $page->assertDontSee('Area EPI Schedules')->assertDontSee('First Thursday of the month');
            } else {
                $page->assertSee('Area EPI Schedules')->assertSee('Poblacion 1')->assertSee('First Thursday of the month');
            }

            $this->assertSame(1, substr_count($page->getContent(), 'class="js-edit-event"'));
        }
    }

    public function test_doses_due_today_are_pending_until_the_following_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $unit = $this->unit();
        $patient = $this->patient($unit);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $schedule = VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $patient->id,
            'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => '2026-11-06', 'status' => 'pending']);

        $this->assertFalse($schedule->is_overdue);
        $this->withSession(['user_id' => $staff->id])->get(route('vaccination.calendar', ['month' => '2026-11', 'day' => '2026-11-06']))
            ->assertOk()->assertViewHas('selectedSchedules', fn ($rows) => count($rows) === 1 && ! $rows[0]['is_overdue']);
        $this->travelTo(Carbon::parse('2026-11-07 00:00:00', 'Asia/Manila'));
        $this->assertTrue($schedule->is_overdue);
    }

    public function test_calendar_generation_preserves_recorded_immunizations_and_excludes_adults(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $this->seed(ImmunizationScheduleSeeder::class);
        $unit = $this->unit();
        $child = $this->patient($unit);
        $adult = $this->patient($unit);
        $adult->update(['birth_date' => '2000-01-01']);
        $record = ImmunizationRecord::create(['public_user_id' => $child->id, 'health_unit_id' => $unit->id,
            'antigen' => 'bcg', 'dose_number' => 1, 'date_given' => '2026-01-02']);
        $service = app(VaccinationScheduleService::class);

        $this->assertSame(16, $service->generateFor($child));
        $this->assertSame(0, $service->generateFor($adult));
        $schedule = $child->vaccinationSchedules()->where('vaccine_name', 'BCG')->sole();
        $this->assertSame('given', $schedule->status);
        $this->assertSame($record->id, $schedule->immunization_record_id);
        $this->assertSame('2026-01-02', $schedule->given_date->toDateString());
    }

    public function test_recording_a_dose_creates_the_missing_calendar_entry(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $this->seed(ImmunizationScheduleSeeder::class);
        $unit = $this->unit();
        $child = $this->patient($unit);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);

        $this->withSession(['user_id' => $staff->id])->post(route('immunization.store'), [
            'public_user_id' => $child->id, 'vaccine' => 'bcg', 'dose_number' => 1, 'date_given' => '2026-11-06',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('vaccination_schedules', ['public_user_id' => $child->id, 'vaccine_name' => 'BCG',
            'status' => 'given', 'given_date' => '2026-11-06', 'immunization_record_id' => ImmunizationRecord::sole()->id]);
    }

    public function test_administration_completes_one_replacement_and_keeps_the_missed_appointment(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $this->seed(ImmunizationScheduleSeeder::class);
        $unit = $this->unit();
        $child = $this->patient($unit);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $base = ['public_user_id' => $child->id, 'health_unit_id' => $unit->id, 'dose_number' => 1];
        $missed = VaccinationSchedule::create($base + ['vaccine_name' => 'BCG', 'due_date' => '2026-10-01', 'status' => 'missed']);
        $replacement = VaccinationSchedule::create($base + ['vaccine_name' => 'bcg', 'due_date' => '2026-11-06',
            'status' => 'pending', 'rescheduled_from_id' => $missed->id]);
        $duplicate = VaccinationSchedule::create($base + ['vaccine_name' => 'BCG', 'due_date' => '2026-11-05', 'status' => 'pending']);

        $this->withSession(['user_id' => $staff->id])->post(route('immunization.store'), [
            'public_user_id' => $child->id, 'vaccine' => 'bcg', 'dose_number' => 1, 'date_given' => '2026-11-06',
        ])->assertSessionHas('success');
        app(VaccinationScheduleService::class)->generateFor($child);

        $record = ImmunizationRecord::sole();
        $this->assertSame($replacement->id, $record->vaccinationSchedule()->sole()->id);
        $this->assertSame('missed', $missed->fresh()->status);
        $this->assertNull($missed->fresh()->immunization_record_id);
        $this->assertNotNull($duplicate->fresh()->superseded_at);
        $this->assertSame($replacement->id, $missed->replacement()->sole()->id);

        // A historical duplicate from an older deployment must not count again.
        VaccinationSchedule::create($base + ['vaccine_name' => 'BCG', 'due_date' => '2026-11-03',
            'status' => 'given', 'given_date' => '2026-11-06', 'immunization_record_id' => $record->id]);
        VaccinationSchedule::create($base + ['vaccine_name' => 'bcg', 'due_date' => '2026-11-03',
            'status' => 'given', 'given_date' => '2026-11-06']);
        $this->get(route('vaccination.forecast'))->assertOk()
            ->assertViewHas('monthStats', fn ($stats) => $stats['given_month'] === 1);
        $this->get(route('vaccination.child', $child))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['given'] === 1 && $stats['missed'] === 1);
    }

    public function test_repeated_legacy_appointment_rows_count_only_in_the_first_recorded_month(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
        $unit = $this->unit();
        $child = $this->patient($unit);
        $service = app(VaccinationScheduleService::class);
        foreach (['2026-10-20', '2026-11-04'] as $date) {
            VaccinationSchedule::create(['public_user_id' => $child->id, 'health_unit_id' => $unit->id,
                'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => $date, 'given_date' => $date, 'status' => 'given']);
        }
        $this->assertSame(1, $service->countAdministrations($unit->id, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')));
        $this->assertSame(0, $service->countAdministrations($unit->id, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-06')));
    }

    public function test_maintenance_waits_for_the_displayed_monthly_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-27 14:00:00', 'Asia/Manila'));
        $unit = $this->unit(['immunization_pattern' => 'monthly_last', 'immunization_weekday' => Carbon::MONDAY]);
        $child = $this->patient($unit);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $appointment = VaccinationSchedule::create(['public_user_id' => $child->id, 'health_unit_id' => $unit->id,
            'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => '2026-10-27', 'status' => 'pending']);

        $this->artisan('vaccinations:maintain-schedule')->expectsOutput('Appointments marked missed: 0')->assertSuccessful();
        $this->assertSame('pending', $appointment->fresh()->status);
        $this->assertNull($appointment->replacement);
        $this->withSession(['user_id' => $staff->id])->get(route('vaccination.forecast'))->assertOk()
            ->assertViewHas('overdue', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('upcoming', fn ($rows) => $rows->sole()->session_date->toDateString() === '2026-11-30');
    }
}
