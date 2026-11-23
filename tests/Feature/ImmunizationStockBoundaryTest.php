<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\ImmunizationRecord;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\PublicUser;
use App\Models\User;
use Database\Seeders\ImmunizationScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImmunizationStockBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_an_administered_dose_does_not_issue_another_vial(): void
    {
        $this->seed(ImmunizationScheduleSeeder::class);
        $facility = HealthUnit::create(['name' => 'Vaccination RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $facility->id]);
        $child = PublicUser::create(['health_unit_id' => $facility->id, 'first_name' => 'Test',
            'last_name' => 'Child', 'birth_date' => today()->subMonth(), 'address' => 'Fixture address']);
        $vaccine = Medicine::create(['medicine_name' => 'BCG Vaccine', 'category' => 'vaccine',
            'unit' => 'Vial', 'is_dispensable' => true]);
        $batch = Inventory::create(['health_unit_id' => $facility->id, 'medicine_id' => $vaccine->id,
            'batch_no' => 'BCG-LOT', 'available_stock' => 5, 'expiration_date' => today()->addMonth()]);

        $this->withSession(['user_id' => $staff->id])->post(route('dispensing.store'), [
            'medicine_id' => $vaccine->id, 'quantity_dispensed' => 1, 'date_dispensed' => today()->toDateString(),
        ])->assertSessionHas('success');
        $this->assertSame(4, $batch->fresh()->available_stock);

        $payload = ['public_user_id' => $child->id, 'vaccine' => 'BCG', 'dose_number' => 1,
            'date_given' => today()->toDateString(), 'batch_no' => $batch->batch_no];
        $this->post(route('immunization.store'), $payload)->assertSessionHas('success');
        $this->post(route('immunization.store'), $payload)->assertSessionHas('error');

        $record = ImmunizationRecord::sole();
        $this->assertSame($batch->batch_no, $record->batch_no);
        $this->assertDatabaseHas('vaccination_schedules', [
            'public_user_id' => $child->id, 'vaccine_name' => 'BCG', 'status' => 'given',
        ]);
        $this->assertSame(4, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
