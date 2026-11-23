<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationActionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_receiving_rhu_can_view_an_incoming_allocation_and_the_pho_can_reverse_it(): void
    {
        $pho = HealthUnit::create(['name' => 'Supply PHO', 'facility_type' => 'Provincial Health Office']);
        $rhu = HealthUnit::create(['name' => 'Receiving RHU', 'facility_type' => 'Rural Health Unit']);
        $phoAdmin = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $rhuStaff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $rhu->id]);
        $medicine = Medicine::create(['medicine_name' => 'Incoming stock']);
        $source = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $pho->id, 'available_stock' => 100]);
        $this->withSession(['user_id' => $phoAdmin->id])->post(route('allocation.province.store'), [
            'medicine_id' => $medicine->id, 'destination_health_unit_id' => $rhu->id,
            'quantity_allocated' => 15, 'date_allocated' => today()->toDateString(),
        ])->assertSessionHas('success');
        $allocation = Allocation::sole();
        $destination = Inventory::where('health_unit_id', $rhu->id)->sole();
        $edit = route('allocation.edit', $allocation->id);
        $delete = route('allocation.destroy', $allocation->id);

        $this->withSession(['user_id' => $rhuStaff->id]);
        $this->get($edit)->assertNotFound();
        $this->delete($delete)->assertNotFound();
        $this->assertSame([85, 15], [$source->fresh()->available_stock, $destination->fresh()->available_stock]);
        $this->get(route('allocation.index'))->assertOk()->assertSee('Incoming stock')->assertDontSee($edit)->assertDontSee($delete);

        $this->withSession(['user_id' => $phoAdmin->id]);
        $this->get(route('allocation.index'))->assertOk()->assertDontSee($edit)->assertSee($delete);
        $this->delete($delete)->assertSessionHas('success');
        $this->assertSoftDeleted($allocation);
        $this->assertSame([100, 0], [$source->fresh()->available_stock, $destination->fresh()->available_stock]);
    }

    public function test_only_the_requesting_rhu_gets_the_supply_decision_forms(): void
    {
        $rhu = HealthUnit::create(['name' => 'Requesting RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $rhu->id]);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $rhu->id]);
        $phoAdmin = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $medicine = Medicine::create(['medicine_name' => 'Requested stock']);
        $stock = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $rhu->id, 'available_stock' => 30]);

        $this->withSession(['user_id' => $midwife->id]);
        foreach (['Outreach' => 5, 'Clinic' => 7] as $purpose => $quantity) {
            $this->post(route('supply_requests.store'), ['medicine_id' => $medicine->id,
                'quantity' => $quantity, 'purpose' => $purpose, 'urgency' => 'normal'])->assertSessionHas('success');
        }
        [$approved, $rejected] = Allocation::orderBy('id')->get();
        $approve = route('allocation.approve_supply_request', $approved->id);
        $reject = route('allocation.reject_supply_request', $rejected->id);

        $this->withSession(['user_id' => $phoAdmin->id]);
        $this->post($approve)->assertNotFound();
        $this->post($reject)->assertNotFound();
        $this->get(route('allocation.index'))->assertOk()->assertSee('Requested stock')->assertDontSee($approve)
            ->assertDontSee('title="Reject"', false);

        $this->withSession(['user_id' => $staff->id]);
        $this->get(route('allocation.index'))->assertOk()->assertSee('action="'.$approve.'" method="POST"', false)
            ->assertSee('action="'.$reject.'" method="POST"', false);
        $this->post($approve)->assertSessionHas('success');
        $this->post($reject, ['rejection_reason' => 'Use clinic stock'])->assertSessionHas('success');
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertStringContainsString('Use clinic stock', $rejected->fresh()->destination);
        $this->assertSame([25, 5], [$stock->fresh()->available_stock, $stock->fresh()->allocated_stock]);
    }

    public function test_manage_allocations_print_and_csv_filter_by_all_source_batches(): void
    {
        $rhu = HealthUnit::create(['name' => 'Allocation RHU']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $rhu->id]);
        $medicine = Medicine::create(['medicine_name' => 'Multiple batch stock']);
        $batch = ['medicine_id' => $medicine->id, 'health_unit_id' => $rhu->id, 'available_stock' => 10];
        $first = Inventory::create($batch + ['batch_no' => 'FIRST', 'expiration_date' => today()->addMonth()]);
        Inventory::create($batch + ['batch_no' => 'SECOND', 'expiration_date' => today()->addMonths(2)]);
        $legacy = Inventory::create($batch + ['batch_no' => 'LEGACY', 'expiration_date' => today()->addMonths(3)]);
        $this->withSession(['user_id' => $staff->id])->post(route('allocation.store'), [
            'inventory_id' => $first->id, 'destination' => 'Outreach', 'quantity_allocated' => 15,
            'date_allocated' => today()->toDateString(),
        ])->assertSessionHas('success');
        $allocation = Allocation::sole();
        $legacyAllocation = Allocation::create(['medicine_id' => $medicine->id, 'health_unit_id' => $rhu->id,
            'inventory_id' => $legacy->id, 'date_allocated' => today(), 'quantity_allocated' => 1, 'status' => 'completed']);

        foreach (['SECOND' => [$allocation->id], 'LEGACY' => [$legacyAllocation->id], 'UNKNOWN' => []] as $number => $expected) {
            $filters = ['batch_input' => $number];
            $print = $this->get(route('allocation.index', $filters + ['print' => '1']))->assertOk();
            $this->assertSame($expected, $print->viewData('allocations')->pluck('id')->all());
            $csv = $this->get(route('allocation.export', $filters))->assertOk()->streamedContent();
            $rows = array_map(fn ($line) => str_getcsv($line, escape: ''), preg_split('/\R/', trim($csv)));
            $this->assertSame($expected, array_map('intval', array_column(array_slice($rows, 1), 0)));
            if ($number === 'SECOND') {
                $print->assertSee('FIRST, SECOND');
                $this->assertSame('FIRST, SECOND', $rows[1][2]);
            }
        }
    }
}
