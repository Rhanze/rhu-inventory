<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\HealthUnit;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\RhuImmunizationSchedule;
use App\Models\User;
use App\Models\VaccinationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidwifeCalendarAreaScopeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Calendar assigned RHU', 'facility_type' => 'Rural Health Unit']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Assigned Area', 'is_primary' => true]);
        MidwifeBarangay::create(['user_id' => $colleague->id, 'barangay' => 'Other Area']);
        PublicUser::create(['health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwife->id,
            'first_name' => 'Historic', 'last_name' => 'Patient', 'birth_date' => '2000-01-01',
            'address' => 'Fixture address', 'barangay' => 'Historic Area']);
        $this->withSession(['user_id' => $midwife->id]);

        return [$unit, $midwife];
    }

    private function eventPayload(array $extra = []): array
    {
        return $extra + ['title' => 'Assigned clinic', 'event_date' => today()->addDay()->toDateString(),
            'type' => 'other', 'priority' => 'normal'];
    }

    private function schedule(HealthUnit $unit, string $area): RhuImmunizationSchedule
    {
        return RhuImmunizationSchedule::create(['health_unit_id' => $unit->id, 'area_label' => $area,
            'pattern' => 'weekly', 'weekday' => 1, 'is_active' => true]);
    }

    public function test_calendar_and_epi_choices_use_assignments_even_before_any_patients_exist_in_the_area(): void
    {
        [$unit] = $this->fixture();
        $own = $this->schedule($unit, 'assigned area');
        $this->schedule($unit, 'Other Area');
        $response = $this->get(route('vaccination.calendar'))->assertOk()
            ->assertViewHas('barangays', fn ($areas) => $areas->all() === ['Assigned Area'])
            ->assertViewHas('epiSchedules', fn ($schedules) => $schedules->pluck('id')->all() === [$own->id])
            ->assertSee('Area EPI Schedules')->assertSee('Add Schedule')
            ->assertSee('value="'.CalendarEvent::TYPE_EPI_DAY.'"', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $selects = $xpath->query('//select[@name="area_label"]');
        $this->assertSame(3, $selects->length);
        foreach ($selects as $select) {
            foreach ($xpath->query('.//option[@value!=""]', $select) as $option) {
                $this->assertSame('assigned area', mb_strtolower($option->getAttribute('value')));
            }
        }
        $this->assertSame(0, $xpath->query('//input[@name="area_label" and @type="text"]')->length);
    }

    public function test_custom_event_creation_rejects_unassigned_or_missing_areas_and_canonicalizes_the_assignment(): void
    {
        $this->fixture();
        foreach ([null, '', 'Other Area', 'Historic Area', 'Invented Area'] as $area) {
            $this->postJson(route('calendar_events.store'), $this->eventPayload(['area_label' => $area]))
                ->assertUnprocessable()->assertJsonValidationErrors('area_label');
        }
        $this->assertDatabaseCount('calendar_events', 0);
        $this->post(route('calendar_events.store'), $this->eventPayload(['area_label' => ' assigned   AREA ']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Assigned Area', CalendarEvent::sole()->area_label);
    }

    public function test_calendar_get_rejects_unassigned_area_filters_and_normalizes_assigned_filters(): void
    {
        [$unit, $midwife] = $this->fixture();
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Second Area']);
        $day = today()->addDay()->toDateString();
        foreach (['Assigned Area', 'Second Area'] as $area) {
            $patient = PublicUser::create(['health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwife->id,
                'first_name' => $area, 'last_name' => 'Patient', 'birth_date' => '2026-01-01',
                'address' => 'Fixture address', 'barangay' => $area]);
            VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $patient->id,
                'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => $day, 'status' => 'pending']);
            CalendarEvent::create($this->eventPayload(['title' => $area.' event', 'area_label' => $area])
                + ['health_unit_id' => $unit->id, 'created_by' => $midwife->id]);
        }
        CalendarEvent::create($this->eventPayload(['title' => 'Shared RHU event'])
            + ['health_unit_id' => $unit->id, 'created_by' => $midwife->id]);

        foreach (['Other Area', 'Historic Area', 'Invented Area', ['Assigned Area']] as $area) {
            $this->getJson(route('vaccination.calendar', ['area_label' => $area]))
                ->assertUnprocessable()->assertJsonValidationErrors('area_label');
        }

        $response = $this->get(route('vaccination.calendar', ['month' => substr($day, 0, 7), 'day' => $day,
            'area_label' => '  assigned    AREA  ']))
            ->assertOk()->assertViewHas('calendarAreaFilter', 'Assigned Area')
            ->assertViewHas('selectedSchedules', fn ($rows) => count($rows) === 1 && $rows[0]['barangay'] === 'Assigned Area')
            ->assertViewHas('selectedEvents', fn ($rows) => collect($rows)->pluck('title')->sort()->values()->all()
                === ['Assigned Area event', 'Shared RHU event'])
            ->assertSee('Area: Assigned Area');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//a[contains(@class, "cal-cell") or contains(@class, "cal-nav-btn")]') as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame('Assigned Area', $query['area_label'] ?? null);
        }
    }

    public function test_historical_event_area_survives_omitted_edits_but_cannot_be_submitted_as_a_new_choice_or_deleted(): void
    {
        [$unit, $midwife] = $this->fixture();
        $event = CalendarEvent::create($this->eventPayload(['title' => 'Old clinic', 'area_label' => 'Historic Area'])
            + ['health_unit_id' => $unit->id, 'created_by' => $midwife->id]);
        $this->put(route('calendar_events.update', $event->id), $this->eventPayload(['title' => 'Corrected title']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Historic Area', $event->fresh()->area_label);
        $this->putJson(route('calendar_events.update', $event->id), $this->eventPayload(['area_label' => 'Historic Area']))
            ->assertUnprocessable()->assertJsonValidationErrors('area_label');
        $this->deleteJson(route('calendar_events.destroy', $event->id))->assertUnprocessable()->assertJsonValidationErrors('area_label');
        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'area_label' => 'Historic Area']);
        $this->put(route('calendar_events.update', $event->id), $this->eventPayload(['area_label' => ' assigned   AREA ']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Assigned Area', $event->fresh()->area_label);
    }

    public function test_midwife_cannot_edit_or_claim_a_colleagues_out_of_assignment_event(): void
    {
        [$unit, $midwife] = $this->fixture();
        $colleague = User::where('health_unit_id', $unit->id)->whereKeyNot($midwife->id)->sole();
        $event = CalendarEvent::create($this->eventPayload(['title' => 'Colleague clinic', 'area_label' => 'Other Area'])
            + ['health_unit_id' => $unit->id, 'created_by' => $colleague->id]);
        foreach ([$this->eventPayload(['title' => 'Tampered title']), $this->eventPayload(['area_label' => 'Assigned Area'])] as $payload) {
            $this->putJson(route('calendar_events.update', $event->id), $payload)->assertForbidden();
        }
        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'title' => 'Colleague clinic', 'area_label' => 'Other Area']);
        $response = $this->get(route('vaccination.calendar', ['day' => $event->event_date->toDateString()]))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertSame(0, (new \DOMXPath($document))->query('//button[contains(@class,"js-edit-event")]')->length);
    }

    public function test_case_normalized_existing_epi_area_does_not_create_duplicate_schedules(): void
    {
        [$unit] = $this->fixture();
        $existing = $this->schedule($unit, ' assigned area ');
        $this->post(route('vaccination.epi_schedule.store'), ['area_label' => 'ASSIGNED   AREA', 'pattern' => 'weekly', 'weekday' => 2])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('rhu_immunization_schedules', 1);
        $this->assertSame(1, $existing->fresh()->weekday);
    }

    public function test_epi_generation_clear_and_schedule_deletion_stay_within_current_assignments(): void
    {
        [$unit, $midwife] = $this->fixture();
        $otherSchedule = $this->schedule($unit, 'Other Area');
        $this->postJson(route('vaccination.epi_schedule.store'), ['area_label' => 'Other Area', 'pattern' => 'weekly', 'weekday' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('area_label');
        $this->post(route('vaccination.epi_schedule.store'), ['area_label' => 'assigned area', 'pattern' => 'weekly', 'weekday' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->deleteJson(route('vaccination.epi_schedule.destroy', $otherSchedule->id))->assertUnprocessable()->assertJsonValidationErrors('area_label');
        $this->post(route('vaccination.generate_epi_days'), ['months' => 1])->assertSessionHas('success');
        $this->assertGreaterThan(0, CalendarEvent::count());
        $this->assertSame(['Assigned Area'], CalendarEvent::distinct()->pluck('area_label')->all());
        $otherEvent = CalendarEvent::create($this->eventPayload(['title' => 'Other EPI', 'type' => 'immunization_day', 'area_label' => 'Other Area'])
            + ['health_unit_id' => $unit->id, 'created_by' => $midwife->id]);
        $this->postJson(route('vaccination.clear_epi_days'), ['area_label' => 'Other Area'])
            ->assertUnprocessable()->assertJsonValidationErrors('area_label');
        $this->post(route('vaccination.clear_epi_days'))->assertSessionHas('success');
        $this->assertSame([$otherEvent->id], CalendarEvent::pluck('id')->all());
    }

    public function test_unassigned_midwives_get_no_free_text_area_fallback_and_cannot_create_area_events(): void
    {
        [$unit, $midwife] = $this->fixture();
        MidwifeBarangay::where('user_id', $midwife->id)->delete();
        $this->schedule($unit, 'Other Area');
        $response = $this->get(route('vaccination.calendar'))->assertOk()
            ->assertViewHas('barangays', fn ($areas) => $areas->isEmpty())
            ->assertViewHas('epiSchedules', fn ($schedules) => $schedules->isEmpty())
            ->assertSee('No barangays are assigned to your account.');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@name="area_label" and @type="text"]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="area_label"]/option[@value!=""]')->length);
        $this->postJson(route('calendar_events.store'), $this->eventPayload(['area_label' => 'Other Area']))
            ->assertUnprocessable()->assertJsonValidationErrors('area_label');
        $this->postJson(route('vaccination.epi_schedule.store'), ['area_label' => 'Other Area', 'pattern' => 'weekly', 'weekday' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('area_label');
        $this->post(route('vaccination.generate_epi_days'))->assertSessionHas('error');
        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_admins_and_staff_keep_their_free_form_area_workflow_for_regular_events(): void
    {
        [$unit] = $this->fixture();
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
            $this->withSession(['user_id' => $user->id])->post(route('calendar_events.store'), $this->eventPayload(['area_label' => 'Custom '.$role.' area']))
                ->assertSessionHasNoErrors()->assertSessionHas('success');
        }
        $this->assertDatabaseCount('calendar_events', 2);
    }

    public function test_rhu_admins_and_staff_cannot_manage_epi_schedules_or_epi_day_events(): void
    {
        [$unit, $midwife] = $this->fixture();
        $schedule = $this->schedule($unit, 'Assigned Area');
        $epi = $this->eventPayload(['title' => 'Barangay EPI day', 'type' => CalendarEvent::TYPE_EPI_DAY, 'area_label' => 'Assigned Area']);
        $event = CalendarEvent::create($epi + ['health_unit_id' => $unit->id, 'created_by' => $midwife->id]);
        $regular = CalendarEvent::create($this->eventPayload() + ['health_unit_id' => $unit->id, 'created_by' => $midwife->id]);

        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
            $this->withSession(['user_id' => $user->id]);
            $this->postJson(route('vaccination.epi_schedule.store'), ['area_label' => 'New Area', 'pattern' => 'weekly', 'weekday' => 1])->assertForbidden();
            $this->deleteJson(route('vaccination.epi_schedule.destroy', $schedule->id))->assertForbidden();
            $this->postJson(route('vaccination.generate_epi_days'), ['months' => 1])->assertForbidden();
            $this->postJson(route('vaccination.clear_epi_days'))->assertForbidden();
            $this->postJson(route('calendar_events.store'), $epi)->assertForbidden();
            $this->putJson(route('calendar_events.update', $regular->id), $epi)->assertForbidden();
            $this->putJson(route('calendar_events.update', $event->id), $this->eventPayload())->assertForbidden();
            $this->deleteJson(route('calendar_events.destroy', $event->id))->assertForbidden();
        }

        $this->assertDatabaseCount('rhu_immunization_schedules', 1);
        $this->assertDatabaseCount('calendar_events', 2);
        $this->assertSame(CalendarEvent::TYPE_EPI_DAY, $event->refresh()->type);
        $this->assertSame('other', $regular->refresh()->type);
    }
}
