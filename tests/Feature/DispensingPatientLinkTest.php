<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DispensingPatientLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_name_patient_link_is_explicit_and_stock_notes_are_private(): void
    {
        [$unit, $staff, $batch] = $this->fixture();
        $chosen = $this->patient($unit, ['birth_date' => '2000-01-01']);
        $other = $this->patient($unit, ['birth_date' => '2001-01-01']);

        $this->get(route('dispensing.create'))->assertOk()->assertDontSee('Sample Registry');
        $this->postJson(route('dispensing.patient_lookup'), ['query' => 'Sample Reg'])
            ->assertOk()->assertExactJson([
                ['id' => $chosen->id, 'name' => 'Sample Registry', 'birth_date' => '2000-01-01'],
                ['id' => $other->id, 'name' => 'Sample Registry', 'birth_date' => '2001-01-01'],
            ])->assertHeader('Cache-Control', 'no-store, private');

        $this->post(route('dispensing.store'), $this->payload($batch) + ['public_user_id' => $other->id, 'patient_name' => 'Overridden name'])
            ->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->assertSame($other->id, $record->public_user_id);
        $this->assertSame('Sample Registry', $record->patient_name);
        $this->assertSame(18, $batch->fresh()->available_stock);
        $this->assertStringNotContainsString('Sample Registry', StockLog::firstOrFail()->remarks);

        $this->put(route('dispensing.update', $record), $this->payload($batch) + ['patient_name' => 'Different name'])
            ->assertSessionHas('success');
        $this->assertSame($other->id, $record->fresh()->public_user_id);
        $this->assertSame('Sample Registry', $record->fresh()->patient_name);

        $this->post(route('dispensing.store'), $this->payload($batch) + ['patient_name' => 'Sample Registry'])
            ->assertSessionHas('success');
        $this->get(route('patients.show', $other))->assertOk()
            ->assertViewHas('dispensingRecords', fn ($records) => $records->pluck('id')->all() === [$record->id]);
        $this->get(route('patients.show', $chosen))->assertOk()
            ->assertViewHas('dispensingRecords', fn ($records) => $records->isEmpty());
    }

    public function test_midwife_lookup_and_writes_share_patient_scope(): void
    {
        [$unit, $midwife, $batch] = $this->fixture('midwife');
        $otherUnit = HealthUnit::create(['name' => 'Other clinic']);
        $own = $this->patient($unit, ['assigned_midwife_id' => $midwife->id]);
        $unassigned = $this->patient($unit);
        $other = $this->patient($otherUnit);

        $this->postJson(route('dispensing.patient_lookup'), ['query' => 'Sample'])
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $own->id);
        foreach ([$unassigned, $other] as $outsideScope) {
            $this->post(route('dispensing.store'), $this->payload($batch) + ['public_user_id' => $outsideScope->id])
                ->assertSessionHasErrors('public_user_id');
        }
        $this->assertSame(20, $batch->fresh()->available_stock);
        $this->post(route('dispensing.store'), $this->payload($batch) + ['public_user_id' => $own->id])
            ->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->put(route('dispensing.update', $record), $this->payload($batch) + ['public_user_id' => $unassigned->id])
            ->assertSessionHasErrors('public_user_id');
        $this->assertSame($own->id, $record->fresh()->public_user_id);
        $this->assertSame(18, $batch->fresh()->available_stock);
    }

    public function test_failed_prescription_write_preserves_stock_and_previous_attachment(): void
    {
        [$unit, $staff, $batch] = $this->fixture();
        config(['prescriptions.disk' => 'local']);
        $storage = Storage::fake('local');
        $path = 'prescriptions/'.$unit->id.'/original.pdf';
        $storage->put($path, '%PDF-1.4');
        $this->post(route('dispensing.store'), $this->payload($batch))->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $record->update(['prescription_image' => $path]);

        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->twice()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->twice()->andReturn($disk);

        $this->post(route('dispensing.store'), $this->payload($batch) + [
            'prescription_image' => UploadedFile::fake()->create('new.pdf', 4, 'application/pdf'),
        ])->assertSessionHasErrors('prescription_image');
        $this->put(route('dispensing.update', $record), array_replace($this->payload($batch), [
            'quantity_dispensed' => 5,
            'prescription_image' => UploadedFile::fake()->create('replacement.pdf', 4, 'application/pdf'),
        ]))->assertSessionHasErrors('prescription_image');

        $this->assertDatabaseCount('dispensing_records', 1);
        $this->assertSame($path, $record->fresh()->prescription_image);
        $this->assertSame(2, $record->fresh()->quantity_dispensed);
        $this->assertSame(18, $batch->fresh()->available_stock);
        $storage->assertExists($path);
    }

    public function test_midwife_area_search_and_exports_hide_a_colleagues_private_details(): void
    {
        [$unit, $midwife, $batch] = $this->fixture('midwife');
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Shared area', 'is_primary' => true]);
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $batch->medicine->update(['requires_prescription' => true]);
        foreach ([[$midwife, 'Own patient', 'Own doctor'], [$colleague, 'Private colleague patient', 'Private colleague doctor']] as [$actor, $patient, $doctor]) {
            DispensingRecord::create(['medicine_id' => $batch->medicine_id, 'health_unit_id' => $unit->id,
                'barangay' => 'Shared area', 'patient_name' => $patient, 'doctor_name' => $doctor,
                'quantity_dispensed' => 2, 'date_dispensed' => today(), 'dispensed_by' => $actor->id,
                'prescription_image' => 'example.pdf']);
        }

        $this->get(route('dispensing.index', ['search' => 'Private colleague']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->count() === 0);
        $this->get(route('dispensing.index', ['search' => 'Own patient']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->count() === 1);
        $csv = $this->get(route('dispensing.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Own patient', $csv);
        $this->assertStringContainsString('Own doctor', $csv);
        $this->assertStringNotContainsString('Private colleague patient', $csv);
        $this->assertStringNotContainsString('Private colleague doctor', $csv);
    }

    private function fixture(string $role = 'staff'): array
    {
        $unit = HealthUnit::create(['name' => 'Test clinic']);
        $actor = User::factory()->create(['health_unit_id' => $unit->id, 'role' => $role]);
        $medicine = Medicine::create(['medicine_name' => 'Test item', 'is_dispensable' => true]);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 20, 'batch_no' => 'B001', 'expiration_date' => today()->addMonth()]);
        $this->withSession(['user_id' => $actor->id]);

        return [$unit, $actor, $batch];
    }

    private function patient(HealthUnit $unit, array $extra = []): PublicUser
    {
        return PublicUser::create($extra + ['health_unit_id' => $unit->id, 'first_name' => 'Sample', 'last_name' => 'Registry',
            'birth_date' => '2000-01-01', 'address' => 'Test address']);
    }

    private function payload(Inventory $batch): array
    {
        return ['inventory_id' => $batch->id, 'quantity_dispensed' => 2, 'date_dispensed' => today()->toDateString()];
    }
}
