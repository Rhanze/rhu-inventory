<?php

namespace Tests\Feature;

use App\Models\DoHProgram;
use App\Models\CalendarEvent;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\StockLog;
use App\Models\User;
use App\Services\DohEventPlanningService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DohEventPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-12-18 10:00:00', 'Asia/Manila'));
    }

    private function unit(string $name = 'Planning RHU'): HealthUnit
    {
        return HealthUnit::create(['name' => $name, 'facility_type' => 'Rural Health Unit']);
    }

    private function program(array $attributes = []): DoHProgram
    {
        return DoHProgram::create($attributes + ['name' => 'National Immunization Day', 'recurrence' => 'yearly',
            'month' => 1, 'day' => 1, 'duration_days' => 1, 'is_active' => true, 'color' => '#42ad8a']);
    }

    private function history(HealthUnit $unit, Medicine $medicine, User $actor, int $weekly = 70): void
    {
        foreach ([28, 21, 14, 7] as $days) {
            StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'quantity_added' => $weekly, 'movement_type' => 'dispensing',
                'date_added' => today()->subDays($days), 'added_by' => $actor->id]);
        }
    }

    private function calendarUrl(string $date = '2027-01-01'): string
    {
        return route('vaccination.calendar', ['month' => substr($date, 0, 7), 'day' => $date]);
    }

    private function calendarTextOutsideDialogs(string $html): string
    {
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ((new \DOMXPath($document))->query('//dialog') as $dialog) {
            $dialog->parentNode->removeChild($dialog);
        }

        return preg_replace('/\s+/u', ' ', $document->textContent);
    }

    private function calendarSidebarLinks(string $html): \DOMNodeList
    {
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return (new \DOMXPath($document))->query('//aside[@id="app-sidebar"]//a[@href="'.route('vaccination.calendar').'"]');
    }

    public function test_rhu_admins_and_staff_can_reach_material_planning_from_the_calendar_sidebar_link(): void
    {
        $unit = $this->unit();
        $this->program();
        Medicine::create(['medicine_name' => 'Accessible program vaccine', 'category' => 'vaccine', 'unit' => 'vial']);

        foreach (['admin', 'staff'] as $role) {
            $viewer = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
            $navigation = $this->withSession(['user_id' => $viewer->id])->get(route('notifications.index'))->assertOk();
            $links = $this->calendarSidebarLinks($navigation->getContent());
            $this->assertCount(1, $links, $role.' needs one reachable calendar entry in the sidebar.');
            $this->assertSame('Schedule Calendar', trim($links->item(0)->textContent));

            $calendar = $this->get($links->item(0)->getAttribute('href').'?month=2027-01&day=2027-01-01')->assertOk()
                ->assertSee('Review and edit material plan')->assertSee('Your planned quantity (vial)')->assertSee('Save plan');
            $selected = $this->calendarSidebarLinks($calendar->getContent());
            $this->assertCount(1, $selected);
            $this->assertContains('active', explode(' ', $selected->item(0)->getAttribute('class')));
        }
    }

    public function test_calendar_sidebar_visibility_preserves_midwife_and_provincial_navigation_scope(): void
    {
        $unit = $this->unit();
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $page = $this->withSession(['user_id' => $midwife->id])->get(route('notifications.index'))->assertOk();
        $this->assertCount(1, $this->calendarSidebarLinks($page->getContent()));

        $provincial = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $page = $this->withSession(['user_id' => $provincial->id])->get(route('notifications.index'))->assertOk();
        $this->assertCount(0, $this->calendarSidebarLinks($page->getContent()));
    }

    public function test_recurrences_cross_months_and_years_and_skip_invalid_month_days(): void
    {
        $program = $this->program(['month' => 12, 'day' => 28, 'duration_days' => 7]);
        $this->assertTrue($program->occursOn(Carbon::parse('2027-01-02')));
        $this->assertFalse($program->occursOn(Carbon::parse('2027-01-04')));
        $this->assertSame(['2026-12-28'], array_map(fn ($date) => $date->toDateString(),
            $program->startsBetween(today(), today()->addDays(30))));
        $monthly = $this->program(['recurrence' => 'monthly', 'month' => null, 'day' => 31]);
        $this->assertSame([], $monthly->startsBetween(Carbon::parse('2027-02-01'), Carbon::parse('2027-02-28')));
        $leap = $this->program(['month' => 2, 'day' => 29]);
        $this->assertSame([], $leap->startsBetween(Carbon::parse('2027-02-01'), Carbon::parse('2027-03-01')));
    }

    public function test_existing_catalog_candidates_generate_quantities_and_shortages_without_a_manual_plan(): void
    {
        $unit = $this->unit();
        $actor = User::factory()->create(['health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Program vaccine', 'category' => 'vaccine', 'unit' => 'vial']);
        $this->program();
        $this->history($unit, $medicine, $actor);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 100, 'allocated_stock' => 500, 'expiration_date' => today()->addYear()]);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 200, 'expiration_date' => today()->subDay()]);
        $deleted = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 500, 'expiration_date' => today()->addYear()]);
        $deleted->delete();

        $item = app(DohEventPlanningService::class)->upcoming($unit->id)[0]['items'][0];
        $this->assertTrue($item['history_ready']);
        $this->assertSame(4, $item['history_weeks']);
        $this->assertSame(100, $item['current_stock']);
        $this->assertSame(0, $item['projected_stock']);
        $this->assertSame(10, $item['target_quantity']);
        $this->assertSame(40, $item['routine_shortage_before_program']);
        $this->assertSame(10, $item['program_shortage']);
        $this->assertSame(50, $item['stock_up']);
        $this->withSession(['user_id' => $actor->id])->get($this->calendarUrl())->assertOk()
            ->assertSee('Stock up 50 vial');
    }

    public function test_fefo_projection_uses_soon_expiring_batches_before_reducing_later_batches(): void
    {
        $unit = $this->unit();
        $actor = User::factory()->create(['health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Program vaccine', 'category' => 'vaccine']);
        $this->program();
        $this->history($unit, $medicine, $actor, 35);
        foreach ([today()->addDays(10), today()->addYear()] as $expiry) {
            Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'available_stock' => 100, 'expiration_date' => $expiry]);
        }

        $item = app(DohEventPlanningService::class)->upcoming($unit->id)[0]['items'][0];
        $this->assertSame(100, $item['expiry_eligible_stock']);
        $this->assertSame(85, $item['projected_stock']);
        $this->assertSame(0, $item['stock_up']);
        $this->withSession(['user_id' => $actor->id])->get($this->calendarUrl())->assertOk()
            ->assertSee('Stock is sufficient');
    }

    public function test_missing_history_stays_unquantified_and_a_local_plan_shows_a_minimum_shortage(): void
    {
        $unit = $this->unit();
        $medicine = Medicine::create(['medicine_name' => 'Program vaccine', 'category' => 'vaccine']);
        $program = $this->program();
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 5]);
        $service = app(DohEventPlanningService::class);
        $item = $service->upcoming($unit->id)[0]['items'][0];
        $this->assertFalse($item['history_ready']);
        $this->assertNull($item['target_quantity']);
        $this->assertNull($item['stock_up']);
        DB::table('doh_event_materials')->insert(['health_unit_id' => $unit->id, 'doh_program_id' => $program->id,
            'event_date' => '2027-01-01', 'medicine_id' => $medicine->id, 'quantity' => 20]);
        $item = $service->upcoming($unit->id)[0]['items'][0];
        $this->assertSame(20, $item['target_quantity']);
        $this->assertNull($item['projected_stock']);
        $this->assertSame(15, $item['stock_up']);
    }

    public function test_staff_can_refine_only_their_rhu_occurrence_and_calendar_explains_the_plan(): void
    {
        $unit = $this->unit();
        $otherUnit = $this->unit('Other RHU');
        $actor = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $program = $this->program();
        $medicine = Medicine::create(['medicine_name' => '<img src=x onerror=alert(1)>', 'category' => 'vaccine', 'unit' => 'vial']);
        $this->withSession(['user_id' => $actor->id])->post(route('vaccination.program_materials.store', $program), [
            'health_unit_id' => $otherUnit->id, 'event_date' => '2027-01-01',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 25]],
        ])->assertRedirect();
        $this->assertDatabaseHas('doh_event_materials', ['health_unit_id' => $unit->id, 'quantity' => 25]);
        $this->assertDatabaseMissing('doh_event_materials', ['health_unit_id' => $otherUnit->id]);
        $this->get($this->calendarUrl())->assertOk()
            ->assertDontSee('Upcoming DoH program preparation')->assertSee('Stock up at least 25 vial')
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->assertNull(app(DohEventPlanningService::class)->upcoming($otherUnit->id)[0]['items'][0]['planned_quantity']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $midwife->id])->post(route('vaccination.program_materials.store', $program), [
            'event_date' => '2027-01-01', 'items' => [['medicine_id' => $medicine->id, 'quantity' => 999]],
        ])->assertForbidden();
    }

    public function test_material_dialog_explains_unknown_history_and_preserves_role_specific_plan_controls(): void
    {
        $unit = $this->unit();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $program = $this->program();
        $medicine = Medicine::create(['medicine_name' => 'Dialog vaccine', 'category' => 'vaccine', 'unit' => 'vial']);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 381]);

        $response = $this->withSession(['user_id' => $staff->id])->get($this->calendarUrl())->assertOk()
            ->assertSee('Review and edit material plan')->assertSee('RHU shared stock available now')
            ->assertSee('No recorded release history is available.')->assertSee('Estimate unavailable')
            ->assertSee('Not estimated; set a quantity')->assertSee('Your planned quantity (vial)')
            ->assertDontSee('Projections reuse this RHU')->assertDontSee('Manual quantities are spread evenly over the program days');
        $document = new \DOMDocument;
        $prior = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($prior);
        $xpath = new \DOMXPath($document);
        $dialogId = 'program-plan-'.$program->id.'-2027-01-01';
        $this->assertSame(1, $xpath->query('//dialog[@id="'.$dialogId.'"]')->length);
        $this->assertSame(1, $xpath->query('//dialog[@id="'.$dialogId.'"]/form')->length);
        $this->assertSame(0, $xpath->query('//form//dialog[@id="'.$dialogId.'"]')->length);
        $this->assertSame(1, $xpath->query('//dialog[@id="'.$dialogId.'"]//input[@name="items[0][quantity]"]')->length);
        $this->assertSame('Save plan', trim($xpath->query('(//dialog[@id="'.$dialogId.'"]//button[@type="submit"])[1]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//dialog[@id="'.$dialogId.'"]//select[@name="additional_medicine_id"]/option[@value="'.$medicine->id.'"]')->length);

        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $response = $this->withSession(['user_id' => $midwife->id])->get($this->calendarUrl())->assertOk()
            ->assertSee('View stock details')->assertDontSee('Your planned quantity (vial)');
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//dialog[@id="'.$dialogId.'"]')->length);
        $this->assertSame(0, $xpath->query('//dialog[@id="'.$dialogId.'"]//form | //dialog[@id="'.$dialogId.'"]//input')->length);
    }

    public function test_additional_material_cannot_silently_overwrite_an_existing_plan_quantity(): void
    {
        $unit = $this->unit();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $program = $this->program();
        $medicine = Medicine::create(['medicine_name' => 'Existing program material', 'category' => 'vaccine']);
        DB::table('doh_event_materials')->insert(['health_unit_id' => $unit->id, 'doh_program_id' => $program->id,
            'event_date' => '2027-01-01', 'medicine_id' => $medicine->id, 'quantity' => 25]);
        $this->withSession(['user_id' => $staff->id])->post(route('vaccination.program_materials.store', $program), [
            'event_date' => '2027-01-01', 'items' => [['medicine_id' => $medicine->id, 'quantity' => 25]],
            'additional_medicine_id' => $medicine->id, 'additional_quantity' => null,
        ])->assertSessionHasErrors('additional_medicine_id');
        $this->assertDatabaseHas('doh_event_materials', ['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'quantity' => 25]);
    }

    public function test_failed_plan_save_restores_the_dialog_and_entered_quantities(): void
    {
        $unit = $this->unit();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $program = $this->program();
        $medicine = Medicine::create(['medicine_name' => 'Recovery material', 'category' => 'vaccine']);
        $this->withSession(['user_id' => $staff->id])->from($this->calendarUrl())
            ->post(route('vaccination.program_materials.store', $program), [
                'material_plan_program_id' => $program->id, 'event_date' => '2027-01-01',
                'items' => [['medicine_id' => $medicine->id, 'quantity' => 27]],
                'additional_quantity' => -1,
            ])->assertSessionHasErrors('additional_quantity');
        $this->get($this->calendarUrl())->assertOk()->assertSee('The material plan was not saved.')
            ->assertSee('Review the plan errors')->assertSee('name="items[0][quantity]" value="27"', false)
            ->assertSee('dialog.showModal()', false);
        $this->assertDatabaseCount('doh_event_materials', 0);
    }

    public function test_stock_reminders_appear_only_when_a_national_event_date_is_selected(): void
    {
        $unit = $this->unit();
        $staff = User::factory()->create(['health_unit_id' => $unit->id]);
        $program = $this->program();
        $medicine = Medicine::create(['medicine_name' => 'Selected event vaccine', 'category' => 'vaccine', 'unit' => 'vial']);
        DB::table('doh_event_materials')->insert(['health_unit_id' => $unit->id, 'doh_program_id' => $program->id,
            'event_date' => '2027-01-01', 'medicine_id' => $medicine->id, 'quantity' => 25]);
        CalendarEvent::create(['health_unit_id' => $unit->id, 'title' => 'Local immunization session',
            'event_date' => '2027-01-02', 'type' => 'immunization_day', 'priority' => 'normal', 'created_by' => $staff->id]);

        $this->withSession(['user_id' => $staff->id])->get($this->calendarUrl())->assertOk()
            ->assertSee('Selected event vaccine')->assertSee('Stock up at least 25 vial')
            ->assertDontSee('Upcoming DoH program preparation')->assertDontSee('<section id="program-preparation"', false)
            ->assertSee('id="selected-day"', false)->assertSee('id="program-preparation"', false);
        $this->get($this->calendarUrl('2027-01-02'))->assertOk()->assertSee('Local immunization session')
            ->assertDontSee('Stock up at least')->assertDontSee('Selected event vaccine')
            ->assertDontSee('Review and edit material plan')->assertDontSee('Upcoming DoH program preparation');
    }

    public function test_selected_event_plans_work_beyond_thirty_days_and_keep_program_materials_separate(): void
    {
        $unit = $this->unit();
        $staff = User::factory()->create(['health_unit_id' => $unit->id]);
        $immunization = $this->program(['month' => 3]);
        $aids = $this->program(['name' => 'World AIDS Day', 'month' => 3]);
        $otherDay = $this->program(['name' => 'Other event', 'month' => 3, 'day' => 2]);
        $this->program(['name' => 'Inactive event', 'month' => 3, 'is_active' => false]);
        $vaccine = Medicine::create(['medicine_name' => 'March vaccine', 'category' => 'vaccine', 'unit' => 'vial']);
        $condom = Medicine::create(['medicine_name' => 'Program condom', 'unit' => 'piece']);
        $unrelated = Medicine::create(['medicine_name' => 'Unrelated event material', 'unit' => 'box']);
        foreach ([[$immunization, $vaccine, 25, '2027-03-01'], [$aids, $condom, 40, '2027-03-01'],
            [$otherDay, $unrelated, 99, '2027-03-02']] as [$program, $medicine, $quantity, $date]) {
            DB::table('doh_event_materials')->insert(['health_unit_id' => $unit->id, 'doh_program_id' => $program->id,
                'event_date' => $date, 'medicine_id' => $medicine->id, 'quantity' => $quantity]);
        }

        $response = $this->withSession(['user_id' => $staff->id])->get($this->calendarUrl('2027-03-01'))->assertOk()
            ->assertViewHas('selectedProgramPlans', fn ($plans) => collect($plans)->keys()->sort()->values()->all()
                === [$immunization->id, $aids->id])
            ->assertSee('Stock up at least 25 vial')->assertSee('Stock up at least 40 piece');
        $text = $this->calendarTextOutsideDialogs($response->getContent());
        $this->assertStringNotContainsString('Unrelated event material', $text);
        $this->assertStringNotContainsString('Stock up at least 99 box', $text);
        $plans = app(DohEventPlanningService::class)->onDate($unit->id, Carbon::parse('2027-03-01'));
        $plans = collect($plans)->keyBy('program_id');
        $this->assertSame([$vaccine->id], array_column($plans[$immunization->id]['items'], 'medicine_id'));
        $this->assertSame([$condom->id], array_column($plans[$aids->id]['items'], 'medicine_id'));
    }

    public function test_distant_material_plans_are_read_only_outside_the_supported_save_window(): void
    {
        $unit = $this->unit();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->program();
        Medicine::create(['medicine_name' => 'Future vaccine', 'category' => 'vaccine']);

        $this->withSession(['user_id' => $staff->id])->get($this->calendarUrl('2028-01-01'))->assertOk()
            ->assertSee('Future vaccine')->assertSee('View stock details')
            ->assertDontSee('Review and edit material plan')->assertDontSee('Save plan')
            ->assertDontSee('Your planned quantity');
    }

    public function test_selected_multi_day_program_reuses_the_start_date_plan_across_the_year_boundary(): void
    {
        $unit = $this->unit();
        $staff = User::factory()->create(['health_unit_id' => $unit->id]);
        $program = $this->program(['month' => 12, 'day' => 28, 'duration_days' => 7]);
        $medicine = Medicine::create(['medicine_name' => 'Year end vaccine', 'category' => 'vaccine', 'unit' => 'vial']);
        DB::table('doh_event_materials')->insert(['health_unit_id' => $unit->id, 'doh_program_id' => $program->id,
            'event_date' => '2026-12-28', 'medicine_id' => $medicine->id, 'quantity' => 35]);

        $plan = app(DohEventPlanningService::class)->onDate($unit->id, Carbon::parse('2027-01-02'))[0];
        $this->assertSame('2026-12-28', $plan['date']);
        $this->assertSame('2027-01-03', $plan['end_date']);
        $this->assertSame(35, $plan['items'][0]['planned_quantity']);
        $this->withSession(['user_id' => $staff->id])->get($this->calendarUrl('2027-01-02'))->assertOk()
            ->assertSee('Stock up at least 35 vial')
            ->assertSee('id="program-plan-'.$program->id.'-2026-12-28"', false)
            ->assertSee('name="event_date" value="2026-12-28"', false);
        $this->assertSame([], app(DohEventPlanningService::class)->onDate($unit->id, Carbon::parse('2027-01-04')));
    }

    public function test_event_card_handles_unknown_quantities_and_keeps_excluded_materials_out_of_reminders(): void
    {
        $unit = $this->unit();
        $staff = User::factory()->create(['health_unit_id' => $unit->id]);
        $program = $this->program();
        $unknown = Medicine::create(['medicine_name' => 'Unplanned vaccine', 'category' => 'vaccine']);
        $excluded = Medicine::create(['medicine_name' => 'Excluded vaccine', 'category' => 'vaccine']);
        $covered = Medicine::create(['medicine_name' => 'Covered vaccine', 'category' => 'vaccine']);
        foreach ([[$excluded, 0], [$covered, 2]] as [$medicine, $quantity]) {
            DB::table('doh_event_materials')->insert(['health_unit_id' => $unit->id, 'doh_program_id' => $program->id,
                'event_date' => '2027-01-01', 'medicine_id' => $medicine->id, 'quantity' => $quantity]);
        }
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $covered->id, 'available_stock' => 5]);

        $response = $this->withSession(['user_id' => $staff->id])->get($this->calendarUrl())->assertOk();
        $text = $this->calendarTextOutsideDialogs($response->getContent());
        $this->assertStringContainsString($unknown->medicine_name, $text);
        $this->assertStringContainsString('Check stock before the event', $text);
        $this->assertStringContainsString('Confirm stock before the event', $text);
        $this->assertStringNotContainsString($excluded->medicine_name, $text);
        $this->assertStringNotContainsString('Stock up 0', $text);
        $this->assertStringNotContainsString('Stock is sufficient', $text);
    }

    public function test_reminders_have_separate_fourteen_and_seven_day_stages_and_durable_deduplication(): void
    {
        $unit = $this->unit();
        User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id, 'is_active' => false]);
        $this->program();
        $service = app(NotificationService::class);
        $this->assertSame(2, $service->scanDohPreparationReminders());
        $this->assertSame(0, $service->scanDohPreparationReminders());
        $this->assertDatabaseHas('notifications', ['type' => 'doh_preparation', 'user_id' => $midwife->id, 'health_unit_id' => $unit->id]);
        $this->assertStringContainsString('Set local quantities', Notification::where('type', 'doh_preparation')->first()->message);
        Notification::where('type', 'doh_preparation')->delete();
        $this->assertSame(0, $service->scanDohPreparationReminders());
        $this->travelTo(Carbon::parse('2026-12-25 10:00:00', 'Asia/Manila'));
        $this->assertSame(2, $service->scanDohPreparationReminders());
        $this->assertSame(0, $service->scanDohPreparationReminders());
        $this->assertDatabaseCount('doh_event_reminders', 4);
    }

    public function test_multi_day_program_shortage_accounts_for_batches_expiring_during_the_program(): void
    {
        $unit = $this->unit();
        $actor = User::factory()->create(['health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Nutrition material', 'category' => 'family_health_nutrition']);
        $this->program(['name' => 'Nutrition Month', 'duration_days' => 31]);
        $this->history($unit, $medicine, $actor, 7);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 100, 'expiration_date' => '2027-01-02']);

        $item = app(DohEventPlanningService::class)->upcoming($unit->id)[0]['items'][0];
        $this->assertSame(86, $item['projected_stock']);
        $this->assertSame(31, $item['target_quantity']);
        $this->assertSame(29, $item['stock_up']);
        $this->assertSame(86, $item['expires_during_program']);
    }

    public function test_manual_program_quantity_replaces_the_baseline_during_the_event(): void
    {
        $unit = $this->unit();
        $actor = User::factory()->create(['health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Program vaccine', 'category' => 'vaccine']);
        $program = $this->program(['duration_days' => 10]);
        $this->history($unit, $medicine, $actor);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 200]);
        DB::table('doh_event_materials')->insert(['health_unit_id' => $unit->id, 'doh_program_id' => $program->id,
            'event_date' => '2027-01-01', 'medicine_id' => $medicine->id, 'quantity' => 20]);

        $item = app(DohEventPlanningService::class)->upcoming($unit->id)[0]['items'][0];
        $this->assertSame(60, $item['projected_stock']);
        $this->assertSame(20, $item['target_quantity']);
        $this->assertSame(0, $item['routine_shortage_before_program']);
        $this->assertSame(0, $item['program_shortage']);
        $this->assertSame(0, $item['stock_up']);
    }

    public function test_excluded_materials_remain_editable_and_can_be_restored(): void
    {
        $unit = $this->unit();
        $actor = User::factory()->create(['health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Program vaccine', 'category' => 'vaccine']);
        $program = $this->program();
        $payload = ['event_date' => '2027-01-01', 'items' => [['medicine_id' => $medicine->id, 'quantity' => 0]]];
        $this->withSession(['user_id' => $actor->id])->post(route('vaccination.program_materials.store', $program), $payload)->assertRedirect();
        $plan = app(DohEventPlanningService::class)->upcoming($unit->id)[0];
        $this->assertSame([], $plan['items']);
        $this->assertSame(0, $plan['editable_items'][0]['planned_quantity']);
        $payload['items'][0]['quantity'] = 20;
        $this->post(route('vaccination.program_materials.store', $program), $payload)->assertRedirect();
        $this->assertSame(20, app(DohEventPlanningService::class)->upcoming($unit->id)[0]['items'][0]['planned_quantity']);
    }

    public function test_late_scan_catches_the_current_window_and_excludes_inactive_programs_and_non_rhus(): void
    {
        $unit = $this->unit();
        User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $pho = HealthUnit::create(['name' => 'PHO', 'facility_type' => 'Provincial Health Office']);
        User::factory()->create(['role' => 'admin', 'health_unit_id' => $pho->id]);
        $this->program();
        $this->program(['name' => 'Inactive program', 'is_active' => false]);
        $this->travelTo(Carbon::parse('2026-12-28 10:00:00', 'Asia/Manila'));

        $this->assertSame(1, app(NotificationService::class)->scanDohPreparationReminders());
        $notice = Notification::where('type', 'doh_preparation')->sole();
        $this->assertStringStartsWith('7-day preparation', $notice->title);
        $this->assertSame($unit->id, $notice->health_unit_id);
        $this->assertStringContainsString('day=2027-01-01', $notice->link);
        $this->assertStringEndsWith('#selected-day', $notice->link);
    }
}
