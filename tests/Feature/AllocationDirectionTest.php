<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AllocationDirectionTest extends TestCase
{
    use RefreshDatabase;

    private function reversedSample(): Allocation
    {
        $pho = HealthUnit::create(['name' => 'Provincial Health Office']);
        $rhu = HealthUnit::create(['name' => 'Receiving RHU', 'facility_type' => 'Rural Health Unit']);
        $user = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $rhu->id, 'is_active' => true]);
        $medicine = Medicine::create(['medicine_name' => 'Direction test', 'category' => 'medicine']);
        $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $rhu->id,
            'batch_no' => 'STK-INIT-0001', 'expiration_date' => today()->addYear(), 'available_stock' => 100, 'allocated_stock' => 0]);
        return Allocation::create(['medicine_id' => $medicine->id, 'inventory_id' => $batch->id,
            'health_unit_id' => $rhu->id, 'destination_health_unit_id' => $pho->id,
            'recipient_user_id' => $user->id, 'allocated_by' => $user->id,
            'quantity_allocated' => 10, 'date_allocated' => today(), 'status' => 'completed']);
    }

    public function test_sample_repair_is_previewable_repeatable_and_does_not_move_stock(): void
    {
        $allocation = $this->reversedSample();
        $source = $allocation->health_unit_id;
        $pho = $allocation->destination_health_unit_id;
        $this->assertSame('Provincial Health Office', $allocation->destination_display);
        $this->artisan('allocations:repair-sample-direction')->assertSuccessful();
        $this->assertSame($source, $allocation->fresh()->health_unit_id);
        $this->artisan('allocations:repair-sample-direction', ['--apply' => true])->assertSuccessful();
        $allocation->refresh();
        $this->assertSame($pho, $allocation->health_unit_id);
        $this->assertSame($source, $allocation->destination_health_unit_id);
        $this->assertNull($allocation->recipient_user_id);
        $this->assertSame('Receiving RHU', $allocation->destination_display);
        $this->assertSame(100, $allocation->inventory->available_stock);
        $this->artisan('allocations:repair-sample-direction', ['--apply' => true])
            ->expectsOutput('0 reversed sample allocations found.')->assertSuccessful();

        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $source, 'is_active' => true]);
        $this->withSession(['user_id' => $staff->id, 'role' => 'staff', 'health_unit_id' => $source])
            ->get('/allocation')->assertOk()->assertSee('Direction test')->assertSee('Receiving RHU');
    }

    public function test_repair_refuses_records_with_stock_movements(): void
    {
        $allocation = $this->reversedSample();
        DB::table('stock_movements')->insert(['owner_type' => 'allocations', 'owner_id' => $allocation->id,
            'inventory_id' => $allocation->inventory_id, 'available_delta' => -10, 'allocated_delta' => 0, 'created_at' => now()]);
        $this->artisan('allocations:repair-sample-direction', ['--apply' => true])->assertFailed();
        $this->assertSame($allocation->health_unit_id, $allocation->fresh()->health_unit_id);
    }

    public function test_provincial_allocation_targets_rhu_without_midwife_recipient(): void
    {
        $sample = $this->reversedSample();
        $phoAdmin = User::factory()->create(['role' => 'admin', 'health_unit_id' => null, 'is_active' => true]);
        $wrongRecipient = User::factory()->create(['role' => 'staff', 'health_unit_id' => $sample->health_unit_id, 'is_active' => true]);
        $payload = ['medicine_id' => $sample->medicine_id, 'destination_health_unit_id' => $sample->health_unit_id,
            'quantity_allocated' => 5, 'date_allocated' => today()->toDateString(), 'recipient_user_id' => $wrongRecipient->id];
        $this->withSession(['user_id' => $phoAdmin->id, 'role' => 'admin', 'health_unit_id' => null])
            ->post('/allocation/province', $payload)->assertSessionHasErrors('recipient_user_id');
        $wrongRecipient->update(['role' => 'midwife', 'health_unit_id' => $sample->destination_health_unit_id]);
        $this->post('/allocation/province', $payload)->assertSessionHasErrors('recipient_user_id');
        $wrongRecipient->update(['health_unit_id' => $sample->health_unit_id, 'is_active' => false]);
        $this->post('/allocation/province', $payload)->assertSessionHasErrors('recipient_user_id');
        $wrongRecipient->update(['is_active' => true]);
        $this->post('/allocation/province', $payload)->assertSessionHasErrors('recipient_user_id');
        Inventory::create(['medicine_id' => $sample->medicine_id, 'health_unit_id' => $sample->destination_health_unit_id,
            'batch_no' => 'PHO-SUPPLY', 'expiration_date' => today()->addYear(), 'available_stock' => 100, 'allocated_stock' => 0]);
        unset($payload['recipient_user_id']);
        $this->post('/allocation/province', $payload)->assertSessionHasNoErrors()->assertRedirect(route('allocation.index'));
        $this->assertDatabaseHas('allocations', ['health_unit_id' => $sample->destination_health_unit_id,
            'destination_health_unit_id' => $sample->health_unit_id, 'recipient_user_id' => null]);
    }
}
