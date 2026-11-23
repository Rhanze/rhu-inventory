<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\ImmunizationRecord;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\StockLog;
use App\Models\User;
use App\Models\VaccinationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EmptyHistoryResultWiringTest extends TestCase
{
    use RefreshDatabase;

    private function dom(TestResponse $response): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());

        return new \DOMXPath($document);
    }

    private function assertHistoryCount(TestResponse $response, int $count): void
    {
        $response->assertOk();
        $markers = $this->dom($response)->query('//*[@data-empty-result="history"]');
        $this->assertSame(1, $markers->length);
        $this->assertSame((string) $count, $markers->item(0)->getAttribute('data-empty-count'));
    }

    private function transactions(HealthUnit $unit, User $user, Medicine $medicine, string $date): void
    {
        DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensed_by' => $user->id, 'date_dispensed' => $date, 'quantity_dispensed' => 2]);
        Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'allocated_by' => $user->id, 'date_allocated' => $date, 'quantity_allocated' => 3,
            'status' => 'approved', 'destination' => 'History outreach']);
        StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'added_by' => $user->id, 'date_added' => $date, 'quantity_added' => 4, 'movement_type' => 'addition']);
    }

    public function test_history_results_count_only_matching_dates_and_the_viewers_rhu(): void
    {
        $unit = HealthUnit::create(['name' => 'Empty feedback RHU']);
        $other = HealthUnit::create(['name' => 'Other feedback RHU']);
        $staff = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'History feedback medicine', 'category' => 'medicine']);
        $this->withSession(['user_id' => $staff->id]);
        $this->transactions($other, $staff, $medicine, '2024-06-01');
        $routes = ['allocation.index', 'dispensing.index', 'stock_logs.index',
            'reports.allocation', 'reports.dispensing', 'reports.stock'];
        foreach ($routes as $route) {
            $this->assertHistoryCount($this->get(route($route, ['year' => 2024, 'rhu_filter' => $other->id])), 0);
        }
        $this->transactions($unit, $staff, $medicine, '2024-06-01');
        foreach ($routes as $route) {
            $this->assertHistoryCount($this->get(route($route, ['year' => 2024])), 1);
            $this->assertHistoryCount($this->get(route($route, ['year' => 2025])), 0);
        }
    }

    public function test_empty_pagination_pages_do_not_claim_that_matching_history_is_missing(): void
    {
        $unit = HealthUnit::create(['name' => 'Paged feedback RHU']);
        $staff = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'Paged history medicine', 'category' => 'medicine']);
        $this->transactions($unit, $staff, $medicine, '2024-06-01');
        $this->withSession(['user_id' => $staff->id]);
        foreach (['allocation.index' => 'allocations', 'dispensing.index' => 'records', 'stock_logs.index' => 'stockLogs'] as $route => $variable) {
            $response = $this->get(route($route, ['year' => 2024, 'page' => 99]))
                ->assertViewHas($variable, fn ($rows) => $rows->isEmpty() && $rows->total() === 1);
            $this->assertHistoryCount($response, 1);
        }
    }

    public function test_immunization_history_uses_given_doses_for_the_selected_child(): void
    {
        $unit = HealthUnit::create(['name' => 'Child feedback RHU']);
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $patient = ['health_unit_id' => $unit->id, 'first_name' => 'History', 'last_name' => 'Child',
            'birth_date' => today()->subMonths(2)->toDateString(), 'address' => 'Feedback address'];
        $child = PublicUser::create($patient + ['assigned_midwife_id' => $midwife->id]);
        $otherChild = PublicUser::create($patient + ['assigned_midwife_id' => $colleague->id]);
        ImmunizationRecord::create(['public_user_id' => $otherChild->id, 'health_unit_id' => $unit->id,
            'antigen' => 'BCG', 'dose_number' => 1, 'date_given' => today()]);
        VaccinationSchedule::create(['public_user_id' => $child->id, 'health_unit_id' => $unit->id,
            'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => today()->addWeek(), 'status' => 'pending']);
        $this->withSession(['user_id' => $midwife->id]);
        $this->assertHistoryCount($this->get(route('immunization.history', $child->id)), 0);
        $dom = $this->dom($this->get(route('immunization.tcl'))->assertOk());
        $historyLinks = $dom->query('//a[@href="'.route('immunization.history', $child->id).'"][@data-empty-action="history"]');
        $this->assertSame(1, $historyLinks->length);
        ImmunizationRecord::create(['public_user_id' => $child->id, 'health_unit_id' => $unit->id,
            'antigen' => 'BCG', 'dose_number' => 1, 'date_given' => today()]);
        $this->assertHistoryCount($this->get(route('immunization.history', $child->id)), 1);
    }

    public function test_midwife_view_all_opens_assigned_barangay_history_and_uses_own_supply_request_count(): void
    {
        $unit = HealthUnit::create(['name' => 'Dashboard feedback RHU']);
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'Dashboard history medicine', 'category' => 'medicine']);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Feedback area']);
        DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensed_by' => $colleague->id, 'barangay' => 'Feedback area', 'date_dispensed' => today(), 'quantity_dispensed' => 2]);
        $request = ['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'date_allocated' => today(), 'quantity_allocated' => 1, 'status' => 'pending', 'destination' => 'Feedback clinic'];
        Allocation::create($request + ['allocated_by' => $colleague->id]);
        $this->withSession(['user_id' => $midwife->id]);
        $response = $this->get(route('dashboard.midwife'))->assertOk()
            ->assertViewHas('recentDispensing', fn ($records) => $records->isEmpty());
        $dom = $this->dom($response);
        $dispensingLink = $dom->query('//a[@href="'.route('dispensing.index').'"][@data-empty-action="history"]')->item(0);
        $this->assertNotNull($dispensingLink);
        $this->assertFalse($dispensingLink->hasAttribute('data-empty-guard'));
        $this->assertHistoryCount($this->get(route('dispensing.index')), 1);
        $requestLink = $dom->query('//a[@aria-label="View all supply requests"]')->item(0);
        $this->assertSame('true', $requestLink->getAttribute('data-empty-guard'));
        $this->assertSame('0', $requestLink->getAttribute('data-empty-count'));
        $this->assertSame('No supply requests yet.', $requestLink->getAttribute('data-empty-message'));
        Allocation::create($request + ['allocated_by' => $midwife->id]);
        $dom = $this->dom($this->get(route('dashboard.midwife'))->assertOk());
        $this->assertSame('1', $dom->evaluate('string(//a[@aria-label="View all supply requests"]/@data-empty-count)'));
    }
}
