<?php

namespace Tests\Feature;

use App\Helpers\ActivityLogger;
use App\Models\Allocation;
use App\Models\Disease;
use App\Models\DiseaseReport;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\PublicUser;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Services\ForecastService;
use App\Services\NotificationService;
use App\Services\TransferService;
use App\Services\VaccinationScheduleService;
use Database\Seeders\ImmunizationScheduleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $name = 'RHU'): HealthUnit
    {
        return HealthUnit::create(['name' => $name, 'facility_type' => 'Rural Health Unit']);
    }

    private function signIn(?HealthUnit $unit, string $role = 'admin'): User
    {
        $user = User::factory()->create(['health_unit_id' => $unit?->id, 'role' => $role]);
        $this->withSession(['user_id' => $user->id, 'role' => $role, 'health_unit_id' => $unit?->id, 'user_name' => $user->name]);

        return $user;
    }

    private function medicine(): Medicine
    {
        return Medicine::create(['medicine_name' => 'Fixture medicine', 'category' => 'medicine', 'unit' => 'tablet', 'requires_prescription' => false]);
    }

    private function batch(HealthUnit $unit, Medicine $medicine, string $batch, int $quantity, int $days = 30): Inventory
    {
        return Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'batch_no' => $batch,
            'available_stock' => $quantity, 'allocated_stock' => 0, 'expiration_date' => today()->addDays($days)]);
    }

    private function dispense(Inventory $batch, int $quantity = 15, array $extra = [])
    {
        return $this->post(route('dispensing.store'), array_merge(['inventory_id' => $batch->id, 'patient_name' => 'Fixture patient',
            'quantity_dispensed' => $quantity, 'date_dispensed' => today()->toDateString()], $extra));
    }

    public function test_deleting_pending_or_rejected_requests_never_creates_stock(): void
    {
        $unit = $this->unit();
        $user = $this->signIn($unit);
        $medicine = $this->medicine();
        $batch = $this->batch($unit, $medicine, 'A', 100);
        foreach (['pending', 'rejected'] as $status) {
            $request = Allocation::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'quantity_allocated' => 25, 'date_allocated' => today(), 'allocated_by' => $user->id, 'status' => $status]);
            $this->delete(route('allocation.destroy', $request->id))->assertSessionMissing('error');
            $this->assertSame(100, $batch->fresh()->available_stock);
        }
    }

    public function test_rhu_exports_cannot_select_another_facility(): void
    {
        $unit = $this->unit();
        $other = $this->unit('Other');
        $this->signIn($unit, 'staff');
        foreach (['elmis.export.inventory', 'elmis.export.movement'] as $route) {
            $this->get(route($route, ['health_unit_id' => $other->id]))->assertForbidden();
        }
    }

    public function test_deactivation_revokes_an_existing_session(): void
    {
        $user = $this->signIn($this->unit());
        $user->update(['is_active' => false]);
        $this->get(route('inventory.index'))->assertRedirect(route('login'))->assertSessionMissing('user_id');
    }

    public function test_changed_roles_are_used_instead_of_stale_session_permissions(): void
    {
        $user = $this->signIn($this->unit());
        $user->update(['role' => 'midwife']);
        $this->get(route('inventory.index'))->assertRedirect();
        $this->assertSame('midwife', session('role'));
    }

    public function test_midwives_cannot_read_or_change_unassigned_patients(): void
    {
        $unit = $this->unit();
        $user = $this->signIn($unit, 'midwife');
        $patient = PublicUser::create(['health_unit_id' => $unit->id, 'first_name' => 'Other', 'last_name' => 'Patient', 'address' => 'Fixture address', 'birth_date' => today()->subYear()]);
        $this->get(route('patients.show', $patient->id))->assertNotFound();
        $this->post(route('prenatal.store'), ['patient_id' => $patient->id, 'visit_date' => today()->toDateString()])->assertNotFound();
        $patient->update(['assigned_midwife_id' => $user->id]);
        $this->post(route('prenatal.store'), ['patient_id' => $patient->id, 'visit_date' => today()->toDateString()])->assertSessionHas('success');
    }

    public function test_login_accepts_existing_usernames_with_digits_and_migrates_session(): void
    {
        User::factory()->create(['username' => 'nurse1', 'password' => Hash::make('existing-password')]);
        $this->withSession(['anonymous' => true]);
        $oldId = session()->getId();
        $this->post(route('login.submit'), ['username' => 'nurse1', 'password' => 'existing-password'])->assertRedirect(route('dashboard'));
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotNull(session('user_id'));
    }

    public function test_settings_reject_passwords_that_do_not_meet_account_rules(): void
    {
        $this->signIn($this->unit());
        $this->post(route('settings'), ['name' => 'Fixture', 'password' => 'abcdef', 'password_confirmation' => 'abcdef'])->assertSessionHasErrors('password');
    }

    public function test_dispense_delete_restores_each_original_batch_and_preserves_history(): void
    {
        $unit = $this->unit();
        $this->signIn($unit);
        $medicine = $this->medicine();
        $a = $this->batch($unit, $medicine, 'A', 10, 30);
        $b = $this->batch($unit, $medicine, 'B', 10, 60);
        $this->dispense($a)->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->assertSame([0, 5], [$a->fresh()->available_stock, $b->fresh()->available_stock]);
        $this->delete(route('dispensing.destroy', $record->id))->assertSessionHas('success');
        $this->assertSame([10, 10], [$a->fresh()->available_stock, $b->fresh()->available_stock]);
        $this->assertSame(2, StockLog::where('dispensing_record_id', $record->id)->whereNotNull('voided_at')->count());
    }

    public function test_dispense_update_reverses_old_batches_before_new_fefo_issue(): void
    {
        $unit = $this->unit();
        $this->signIn($unit);
        $medicine = $this->medicine();
        $a = $this->batch($unit, $medicine, 'A', 10);
        $b = $this->batch($unit, $medicine, 'B', 10, 60);
        $this->dispense($a)->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->put(route('dispensing.update', $record->id), ['inventory_id' => $a->id, 'patient_name' => 'Fixture',
            'quantity_dispensed' => 5, 'date_dispensed' => today()->toDateString()])->assertSessionHas('success');
        $this->assertSame([5, 10], [$a->fresh()->available_stock, $b->fresh()->available_stock]);
        $this->delete(route('dispensing.destroy', $record->id))->assertSessionHas('success');
        $this->assertSame([10, 10], [$a->fresh()->available_stock, $b->fresh()->available_stock]);
    }

    public function test_allocation_delete_restores_all_allocated_batches(): void
    {
        $unit = $this->unit();
        $this->signIn($unit);
        $medicine = $this->medicine();
        $a = $this->batch($unit, $medicine, 'A', 10);
        $b = $this->batch($unit, $medicine, 'B', 10, 60);
        $this->post(route('allocation.store'), ['inventory_id' => $a->id, 'destination' => 'Fixture station',
            'quantity_allocated' => 15, 'date_allocated' => today()->toDateString()])->assertSessionHas('success');
        $allocation = Allocation::firstOrFail();
        $this->delete(route('allocation.destroy', $allocation->id))->assertSessionHas('success');
        $this->assertSame([10, 10, 0, 0], [$a->fresh()->available_stock, $b->fresh()->available_stock, $a->fresh()->allocated_stock, $b->fresh()->allocated_stock]);
    }

    public function test_ambiguous_legacy_reversal_fails_without_changing_stock(): void
    {
        $unit = $this->unit();
        $user = $this->signIn($unit);
        $medicine = $this->medicine();
        $a = $this->batch($unit, $medicine, 'SAME', 10);
        $this->batch($unit, $medicine, 'SAME', 10, 60);
        $record = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'patient_name' => 'Fixture', 'quantity_dispensed' => 5, 'date_dispensed' => today(), 'dispensed_by' => $user->id]);
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'quantity_added' => 5, 'date_added' => today(), 'batch_no' => 'SAME', 'dispensing_record_id' => $record->id]);
        $this->delete(route('dispensing.destroy', $record->id))->assertSessionHas('error');
        $this->assertSame(10, $a->fresh()->available_stock);
        $this->assertNotNull($record->fresh());
    }

    public function test_prescription_download_and_failed_update_preserve_the_attachment(): void
    {
        Storage::fake('local');
        $unit = $this->unit();
        $this->signIn($unit);
        $medicine = $this->medicine();
        $batch = $this->batch($unit, $medicine, 'A', 10);
        $this->dispense($batch, 5, ['prescription_image' => UploadedFile::fake()->create('rx.pdf', 1, 'application/pdf')])->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $path = $record->prescription_image;
        $this->get(route('dispensing.prescription', $record->id))->assertOk();
        $this->put(route('dispensing.update', $record->id), ['inventory_id' => $batch->id, 'patient_name' => 'Fixture',
            'quantity_dispensed' => 99, 'date_dispensed' => today()->toDateString(), 'remove_prescription' => true])->assertSessionHas('error');
        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $record->fresh()->prescription_image);
        $this->assertSame(5, $batch->fresh()->available_stock);
    }

    private function transfer(HealthUnit $from, HealthUnit $to, Medicine $medicine, int $quantity = 7): TransferRequest
    {
        return TransferRequest::create(['initiator_type' => 'rhu_request', 'from_health_unit_id' => $from->id,
            'to_health_unit_id' => $to->id, 'medicine_id' => $medicine->id, 'quantity' => $quantity, 'requested_by' => session('user_id'), 'status' => 'pending']);
    }

    public function test_reservation_prevents_overpromising_and_cancellation_releases_it(): void
    {
        $from = $this->unit();
        $to = $this->unit('Destination');
        $this->signIn($from);
        $medicine = $this->medicine();
        $batch = $this->batch($from, $medicine, 'A', 10);
        $transfer = $this->transfer($from, $to, $medicine);
        $this->post(route('transfers.accept', $transfer->id))->assertSessionHas('success');
        $this->assertSame([3, 7], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $another = $this->transfer($from, $to, $medicine);
        $this->post(route('transfers.accept', $another->id))->assertSessionHasErrors('stock');
        $this->signIn($to);
        $this->post(route('transfers.cancel', $transfer->id))->assertSessionHas('success');
        $this->assertSame([10, 0], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
    }

    public function test_manual_and_automatic_receipt_apply_only_once_and_copy_packaging(): void
    {
        $from = $this->unit();
        $to = $this->unit('Destination');
        $this->signIn($from);
        $medicine = $this->medicine();
        $batch = $this->batch($from, $medicine, 'A', 10);
        $batch->update(['pack_type' => 'Box', 'pieces_per_pack' => 10]);
        $transfer = $this->transfer($from, $to, $medicine);
        app(TransferService::class)->reserve($transfer->id);
        $this->signIn($to);
        $this->post(route('transfers.receive', $transfer->id))->assertSessionHas('success');
        app(TransferService::class)->complete($transfer->id, null, null, true);
        $destination = Inventory::where('health_unit_id', $to->id)->firstOrFail();
        $this->assertSame([3, 0, 7, 'Box', 10], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock, $destination->available_stock, $destination->pack_type, $destination->pieces_per_pack]);
        $this->assertSame(2, StockLog::where('transfer_id', $transfer->id)->count());
    }

    public function test_transfer_never_reserves_an_expired_batch(): void
    {
        $from = $this->unit();
        $to = $this->unit('Destination');
        $this->signIn($from);
        $medicine = $this->medicine();
        $batch = $this->batch($from, $medicine, 'EXPIRED', 10, -1);
        $transfer = $this->transfer($from, $to, $medicine);
        $this->post(route('transfers.accept', $transfer->id))->assertSessionHasErrors('stock');
        $this->assertSame(10, $batch->fresh()->available_stock);
    }

    public function test_stock_entry_keeps_distinct_batches_with_identical_expiry(): void
    {
        $unit = $this->unit();
        $this->signIn($unit);
        $medicine = $this->medicine();
        $a = $this->batch($unit, $medicine, 'A', 10);
        $this->post(route('stock_logs.store'), ['medicine_id' => $medicine->id, 'quantity_added' => 5, 'date_added' => today()->toDateString(),
            'batch_no' => 'B', 'expiration_date' => $a->expiration_date->toDateString()])->assertSessionHas('success');
        $this->assertSame('A', $a->fresh()->batch_no);
        $this->assertSame(10, $a->fresh()->available_stock);
        $this->assertDatabaseHas('inventory', ['batch_no' => 'B', 'available_stock' => 5]);
    }

    public function test_forecasts_aggregate_batches_include_stockouts_and_use_real_disease_columns(): void
    {
        $unit = $this->unit();
        $medicine = $this->medicine();
        $a = $this->batch($unit, $medicine, 'A', 60);
        $b = $this->batch($unit, $medicine, 'B', 60, 60);
        $this->batch($unit, $medicine, 'EXPIRED', 500, -1);
        StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'quantity_added' => 100, 'date_added' => today(), 'remarks' => 'Released — Dispensed']);
        $service = app(ForecastService::class);
        $forecast = $service->getForecast($unit->id);
        $this->assertCount(1, $forecast['items']);
        $this->assertSame(120, $forecast['items'][0]['available_stock']);
        $this->assertEquals(0, $forecast['items'][0]['shortage']);
        $a->update(['available_stock' => 0]);
        $b->update(['available_stock' => 0]);
        $this->assertSame('critical', $service->getForecast($unit->id)['items'][0]['status']);
        $disease = Disease::create(['code' => 'FIX', 'name' => 'Fixture disease']);
        DiseaseReport::create(['disease_id' => $disease->id, 'health_unit_id' => $unit->id, 'case_count' => 12, 'report_date' => today()->startOfWeek()->subDay()->toDateString(), 'period_type' => 'weekly', 'period_start' => today()->startOfWeek()->subWeek()->toDateString(), 'period_end' => today()->startOfWeek()->subDay()->toDateString(), 'is_complete' => true]);
        $this->assertEquals(12, $service->getUnplannedForecast($unit->id)['items'][0]->total_cases);
        $this->assertSame('Fixture disease', $service->getTrendingDiseases($unit->id)['datasets'][0]['label']);
    }

    public function test_every_controller_route_and_literal_view_exists_and_schedules_are_registered(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_contains($route->getActionName(), '@')) {
                [$class, $method] = explode('@', $route->getActionName());
                $this->assertTrue(method_exists($class, $method), $route->uri().' has a missing action');
            }
        }
        $this->assertTrue(view()->exists('public.landing'));
        $this->assertTrue(view()->exists('redistribution.index'));
        $this->assertCount(4, app(Schedule::class)->events());
        $this->get(route('public.home'))->assertOk();
    }

    public function test_provincial_reversal_restores_both_sides_and_rejects_used_destination_stock(): void
    {
        $pho = $this->unit('Provincial Health Office');
        $pho->update(['facility_type' => 'Provincial Health Office']);
        $destination = $this->unit('Destination');
        $this->signIn(null);
        $medicine = $this->medicine();
        $a = $this->batch($pho, $medicine, 'A', 10);
        $b = $this->batch($pho, $medicine, 'B', 10, 60);
        $this->post(route('allocation.province.store'), ['medicine_id' => $medicine->id, 'destination_health_unit_id' => $destination->id,
            'quantity_allocated' => 15, 'date_allocated' => today()->toDateString()])->assertSessionHas('success');
        $allocation = Allocation::firstOrFail();
        $destinationB = Inventory::where('health_unit_id', $destination->id)->where('batch_no', 'B')->firstOrFail();
        $destinationB->update(['available_stock' => 1]);
        $this->delete(route('allocation.destroy', $allocation->id))->assertSessionHas('error');
        $this->assertSame([0, 5], [$a->fresh()->available_stock, $b->fresh()->available_stock]);
        $destinationB->update(['available_stock' => 5]);
        $this->delete(route('allocation.destroy', $allocation->id))->assertSessionHas('success');
        $this->assertSame([10, 10], [$a->fresh()->available_stock, $b->fresh()->available_stock]);
        $this->assertEquals(0, Inventory::where('health_unit_id', $destination->id)->sum('available_stock'));
    }

    public function test_supply_request_can_be_approved_only_once(): void
    {
        $unit = $this->unit();
        $user = $this->signIn($unit);
        $medicine = $this->medicine();
        $batch = $this->batch($unit, $medicine, 'A', 20);
        $allocation = Allocation::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'quantity_allocated' => 7,
            'date_allocated' => today(), 'allocated_by' => $user->id, 'status' => 'pending']);
        $this->post(route('allocation.approve_supply_request', $allocation->id))->assertSessionHas('success');
        $this->post(route('allocation.approve_supply_request', $allocation->id))->assertNotFound();
        $this->assertSame([13, 7], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
    }

    public function test_stock_lookup_ignores_expired_and_deleted_batches(): void
    {
        $unit = $this->unit();
        $other = $this->unit('Other');
        $this->signIn($unit);
        $medicine = $this->medicine();
        $this->batch($other, $medicine, 'GOOD', 10);
        $this->batch($other, $medicine, 'EXPIRED', 100, -1);
        $deleted = $this->batch($other, $medicine, 'DELETED', 100);
        $deleted->delete();
        $this->getJson(route('stock_lookup.search', ['medicine_id' => $medicine->id]))->assertOk()->assertJsonPath('results.0.available_stock', 10);
    }

    public function test_critical_notifications_are_not_suppressed_and_pho_audit_is_recorded(): void
    {
        $unit = $this->unit();
        $this->signIn(null);
        $medicine = $this->medicine();
        $batch = $this->batch($unit, $medicine, 'A', 8, 200);
        $service = app(NotificationService::class);
        $service->scanAndGenerate();
        $batch->update(['available_stock' => 2]);
        $service->scanAndGenerate();
        $this->assertDatabaseHas('notifications', ['inventory_id' => $batch->id, 'severity' => 'danger']);
        ActivityLogger::log('Fixture', 'Audit', 'PHO fixture event');
        $this->assertDatabaseHas('activity_logs', ['health_unit_id' => null, 'module' => 'Fixture']);
    }

    public function test_core_pages_render_on_a_clean_database(): void
    {
        $unit = $this->unit();
        $this->signIn($unit);
        foreach (['inventory.index', 'medicine.index', 'dispensing.index', 'allocation.index', 'forecast.index',
            'public_users.index', 'prenatal.index', 'postpartum.index', 'family_planning.index', 'immunization.tcl',
            'vaccination.calendar', 'vaccination.forecast'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->signIn(null);
        $this->get(route('redistribution.index'))->assertOk();
        $this->get(route('inventory.pho_receive.export'))->assertOk();
    }

    public function test_dashboards_and_refresh_render_with_current_data(): void
    {
        $unit = $this->unit();
        $this->signIn($unit);
        $this->get(route('dashboard'))->assertOk();
        $this->getJson(route('dashboard.refresh'))->assertOk()->assertJsonStructure(['stats', 'charts']);
        $this->signIn($unit, 'midwife');
        $this->get(route('dashboard.midwife'))->assertOk();
    }

    public function test_recording_a_configured_dose_syncs_the_calendar_and_rejects_unknown_doses(): void
    {
        $this->seed(ImmunizationScheduleSeeder::class);
        $unit = $this->unit();
        $this->signIn($unit);
        $patient = PublicUser::create(['health_unit_id' => $unit->id, 'first_name' => 'Fixture', 'last_name' => 'Child',
            'address' => 'Fixture address', 'birth_date' => today()->subMonth()]);
        $schedule = VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $patient->id,
            'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => $patient->birth_date, 'status' => 'pending']);
        $this->post(route('immunization.store'), ['public_user_id' => $patient->id, 'vaccine' => 'bcg', 'dose_number' => 1,
            'date_given' => today()->toDateString()])->assertSessionHas('success');
        $this->assertSame('given', $schedule->fresh()->status);
        $this->post(route('immunization.store'), ['public_user_id' => $patient->id, 'vaccine' => 'not-a-vaccine', 'dose_number' => 99,
            'date_given' => today()->toDateString()])->assertSessionHasErrors('vaccine');
    }

    public function test_monthly_vaccination_exports_include_only_a_midwifes_assigned_patients(): void
    {
        $unit = $this->unit();
        $user = $this->signIn($unit, 'midwife');
        foreach ([['Assigned', $user->id], ['Unassigned', null]] as [$name, $midwifeId]) {
            $patient = PublicUser::create(['health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwifeId,
                'first_name' => $name, 'last_name' => 'Fixture', 'address' => 'Fixture address', 'birth_date' => today()->subMonth()]);
            VaccinationSchedule::create(['health_unit_id' => $unit->id, 'public_user_id' => $patient->id,
                'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => today(), 'status' => 'pending']);
        }
        $summary = app(VaccinationScheduleService::class)->monthlySummary($unit->id, today()->format('Y-m'));
        $this->assertSame(1, $summary['total_children']);
        $this->assertSame('Assigned', $summary['children']->first()->patient->first_name);
    }
}
