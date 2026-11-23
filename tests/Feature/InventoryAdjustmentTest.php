<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_metadata_form_can_save_after_dispensing_without_restoring_stock(): void
    {
        [$batch, $editor] = $this->batchAndEditor();
        $dispenser = User::factory()->create(['role' => 'staff', 'health_unit_id' => $batch->health_unit_id]);
        $editUrl = route('inventory.edit', $batch->id);

        $this->withSession(['user_id' => $editor->id])->get($editUrl)->assertOk();
        $this->withSession(['user_id' => $dispenser->id])->post(route('dispensing.store'), [
            'medicine_id' => $batch->medicine_id,
            'quantity_dispensed' => 20,
            'date_dispensed' => today()->toDateString(),
        ])->assertSessionHas('success');

        $this->withSession(['user_id' => $editor->id])->from($editUrl)->put(route('inventory.update', $batch->id), [
            'storage_location' => 'Cabinet B',
            'available_stock' => 100,
            'expected_available_stock' => 100,
        ])->assertRedirect(route('inventory.index'))->assertSessionHasNoErrors();

        $this->assertSame(80, $batch->fresh()->available_stock);
        $this->assertSame('Cabinet B', $batch->fresh()->storage_location);
        $this->assertDatabaseCount('stock_logs', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Inventory', 'action' => 'Update', 'user_id' => $editor->id]);
    }

    public function test_adjustment_requires_a_reason_and_posts_the_correction_once(): void
    {
        [$batch, $editor] = $this->batchAndEditor();
        $url = route('inventory.adjust', $batch->id);
        $editUrl = route('inventory.edit', $batch->id);
        $this->withSession(['user_id' => $editor->id])->get($editUrl)->assertOk()->assertSee($url);

        $payload = ['available_stock' => 92, 'expected_available_stock' => 100];
        $this->from($editUrl)->put($url, $payload)->assertSessionHasErrors('remarks');
        $this->assertSame(100, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('stock_logs', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $reason = 'Eight damaged tablets removed during the count.';
        $this->put($url, $payload + ['remarks' => $reason])->assertSessionHas('success');
        $this->from($editUrl)->put($url, $payload + ['remarks' => $reason])->assertSessionHasErrors('available_stock');

        $this->assertSame(92, $batch->fresh()->available_stock);
        $log = StockLog::sole();
        $this->assertSame('adjustment_out', $log->movement_type);
        $this->assertSame(8, $log->quantity_added);
        $this->assertStringContainsString($reason, $log->remarks);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', [
            'owner_type' => 'stock_logs', 'owner_id' => $log->id, 'inventory_id' => $batch->id,
            'available_delta' => -8, 'allocated_delta' => 0, 'performed_by' => $editor->id, 'reason' => $reason,
        ]);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Adjust Stock', 'user_id' => $editor->id]);
    }

    public function test_metadata_endpoint_cannot_change_quantity_even_with_a_current_balance(): void
    {
        [$batch, $editor] = $this->batchAndEditor();
        $this->withSession(['user_id' => $editor->id])->put(route('inventory.update', $batch->id), [
            'storage_location' => 'Cabinet C', 'available_stock' => 125, 'expected_available_stock' => 100,
        ])->assertSessionHas('success');

        $this->assertSame('Cabinet C', $batch->fresh()->storage_location);
        $this->assertSame(100, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('stock_logs', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_provincial_receipt_stock_is_read_only_in_inventory(): void
    {
        [$batch] = $this->batchAndEditor();
        $pho = HealthUnit::create(['name' => 'Adjustment test PHO', 'facility_type' => 'Provincial Health Office']);
        $administrator = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $batch->update(['health_unit_id' => $pho->id]);
        $this->withSession(['user_id' => $administrator->id])
            ->get(route('inventory.index', ['rhu_filter' => 'pho']))->assertOk()->assertSee('Read-only');
        $this->get(route('inventory.edit', $batch->id))->assertForbidden();
        $this->put(route('inventory.update', $batch->id), ['storage_location' => 'Wrong cabinet'])->assertForbidden();
        $this->put(route('inventory.adjust', $batch->id), ['available_stock' => 95, 'expected_available_stock' => 100, 'remarks' => 'Test'])->assertForbidden();
        $this->delete(route('inventory.destroy', $batch->id))->assertForbidden();
        $this->assertSame('Cabinet A', $batch->fresh()->storage_location);
    }

    public function test_stock_adjustment_is_facility_scoped_and_rolls_back_if_the_ledger_cannot_be_written(): void
    {
        [$batch, $editor] = $this->batchAndEditor();
        $otherFacility = HealthUnit::create(['name' => 'Other adjustment RHU']);
        $outsider = User::factory()->create(['role' => 'staff', 'health_unit_id' => $otherFacility->id]);
        $url = route('inventory.adjust', $batch->id);
        $payload = ['available_stock' => 115, 'expected_available_stock' => 100, 'remarks' => 'Physical count'];
        $this->withSession(['user_id' => $outsider->id])->put($url, $payload)->assertNotFound();

        $this->mock(StockMovementService::class)->shouldReceive('record')
            ->once()->andThrow(new \RuntimeException('Simulated ledger write failure'));
        $this->withSession(['user_id' => $editor->id])->put($url, $payload)->assertServerError();
        $this->assertSame(100, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('stock_logs', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    private function batchAndEditor(): array
    {
        $facility = HealthUnit::create(['name' => 'Adjustment test RHU', 'facility_type' => 'Rural Health Unit']);
        $editor = User::factory()->create(['role' => 'admin', 'health_unit_id' => $facility->id]);
        $medicine = Medicine::create(['medicine_name' => 'Adjustment test stock', 'is_dispensable' => true]);
        $batch = Inventory::create([
            'health_unit_id' => $facility->id, 'medicine_id' => $medicine->id, 'available_stock' => 100,
            'batch_no' => 'COUNT-1', 'expiration_date' => today()->addMonth(), 'storage_location' => 'Cabinet A',
        ]);

        return [$batch, $editor];
    }
}
