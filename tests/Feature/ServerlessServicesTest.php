<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use App\Support\ClinicalAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServerlessServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_prescription_can_stream_from_configured_private_storage(): void
    {
        config(['prescriptions.disk' => 's3']);
        Storage::fake('s3');
        $unit = HealthUnit::create(['name' => 'Remote-file RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'Fixture', 'category' => 'medicine']);
        Storage::disk('s3')->put('prescriptions/fixture.pdf', '%PDF-1.4 fixture');
        $record = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'patient_name' => 'Fixture', 'quantity_dispensed' => 1, 'date_dispensed' => today(),
            'dispensed_by' => $user->id, 'prescription_image' => 'prescriptions/fixture.pdf']);
        $this->get(route('dispensing.prescription', $record->id))->assertRedirect(route('login'));
        $response = $this->withSession(['user_id' => $user->id, 'role' => 'staff', 'health_unit_id' => $unit->id])
            ->get(route('dispensing.prescription', $record->id))->assertOk();
        $this->assertSame('%PDF-1.4 fixture', $response->streamedContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_prescription_access_requires_the_facility_and_the_midwife_owner(): void
    {
        config(['prescriptions.disk' => 's3']);
        Storage::fake('s3')->put('prescriptions/scope.pdf', '%PDF-1.4 fixture');
        $facility = HealthUnit::create(['name' => 'Prescription facility']);
        $otherFacility = HealthUnit::create(['name' => 'Other facility']);
        $owner = User::factory()->create(['health_unit_id' => $facility->id, 'role' => 'midwife']);
        $colleague = User::factory()->create(['health_unit_id' => $facility->id, 'role' => 'midwife']);
        $outside = User::factory()->create(['health_unit_id' => $otherFacility->id, 'role' => 'staff']);
        $pho = User::factory()->create(['health_unit_id' => null, 'role' => 'admin']);
        $record = DispensingRecord::create([
            'medicine_id' => Medicine::create(['medicine_name' => 'Prescription fixture'])->id,
            'health_unit_id' => $facility->id, 'quantity_dispensed' => 1, 'date_dispensed' => today(),
            'dispensed_by' => $owner->id, 'prescription_image' => 'prescriptions/scope.pdf',
        ]);
        $url = route('dispensing.prescription', $record->id);

        $this->withSession(['user_id' => $pho->id])->get($url)->assertForbidden();
        $this->withSession(['user_id' => $outside->id])->get($url)->assertNotFound();
        $this->withSession(['user_id' => $colleague->id])->get($url)->assertNotFound();
        $this->withSession(['user_id' => $owner->id])->get($url)->assertOk();
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', ['module' => ClinicalAudit::MODULE, 'action' => 'Download',
            'user_id' => $owner->id, 'description' => 'prescription #'.$record->id]);
    }

    public function test_scheduler_requires_management_and_secret_and_deduplicates_a_minute(): void
    {
        $secret = str_repeat('x', 48);
        config(['portals.surface' => 'management', 'serverless.cron_secret' => $secret]);
        $this->get(route('internal.scheduler'))->assertForbidden();
        Artisan::shouldReceive('call')->once()->with('schedule:run', ['--no-interaction' => true])->andReturn(0);
        $this->withToken($secret)->getJson(route('internal.scheduler'))->assertOk()->assertJsonPath('status', 'processed');
        $this->getJson(route('internal.scheduler'))->assertOk()->assertJsonPath('status', 'already_processed');
        config(['portals.surface' => 'public']);
        $this->getJson(route('internal.scheduler'))->assertNotFound();
    }
}
