<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\FamilyPlanning;
use App\Models\HealthUnit;
use App\Models\ImmunizationRecord;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\PostpartumVisit;
use App\Models\PrenatalVisit;
use App\Models\PublicUser;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Services\VaccinationScheduleService;
use Carbon\Carbon;
use Database\Seeders\ImmunizationScheduleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ClinicalWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 14:00:00', 'Asia/Manila'));
    }

    private function signIn(HealthUnit $unit, string $role = 'staff'): User
    {
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => $role]);
        $this->withSession(['user_id' => $user->id]);

        return $user;
    }

    private function patient(HealthUnit $unit, array $extra = []): PublicUser
    {
        return PublicUser::create(array_merge(['health_unit_id' => $unit->id, 'first_name' => 'Audit', 'last_name' => 'Patient',
            'barangay' => 'Shared Village', 'birth_date' => '2000-01-01', 'address' => 'Audit address'], $extra));
    }

    public function test_automatic_patient_assignment_uses_only_active_local_midwives(): void
    {
        $unit = HealthUnit::create(['name' => 'Local RHU']);
        $other = HealthUnit::create(['name' => 'Other RHU']);
        $this->signIn($unit);
        foreach ([[$other, 'midwife', true], [$unit, 'midwife', false], [$unit, 'staff', true]] as [$facility, $role, $active]) {
            $wrong = User::factory()->create(['health_unit_id' => $facility->id, 'role' => $role, 'is_active' => $active]);
            MidwifeBarangay::create(['user_id' => $wrong->id, 'barangay' => 'Shared Village', 'is_primary' => true]);
        }
        $local = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        MidwifeBarangay::create(['user_id' => $local->id, 'barangay' => 'Shared Village']);
        $this->post(route('public_users.store'), ['first_name' => 'Assigned', 'last_name' => 'Locally', 'birth_date' => '2000-01-01',
            'barangay' => 'Shared Village', 'address' => 'Local address'])->assertSessionHas('success');
        $this->assertSame($local->id, PublicUser::firstOrFail()->assigned_midwife_id);
    }

    public function test_patient_history_excludes_reversed_and_colleague_dispensing_and_pho_cannot_read_patients(): void
    {
        $unit = HealthUnit::create(['name' => 'Private RHU']);
        $midwife = $this->signIn($unit, 'midwife');
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $patient = $this->patient($unit, ['assigned_midwife_id' => $midwife->id]);
        $medicine = Medicine::create(['medicine_name' => 'Audit medicine']);
        foreach ([$midwife->id, $colleague->id, $midwife->id] as $index => $userId) {
            $record = DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'dispensed_by' => $userId, 'public_user_id' => $patient->id, 'patient_name' => $patient->full_name,
                'date_dispensed' => '2026-11-01', 'quantity_dispensed' => 10]);
            if ($index === 2) {
                $record->delete();
            }
        }
        $this->get(route('patients.show', $patient->id))->assertOk()
            ->assertViewHas('dispensingRecords', fn ($records) => $records->count() === 1 && $records->first()->dispensed_by === $midwife->id);
        $pho = User::factory()->create(['health_unit_id' => null, 'role' => 'admin']);
        $this->withSession(['user_id' => $pho->id])->get(route('patients.show', $patient->id))->assertForbidden();
    }

    public function test_patient_deletion_preserves_each_kind_of_clinical_history(): void
    {
        $unit = HealthUnit::create(['name' => 'Clinical history RHU']);
        $this->signIn($unit);
        foreach (['prenatal', 'postpartum', 'planning', 'given', 'missed'] as $type) {
            $patient = $this->patient($unit, ['first_name' => $type]);
            $base = ['patient_id' => $patient->id, 'health_unit_id' => $unit->id, 'visit_date' => '2026-11-01'];
            match ($type) {
                'prenatal' => PrenatalVisit::create($base),
                'postpartum' => PostpartumVisit::create($base + ['delivery_date' => '2026-10-30', 'visit_number' => 1]),
                'planning' => FamilyPlanning::create($base + ['date_accepted' => '2026-11-01', 'method' => 'Pills']),
                default => VaccinationSchedule::create(['public_user_id' => $patient->id, 'health_unit_id' => $unit->id,
                    'vaccine_name' => 'BCG', 'dose_number' => 1, 'due_date' => '2026-11-01', 'status' => $type]),
            };
            $this->delete(route('public_users.destroy', $patient->id))->assertSessionHas('error');
            $this->assertNotNull($patient->fresh());
        }
    }

    public function test_date_of_birth_correction_supersedes_reminders_without_erasing_history(): void
    {
        $unit = HealthUnit::create(['name' => 'Birth correction RHU']);
        $this->signIn($unit);
        $patient = $this->patient($unit, ['birth_date' => '2025-11-01']);
        foreach (['pending', 'missed', 'given'] as $index => $status) {
            VaccinationSchedule::create(['public_user_id' => $patient->id, 'health_unit_id' => $unit->id,
                'vaccine_name' => 'BCG', 'dose_number' => $index + 1, 'due_date' => '2025-11-01', 'status' => $status]);
        }
        $this->put(route('public_users.update', $patient->id), ['first_name' => 'Audit', 'last_name' => 'Patient',
            'birth_date' => '2000-01-01', 'barangay' => 'Shared Village', 'address' => 'Audit address'])->assertSessionHas('success');
        $this->assertSame(['pending', 'missed', 'given'], $patient->vaccinationSchedules()->orderBy('id')->pluck('status')->all());
        $this->assertSame(['given'], $patient->vaccinationSchedules()->current()->pluck('status')->all());
    }

    public function test_birth_date_cannot_be_changed_to_after_a_recorded_vaccination(): void
    {
        $unit = HealthUnit::create(['name' => 'Recorded vaccination RHU']);
        $this->signIn($unit);
        $patient = $this->patient($unit, ['birth_date' => '2026-09-01']);
        ImmunizationRecord::create(['public_user_id' => $patient->id, 'health_unit_id' => $unit->id,
            'antigen' => 'bcg', 'dose_number' => 1, 'date_given' => '2026-10-01']);

        $this->putJson(route('public_users.update', $patient->id), ['first_name' => 'Audit', 'last_name' => 'Patient',
            'birth_date' => '2026-10-02', 'barangay' => 'Shared Village', 'address' => 'Local address'])
            ->assertUnprocessable()->assertJsonValidationErrors('birth_date');
        $this->assertSame('2026-09-01', $patient->fresh()->birth_date->toDateString());
    }

    public function test_registration_rolls_back_when_vaccination_scheduling_fails(): void
    {
        $unit = HealthUnit::create(['name' => 'Registration recovery RHU']);
        $this->signIn($unit);
        $this->mock(VaccinationScheduleService::class)->shouldReceive('generateFor')->once()
            ->andThrow(new RuntimeException('Synthetic schedule failure.'));

        $this->post(route('public_users.store'), ['first_name' => 'New', 'last_name' => 'Patient', 'birth_date' => '2026-01-01',
            'barangay' => 'Shared Village', 'address' => 'Local address'])->assertServerError();
        $this->assertDatabaseCount('public_users', 0);
    }

    public function test_birth_date_correction_rolls_back_when_vaccination_scheduling_fails(): void
    {
        $unit = HealthUnit::create(['name' => 'Birth date recovery RHU']);
        $this->signIn($unit);
        $patient = $this->patient($unit, ['birth_date' => '2026-09-01']);
        $this->mock(VaccinationScheduleService::class)->shouldReceive('regenerateForCorrectedBirthDate')->once()
            ->andThrow(new RuntimeException('Synthetic schedule failure.'));

        $this->put(route('public_users.update', $patient->id), ['first_name' => 'Audit', 'last_name' => 'Patient',
            'birth_date' => '2026-09-02', 'barangay' => 'Shared Village', 'address' => 'Local address'])->assertServerError();
        $this->assertSame('2026-09-01', $patient->fresh()->birth_date->toDateString());
    }

    public function test_clinical_visits_cannot_be_recorded_before_a_known_birth_date(): void
    {
        $unit = HealthUnit::create(['name' => 'Visit chronology RHU']);
        $this->signIn($unit);
        $patient = $this->patient($unit, ['birth_date' => '2025-01-01']);

        foreach ([
            'prenatal.store' => [['visit_date' => '2024-12-31'], 'visit_date'],
            'postpartum.store' => [['delivery_date' => '2024-12-31', 'visit_date' => '2025-01-01'], 'delivery_date'],
            'family_planning.store' => [['date_accepted' => '2024-12-31', 'method' => 'Pills'], 'date_accepted'],
        ] as $route => [$fields, $dateField]) {
            $this->postJson(route($route), $fields + ['patient_id' => $patient->id])
                ->assertUnprocessable()->assertJsonValidationErrors($dateField);
        }
    }

    public function test_clinical_monthly_totals_include_first_day_and_supplies_due_today(): void
    {
        $unit = HealthUnit::create(['name' => 'Calendar RHU']);
        $this->signIn($unit);
        $patient = $this->patient($unit);
        PrenatalVisit::create(['patient_id' => $patient->id, 'health_unit_id' => $unit->id, 'visit_date' => '2026-11-01']);
        PostpartumVisit::create(['patient_id' => $patient->id, 'health_unit_id' => $unit->id, 'visit_date' => '2026-11-01', 'delivery_date' => '2026-10-30']);
        FamilyPlanning::create(['patient_id' => $patient->id, 'health_unit_id' => $unit->id, 'date_accepted' => '2026-11-01',
            'method' => 'Pills', 'next_supply_date' => '2026-11-06']);
        $this->get(route('prenatal.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['this_month'] === 1);
        $this->get(route('postpartum.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['this_month'] === 1);
        $this->get(route('family_planning.index', ['supply' => 'due']))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['this_month'] === 1 && $stats['due_for_supply'] === 1)
            ->assertViewHas('records', fn ($records) => $records->count() === 1);
    }

    public function test_vaccination_filters_and_incomplete_session_patterns_return_validation_errors(): void
    {
        $unit = HealthUnit::create(['name' => 'Validation RHU']);
        $this->signIn($unit);
        foreach (['vaccination.calendar', 'vaccination.monthly', 'vaccination.monthly.export'] as $route) {
            $this->getJson(route($route, ['month' => 'not-a-month']))->assertUnprocessable()->assertJsonValidationErrors('month');
        }
        $this->postJson(route('vaccination.session_pattern'), ['immunization_pattern' => 'weekly'])
            ->assertUnprocessable()->assertJsonValidationErrors('immunization_weekday');
        $this->postJson(route('vaccination.session_pattern'), ['immunization_pattern' => 'monthly_nth', 'immunization_weekday' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('immunization_week_number');
    }

    public function test_vaccinations_cannot_be_recorded_before_birth(): void
    {
        $unit = HealthUnit::create(['name' => 'Chronology RHU']);
        $this->signIn($unit);
        $this->seed(ImmunizationScheduleSeeder::class);
        $patient = $this->patient($unit, ['birth_date' => '2026-11-01']);
        $this->postJson(route('immunization.store'), ['public_user_id' => $patient->id, 'vaccine' => 'bcg', 'dose_number' => 1,
            'date_given' => '2026-10-31'])->assertUnprocessable()->assertJsonValidationErrors('date_given');
        $this->assertDatabaseCount('immunization_records', 0);
    }

    public function test_vaccine_stock_is_issued_in_dispensing_and_clinical_dose_records_do_not_issue_it_again(): void
    {
        $unit = HealthUnit::create(['name' => 'Vaccination workflow RHU']);
        $staff = $this->signIn($unit);
        $this->seed(ImmunizationScheduleSeeder::class);
        $vaccine = Medicine::create(['medicine_name' => 'Fixture BCG vaccine', 'category' => 'vaccine',
            'unit' => 'Vial', 'is_dispensable' => true]);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $vaccine->id,
            'batch_no' => 'BCG-LOT', 'available_stock' => 5, 'expiration_date' => today()->addYear()]);

        $this->get(route('dispensing.create'))->assertOk()->assertSee($vaccine->medicine_name);
        $this->post(route('dispensing.store'), ['medicine_id' => $vaccine->id, 'quantity_dispensed' => 1,
            'date_dispensed' => today()->toDateString()])->assertSessionHas('success');
        $issue = DispensingRecord::sole();
        $this->assertSame(4, $batch->fresh()->available_stock);
        $this->assertDatabaseHas('stock_movements', ['owner_type' => 'dispensing_records', 'owner_id' => $issue->id,
            'inventory_id' => $batch->id, 'available_delta' => -1]);

        $this->get(route('immunization.tcl'))->assertOk()->assertSee(route('immunization.store'))
            ->assertSee('Log the vial or other stock units used in Dispensing if they have yet to be issued.');
        foreach (['First child', 'Second child'] as $name) {
            $child = $this->patient($unit, ['first_name' => $name, 'birth_date' => '2026-01-01']);
            $dose = ['public_user_id' => $child->id, 'vaccine' => 'bcg', 'dose_number' => 1,
                'date_given' => today()->toDateString(), 'batch_no' => $batch->batch_no];
            $this->post(route('immunization.store'), $dose)->assertSessionHas('success');
            $this->post(route('immunization.store'), $dose)->assertSessionHas('error');
            $record = $child->immunizationRecords()->sole();
            $this->assertSame($batch->batch_no, $record->batch_no);
            $this->assertSame($staff->id, $record->administered_by);
            $this->assertDatabaseHas('vaccination_schedules', ['immunization_record_id' => $record->id,
                'status' => 'given', 'given_date' => today()->toDateString()]);
        }

        $this->assertSame(4, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('dispensing_records', 1);
        $this->assertDatabaseCount('immunization_records', 2);
    }

    public function test_only_pho_admin_can_manage_rhu_reference_records(): void
    {
        $unit = HealthUnit::create(['name' => 'Existing RHU']);
        $this->signIn($unit, 'admin');
        $this->get(route('rhu.index'))->assertForbidden();
        $this->put(route('rhu.update', $unit->id), ['name' => 'Changed', 'municipality' => 'Town', 'address' => 'Address'])->assertForbidden();
        $this->delete(route('rhu.destroy', $unit->id))->assertForbidden();
        $pho = User::factory()->create(['health_unit_id' => null, 'role' => 'admin']);
        $this->withSession(['user_id' => $pho->id])->get(route('rhu.index'))->assertOk();
        $this->post(route('rhu.store'), ['name' => 'New RHU', 'municipality' => 'Town', 'address' => 'Address'])->assertSessionHas('success');
        $this->assertDatabaseHas('health_units', ['name' => 'New RHU']);
    }

    public function test_failed_account_deletion_preserves_patient_and_barangay_assignments(): void
    {
        $unit = HealthUnit::create(['name' => 'Deletion RHU']);
        $this->signIn($unit, 'admin');
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $patient = $this->patient($unit, ['assigned_midwife_id' => $midwife->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Shared Village']);
        $originalDispatcher = User::getEventDispatcher();
        User::setEventDispatcher(clone $originalDispatcher);
        User::deleting(function ($user) use ($midwife) {
            if ($user->id === $midwife->id) {
                throw new QueryException('test', 'delete user', [], new \PDOException('Referenced account cannot be removed'));
            }
        });
        try {
            $this->delete(route('users.destroy', $midwife->id))->assertSessionHas('error');
        } finally {
            User::setEventDispatcher($originalDispatcher);
        }
        $this->assertNotNull($midwife->fresh());
        $this->assertSame($midwife->id, $patient->fresh()->assigned_midwife_id);
        $this->assertDatabaseHas('midwife_barangay', ['user_id' => $midwife->id, 'barangay' => 'Shared Village']);
        $this->assertDatabaseMissing('activity_logs', ['module' => 'Users', 'action' => 'Delete']);
    }

    public function test_facility_with_operational_history_cannot_be_deleted_even_without_user_accounts(): void
    {
        $pho = User::factory()->create(['health_unit_id' => null, 'role' => 'admin']);
        $this->withSession(['user_id' => $pho->id]);
        HealthUnit::create(['name' => 'Keep RHU']);
        $stockUnit = HealthUnit::create(['name' => 'Stock RHU']);
        $patientUnit = HealthUnit::create(['name' => 'Patient RHU']);
        $medicine = Medicine::create(['medicine_name' => 'History medicine']);
        Inventory::create(['health_unit_id' => $stockUnit->id, 'medicine_id' => $medicine->id, 'available_stock' => 10]);
        $patient = $this->patient($patientUnit);
        foreach ([$stockUnit, $patientUnit] as $unit) {
            $this->delete(route('rhu.destroy', $unit->id))->assertSessionHas('error');
            $this->assertNotNull($unit->fresh());
        }
        $this->assertSame(10, Inventory::firstOrFail()->available_stock);
        $this->assertNotNull($patient->fresh());
    }
}
