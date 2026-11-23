<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'admin'): array
    {
        $unit = HealthUnit::create(['name' => 'Audit RHU', 'facility_type' => 'Rural Health Unit']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => $role]);
        $medicine = Medicine::create(['medicine_name' => 'Audit medicine', 'category' => 'medicine', 'unit' => 'tablet']);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 100, 'allocated_stock' => 0, 'batch_no' => 'AUDIT', 'expiration_date' => today()->addMonth()]);
        $this->withSession(['user_id' => $user->id, 'role' => $role, 'health_unit_id' => $unit->id]);

        return [$unit, $user, $medicine, $batch];
    }

    public function test_stale_inventory_edit_cannot_overwrite_a_transfer_reservation(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $destination = HealthUnit::create(['name' => 'Destination RHU']);
        $transfer = TransferRequest::create(['from_health_unit_id' => $unit->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'quantity' => 10, 'status' => 'pending', 'requested_by' => $user->id]);
        app(TransferService::class)->reserve($transfer->id, $unit->id);
        $this->put(route('inventory.update', $batch->id), ['available_stock' => 90, 'allocated_stock' => 0,
            'batch_no' => 'AUDIT', 'expiration_date' => $batch->expiration_date->toDateString()])->assertSessionMissing('errors');
        $this->assertSame(10, $batch->fresh()->allocated_stock);
        $receiver = User::factory()->create(['health_unit_id' => $destination->id, 'role' => 'staff']);
        app(TransferService::class)->complete($transfer->id, $receiver->id, $destination->id);
        $this->assertSame(90, $batch->fresh()->available_stock);
        $this->assertSame(0, $batch->fresh()->allocated_stock);
        $this->assertSame(10, (int) Inventory::where('health_unit_id', $destination->id)->sum('available_stock'));
    }

    public function test_reserved_lot_is_locked_and_receipt_rejects_a_changed_snapshot(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $destination = HealthUnit::create(['name' => 'Destination RHU']);
        $receiver = User::factory()->create(['health_unit_id' => $destination->id, 'role' => 'staff']);
        $transfer = TransferRequest::create(['from_health_unit_id' => $unit->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'quantity' => 10, 'status' => 'pending', 'requested_by' => $user->id]);
        app(TransferService::class)->reserve($transfer->id, $unit->id);

        $this->put(route('inventory.update', $batch->id), ['batch_no' => 'OTHER-LOT'])->assertSessionHasErrors('batch_no');
        $this->assertSame('AUDIT', $batch->fresh()->batch_no);
        $this->assertSame($batch->expiration_date->toDateString(), $transfer->fresh()->reserved_expiration_date->toDateString());

        // Simulate legacy or out-of-band drift that must never silently change the credited lot.
        $batch->update(['expiration_date' => today()->addYear()]);
        $this->withSession(['user_id' => $receiver->id])->post(route('transfers.receive', $transfer->id), ['received_quantity' => 10])
            ->assertSessionHasErrors('stock');
        $this->assertSame('accepted', $transfer->fresh()->status);
        $this->assertSame(10, $batch->fresh()->allocated_stock);
        $this->assertFalse(Inventory::where('health_unit_id', $destination->id)->exists());
    }

    public function test_approved_request_can_be_received_only_by_its_recipient_without_another_stock_deduction(): void
    {
        [$unit, $admin, $medicine, $batch] = $this->fixture();
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $request = Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'quantity_allocated' => 20, 'date_allocated' => today(), 'allocated_by' => $midwife->id,
            'recipient_user_id' => $midwife->id, 'status' => 'pending', 'destination' => 'Clinic supply']);
        $this->post(route('allocation.approve_supply_request', $request->id))->assertSessionHas('success');
        $this->assertSame('approved', $request->fresh()->status);
        $this->post(route('allocation.mark_received', $request->id))->assertNotFound();
        $this->withSession(['user_id' => $midwife->id]);
        $this->get(route('supply_requests.index'))->assertOk()->assertSee('Confirm Receipt');
        $this->post(route('allocation.mark_received', $request->id))->assertSessionHas('success');
        $this->assertSame('received', $request->fresh()->status);
        $this->assertSame(80, $batch->fresh()->available_stock);
        $this->post(route('allocation.mark_received', $request->id))->assertSessionHas('error');
        $this->assertSame(80, $batch->fresh()->available_stock);
    }

    public function test_changing_a_direct_allocation_recipient_updates_the_receipt_state(): void
    {
        [$unit, $admin, $medicine, $batch] = $this->fixture();
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $payload = ['inventory_id' => $batch->id, 'destination' => 'Clinic', 'quantity_allocated' => 10,
            'date_allocated' => today()->toDateString()];
        $this->post(route('allocation.store'), $payload)->assertSessionHas('success');
        $allocation = Allocation::firstOrFail();
        $this->assertSame('completed', $allocation->status);
        $this->put(route('allocation.update', $allocation->id), $payload + ['recipient_user_id' => $midwife->id])
            ->assertSessionHas('success');
        $this->assertSame('awaiting_receipt', $allocation->fresh()->status);
        $this->assertDatabaseHas('notifications', ['allocation_id' => $allocation->id, 'user_id' => $midwife->id,
            'type' => 'allocation_to_user', 'is_read' => false]);
        $this->put(route('allocation.update', $allocation->id), $payload)->assertSessionHas('success');
        $this->assertSame('completed', $allocation->fresh()->status);
        $this->assertNull($allocation->fresh()->recipient_user_id);
        $this->assertSame(90, $batch->fresh()->available_stock);
    }

    public function test_editing_an_approved_supply_request_preserves_its_owner_and_receipt_workflow(): void
    {
        [$unit, $admin, $medicine, $batch] = $this->fixture();
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $request = Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'quantity_allocated' => 20, 'date_allocated' => today(), 'allocated_by' => $midwife->id,
            'recipient_user_id' => $midwife->id, 'status' => 'pending', 'destination' => 'Clinic supply']);
        $this->post(route('allocation.approve_supply_request', $request->id))->assertSessionHas('success');
        $this->put(route('allocation.update', $request->id), ['inventory_id' => $batch->id, 'destination' => 'Clinic supply',
            'quantity_allocated' => 15, 'date_allocated' => today()->toDateString()])->assertSessionHas('success');
        $this->assertSame($midwife->id, $request->fresh()->allocated_by);
        $this->assertSame($midwife->id, (int) $request->fresh()->recipient_user_id);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(85, $batch->fresh()->available_stock);
        $this->withSession(['user_id' => $midwife->id])->get(route('supply_requests.index'))->assertOk()->assertSee('Confirm Receipt');
        $this->post(route('allocation.mark_received', $request->id))->assertSessionHas('success');
        $this->assertSame('received', $request->fresh()->status);
    }

    public function test_null_facility_staff_cannot_mutate_provincial_stock_or_issue_directives(): void
    {
        [, , , $batch] = $this->fixture();
        $staff = User::factory()->create(['health_unit_id' => null, 'role' => 'staff']);
        foreach ([
            ['GET', route('allocation.province'), []],
            ['POST', route('allocation.province.store'), []],
            ['GET', route('transfers.distribute'), []],
            ['POST', route('transfers.distribute.store'), []],
            ['PUT', route('inventory.update', $batch->id), ['available_stock' => 999, 'allocated_stock' => 0]],
            ['DELETE', route('inventory.destroy', $batch->id), []],
        ] as [$method, $url, $data]) {
            $this->withSession(['user_id' => $staff->id])->call($method, $url, $data)
                ->assertRedirect(route('login'))->assertSessionMissing('user_id');
        }
        $this->assertSame(100, $batch->fresh()->available_stock);
        $this->assertDatabaseCount('allocations', 0);
        $this->assertDatabaseCount('transfer_requests', 0);
    }

    public function test_expired_stock_does_not_make_a_transfer_request_or_supply_balance_available(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture('midwife');
        $batch->update(['available_stock' => 10]);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 999, 'expiration_date' => today()->subDay()]);
        $this->get(route('supply_requests.create'))->assertOk()
            ->assertViewHas('stockLevels', fn ($levels) => $levels[$medicine->id]['total'] === 10);
        $source = HealthUnit::create(['name' => 'Expired source']);
        Inventory::create(['health_unit_id' => $source->id, 'medicine_id' => $medicine->id,
            'available_stock' => 999, 'expiration_date' => today()->subDay()]);
        $user->update(['role' => 'staff']);
        $this->post(route('transfers.store'), ['source_rhu_id' => $source->id, 'medicine_id' => $medicine->id, 'quantity' => 10])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('transfer_requests', 0);
    }

    public function test_future_stock_receipts_cannot_increase_current_rhu_or_provincial_stock(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $tomorrow = today()->addDay()->toDateString();
        $this->post(route('stock_logs.store'), ['medicine_id' => $medicine->id,
            'quantity_added' => 30, 'date_added' => $tomorrow, 'batch_no' => $batch->batch_no])
            ->assertSessionHasErrors('date_added');

        $pho = User::factory()->create(['health_unit_id' => null, 'role' => 'admin']);
        $this->withSession(['user_id' => $pho->id])->post(route('inventory.pho_receive.store'), [
            'submission_token' => (string) \Illuminate\Support\Str::uuid(),
            'received_date' => $tomorrow,
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 30]],
        ])->assertSessionHasErrors('received_date');

        $this->assertDatabaseCount('inventory', 1);
        $this->assertDatabaseCount('stock_logs', 0);
        $this->assertDatabaseCount('doh_shipments', 0);
        $this->assertSame(100, $batch->fresh()->available_stock);
    }
}
