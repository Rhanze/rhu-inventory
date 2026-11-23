<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class MidwifeAssignedAreaChoicesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Assigned choices RHU', 'facility_type' => 'Rural Health Unit']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Assigned Alpha', 'is_primary' => true]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Assigned Beta']);
        MidwifeBarangay::create(['user_id' => $colleague->id, 'barangay' => 'Colleague area']);
        $patient = PublicUser::create(['health_unit_id' => $unit->id, 'assigned_midwife_id' => $midwife->id,
            'first_name' => 'Historical', 'last_name' => 'Patient', 'birth_date' => '2000-01-01',
            'barangay' => 'Recorded old area', 'address' => 'Local address']);
        $medicine = Medicine::create(['medicine_name' => 'Assigned choices medicine', 'category' => 'medicine', 'requires_prescription' => false]);
        $inventory = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 100, 'allocated_stock' => 0, 'expiration_date' => today()->addMonth()]);
        $this->withSession(['user_id' => $midwife->id]);

        return compact('unit', 'midwife', 'colleague', 'patient', 'medicine', 'inventory');
    }

    private function optionValues(string $html, string $id): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
            $nodes = (new \DOMXPath($document))->query('//select[@id="'.$id.'"]/option/@value');

            return array_map(fn ($node) => $node->nodeValue, iterator_to_array($nodes));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function patientData(array $extra = []): array
    {
        return $extra + ['first_name' => 'New', 'last_name' => 'Patient', 'birth_date' => '2000-01-01',
            'barangay' => 'Assigned Alpha', 'address' => 'Local address'];
    }

    public function test_midwife_patient_and_dispensing_choices_use_assignments_without_needing_patient_records(): void
    {
        $fixture = $this->fixture();
        foreach (['public_users.create' => 'patient-barangay', 'public_users.index' => 'pf-barangay',
            'dispensing.create' => 'dispensing-barangay'] as $route => $select) {
            $response = $this->get(route($route))->assertOk();
            $this->assertSame(['', 'Assigned Alpha', 'Assigned Beta'], $this->optionValues($response->getContent(), $select));
        }
        $this->post(route('public_users.store'), $this->patientData(['barangay' => 'assigned beta']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('public_users', ['first_name' => 'New', 'barangay' => 'Assigned Beta', 'assigned_midwife_id' => $fixture['midwife']->id]);
    }

    public function test_midwife_patient_input_and_filters_reject_unassigned_area_tampering(): void
    {
        $fixture = $this->fixture();
        foreach (['Colleague area', 'Recorded old area', 'Invented area'] as $area) {
            $this->post(route('public_users.store'), $this->patientData(['barangay' => $area]))->assertSessionHasErrors('barangay');
            $this->getJson(route('public_users.index', ['barangay' => $area]))->assertUnprocessable()->assertJsonValidationErrors('barangay');
            $this->put(route('public_users.update', $fixture['patient']->id), $this->patientData(['barangay' => $area]))
                ->assertSessionHasErrors('barangay');
        }
        $this->assertDatabaseCount('public_users', 1);
        $this->assertSame('Recorded old area', $fixture['patient']->fresh()->barangay);
        $this->get(route('public_users.index', ['barangay' => '  assigned   alpha ']))->assertOk()
            ->assertSee('value="Assigned Alpha" selected', false);
    }

    public function test_recorded_patient_and_dispensing_areas_remain_history_without_becoming_choices(): void
    {
        $fixture = $this->fixture();
        $response = $this->get(route('public_users.edit', $fixture['patient']->id))->assertOk()->assertSee('Recorded area — Recorded old area');
        $this->assertSame(['', 'Assigned Alpha', 'Assigned Beta'], $this->optionValues($response->getContent(), 'patient-barangay'));
        $payload = $this->patientData();
        unset($payload['barangay']);
        $this->put(route('public_users.update', $fixture['patient']->id), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Recorded old area', $fixture['patient']->fresh()->barangay);

        $record = DispensingRecord::create(['health_unit_id' => $fixture['unit']->id, 'medicine_id' => $fixture['medicine']->id,
            'dispensed_by' => $fixture['midwife']->id, 'quantity_dispensed' => 3, 'date_dispensed' => today(), 'barangay' => 'Recorded old area']);
        $response = $this->get(route('dispensing.edit', $record->id))->assertOk()->assertSee('Recorded old area (recorded area)');
        $this->assertSame(['', 'Assigned Alpha', 'Assigned Beta'], $this->optionValues($response->getContent(), 'dispensing-barangay'));
    }

    public function test_unassigned_midwife_gets_empty_choices_and_cannot_register_a_guessed_area(): void
    {
        $fixture = $this->fixture();
        MidwifeBarangay::where('user_id', $fixture['midwife']->id)->delete();
        foreach (['public_users.create' => 'patient-barangay', 'public_users.index' => 'pf-barangay',
            'dispensing.create' => 'dispensing-barangay'] as $route => $select) {
            $response = $this->get(route($route))->assertOk()->assertSee('No assigned barangays');
            $this->assertSame([''], $this->optionValues($response->getContent(), $select));
        }
        $this->post(route('public_users.store'), $this->patientData())->assertSessionHasErrors('barangay');
        $this->post(route('dispensing.store'), ['inventory_id' => $fixture['inventory']->id, 'quantity_dispensed' => 2,
            'date_dispensed' => today()->toDateString(), 'barangay' => 'Assigned Alpha'])->assertSessionHasErrors('barangay');
        $this->assertDatabaseCount('public_users', 1);
        $this->assertDatabaseCount('dispensing_records', 0);
    }

    public function test_disease_filters_chart_and_export_reject_other_areas_for_midwives(): void
    {
        $this->fixture();
        $response = $this->get(route('disease_reports.index'))->assertOk();
        $this->assertSame(['Assigned Alpha', 'Assigned Beta'], $this->optionValues($response->getContent(), 'demand-barangay'));
        $this->get(route('disease_reports.index', ['barangay' => '  assigned   beta ']))->assertOk()
            ->assertViewHas('selectedBarangay', 'Assigned Beta');
        foreach (['disease_reports.index', 'disease_reports.chart_data', 'disease_reports.export'] as $route) {
            $this->getJson(route($route, ['barangay' => 'Colleague area']))->assertForbidden();
        }
    }

    public function test_patient_csv_imports_use_the_same_midwife_assignment_validation(): void
    {
        $this->fixture();
        $this->post(route('imports.store', ['section' => 'patients']), [
            'file' => UploadedFile::fake()->createWithContent('patients.csv', "first_name,last_name,birth_date,barangay,address\nImported,Patient,2000-01-01,Colleague area,Local address\n"),
            'import_token' => (string) Str::uuid(),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('public_users', 1);
        $this->assertDatabaseCount('csv_import_batches', 0);
    }

    public function test_staff_keep_free_text_patient_areas_and_existing_rhu_wide_choices(): void
    {
        $fixture = $this->fixture();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $fixture['unit']->id]);
        $this->withSession(['user_id' => $staff->id])->get(route('public_users.create'))->assertOk()
            ->assertSee('type="text" name="barangay"', false);
        $this->post(route('public_users.store'), $this->patientData(['barangay' => 'New staff area']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('public_users', ['barangay' => 'New staff area', 'registered_by' => $staff->id]);
    }
}
