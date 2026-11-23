<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\TransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidwifeSupplyNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function incomingTransfer(): array
    {
        $source = HealthUnit::create(['name' => 'Source RHU']);
        $destination = HealthUnit::create(['name' => 'Receiving RHU']);
        $sourceUser = User::factory()->create(['role' => 'staff', 'health_unit_id' => $source->id]);
        $medicine = Medicine::create(['medicine_name' => 'RHU transfer medicine', 'category' => 'medicine']);
        $batch = Inventory::create(['health_unit_id' => $source->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'RHU-TRANSFER', 'expiration_date' => today()->addYear(),
            'available_stock' => 90, 'allocated_stock' => 10]);
        $transfer = TransferRequest::create(['from_health_unit_id' => $source->id,
            'to_health_unit_id' => $destination->id, 'medicine_id' => $medicine->id,
            'inventory_id' => $batch->id, 'batch_no' => $batch->batch_no, 'quantity' => 10,
            'status' => 'accepted', 'stock_reserved' => true, 'requested_by' => $sourceUser->id,
            'approved_at' => now()]);

        return [$destination, $transfer, $batch];
    }

    public function test_midwife_dashboard_has_no_rhu_transfers_and_direct_transfer_routes_return_to_her_dashboard(): void
    {
        [$unit, $transfer, $batch] = $this->incomingTransfer();
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $midwife->id]);

        $this->get(route('dashboard.midwife'))->assertOk()
            ->assertDontSee('Incoming Transfers')->assertDontSee('RHU transfer medicine')
            ->assertDontSee(route('transfers.receive', $transfer->id), false);
        foreach (['transfers.index', 'transfers.create', 'transfers.distribute'] as $route) {
            $this->get(route($route))->assertRedirect(route('dashboard.midwife'));
        }
        foreach (['transfers.store', 'transfers.distribute.store'] as $route) {
            $this->post(route($route), [])->assertRedirect(route('dashboard.midwife'));
        }
        foreach (['transfers.receive', 'transfers.cancel', 'transfers.accept', 'transfers.reject',
            'transfers.pho_approve', 'transfers.pho_reject'] as $route) {
            $this->post(route($route, $transfer->id), [])->assertRedirect(route('dashboard.midwife'));
        }
        $this->get(route('dashboard.midwife'))->assertOk()->assertSessionHas('user_id', $midwife->id);
        $this->assertSame('accepted', $transfer->fresh()->status);
        $this->assertSame(90, $batch->fresh()->available_stock);
        $this->assertSame(10, $batch->fresh()->allocated_stock);
        $this->assertSame(0, (int) Inventory::where('health_unit_id', $unit->id)->sum('available_stock'));
    }

    public function test_rhu_admins_and_staff_can_still_receive_incoming_transfers(): void
    {
        foreach (['admin', 'staff'] as $role) {
            [$unit, $transfer, $batch] = $this->incomingTransfer();
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
            $this->withSession(['user_id' => $user->id]);
            $this->get(route('transfers.index'))->assertOk()->assertSee('RHU transfer medicine');
            $this->from(route('transfers.index'))->post(route('transfers.receive', $transfer->id), ['received_quantity' => 10])
                ->assertRedirect(route('transfers.index'))->assertSessionHas('success');
            $this->assertSame('received', $transfer->fresh()->status);
            $this->assertSame($user->id, $transfer->fresh()->received_by);
            $this->assertSame(0, $batch->fresh()->allocated_stock);
            $this->assertSame(10, (int) Inventory::where('health_unit_id', $unit->id)->sum('available_stock'));
        }
    }

    public function test_recent_requests_view_all_opens_the_midwifes_full_supply_list_with_matching_scope(): void
    {
        $unit = HealthUnit::create(['name' => 'Supply list RHU']);
        $otherUnit = HealthUnit::create(['name' => 'Other supply RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Own supply medicine']);
        $base = ['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'allocated_by' => $midwife->id, 'recipient_user_id' => $midwife->id,
            'date_allocated' => today(), 'quantity_allocated' => 1, 'status' => 'pending'];
        $ownIds = [];
        foreach (range(1, 7) as $number) {
            $ownIds[] = Allocation::create($base + ['destination' => 'Own request '.$number])->id;
        }
        Allocation::create(array_replace($base, ['allocated_by' => $colleague->id,
            'recipient_user_id' => $colleague->id, 'destination' => 'Colleague request']));
        Allocation::create(array_replace($base, ['health_unit_id' => $otherUnit->id,
            'destination' => 'Other RHU request']));
        Allocation::create($base + ['destination_health_unit_id' => $otherUnit->id,
            'destination' => 'Interfacility allocation']);

        $this->withSession(['user_id' => $midwife->id]);
        $this->get(route('dashboard.midwife'))->assertOk()
            ->assertViewHas('pendingRequests', 7)
            ->assertViewHas('recentRequests', fn ($requests) => $requests->pluck('id')->all() === array_reverse(array_slice($ownIds, -5)))
            ->assertSee('href="'.route('supply_requests.index').'" aria-label="View all supply requests"', false)
            ->assertDontSee('Colleague request')->assertDontSee('Other RHU request')->assertDontSee('Interfacility allocation');
        $this->get(route('supply_requests.index'))->assertOk()
            ->assertViewHas('requests', fn ($requests) => $requests->total() === 7
                && $requests->pluck('id')->all() === array_reverse($ownIds))
            ->assertViewHas('counts', fn ($counts) => $counts['pending'] === 7)
            ->assertSee('Own request 1')->assertSee('Own request 7')
            ->assertDontSee('Colleague request')->assertDontSee('Other RHU request')->assertDontSee('Interfacility allocation');
    }
}
