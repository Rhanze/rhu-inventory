<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientAssignedBarangayOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Patient assigned-area RHU']);
        $primary = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $primary->id, 'barangay' => 'Scope Alpha', 'is_primary' => true]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Scope Alpha']);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Scope Beta', 'is_primary' => true]);
        MidwifeBarangay::create(['user_id' => $primary->id, 'barangay' => 'Other barangay']);
        $this->withSession(['user_id' => $midwife->id]);

        return [$unit, $midwife, $primary];
    }

    private function input(array $extra = []): array
    {
        return $extra + ['first_name' => 'Assigned', 'last_name' => 'Patient', 'birth_date' => '2000-01-01',
            'barangay' => 'Scope Alpha', 'address' => 'Local address'];
    }

    public function test_shared_barangay_registration_stays_with_the_midwife_who_registered_the_patient(): void
    {
        [$unit, $midwife, $primary] = $this->fixture();
        $this->post(route('public_users.store'), $this->input(['barangay' => ' scope   alpha ']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $patient = PublicUser::firstOrFail();
        $this->assertSame('Scope Alpha', $patient->barangay);
        $this->assertSame($midwife->id, $patient->assigned_midwife_id);
        $this->assertSame($midwife->id, $patient->registered_by);
        $this->get(route('public_users.index'))->assertOk()
            ->assertViewHas('members', fn ($members) => $members->contains('id', $patient->id));
        $this->withSession(['user_id' => $primary->id])->get(route('public_users.index'))->assertOk()
            ->assertViewHas('members', fn ($members) => ! $members->contains('id', $patient->id));
        $this->assertDatabaseCount('public_users', 1);
    }

    public function test_patient_edits_accept_another_assigned_area_but_cannot_reassign_the_owner(): void
    {
        [$unit, $midwife, $primary] = $this->fixture();
        $patient = PublicUser::create($this->input() + ['health_unit_id' => $unit->id,
            'registered_by' => $midwife->id, 'assigned_midwife_id' => $midwife->id]);
        $this->put(route('public_users.update', $patient->id), $this->input([
            'barangay' => 'Scope Beta', 'assigned_midwife_id' => $primary->id,
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Scope Beta', $patient->fresh()->barangay);
        $this->assertSame($midwife->id, $patient->fresh()->assigned_midwife_id);
        $this->put(route('public_users.update', $patient->id), $this->input(['barangay' => 'Other barangay']))
            ->assertSessionHasErrors('barangay');
        $this->assertSame('Scope Beta', $patient->fresh()->barangay);
        $this->assertSame($midwife->id, $patient->fresh()->assigned_midwife_id);
    }

    public function test_combined_barangay_values_are_rejected_without_changing_existing_patient_history(): void
    {
        [$unit, $midwife] = $this->fixture();
        $patient = PublicUser::create($this->input(['barangay' => 'Recorded previous area']) + [
            'health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwife->id,
        ]);
        foreach (["Scope Alpha, Scope Beta", "Scope Alpha\nScope Beta", 'Scope Alpha; Scope Beta', 'Scope Alpha / Scope Beta'] as $value) {
            $this->post(route('public_users.store'), $this->input(['barangay' => $value]))->assertSessionHasErrors('barangay');
            $this->put(route('public_users.update', $patient->id), $this->input(['barangay' => $value]))->assertSessionHasErrors('barangay');
            $this->getJson(route('public_users.index', ['barangay' => $value]))->assertUnprocessable()->assertJsonValidationErrors('barangay');
        }
        $this->assertDatabaseCount('public_users', 1);
        $this->assertSame('Recorded previous area', $patient->fresh()->barangay);
        $this->assertSame($midwife->id, $patient->fresh()->assigned_midwife_id);
        $withoutBarangay = $this->input();
        unset($withoutBarangay['barangay']);
        $this->put(route('public_users.update', $patient->id), $withoutBarangay)
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Recorded previous area', $patient->fresh()->barangay);
    }
}
