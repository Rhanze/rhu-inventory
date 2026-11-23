<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispensingBarangayTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'midwife'): array
    {
        $unit = HealthUnit::create(['name' => 'Area RHU', 'facility_type' => 'Rural Health Unit']);
        $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Area medicine', 'unit' => 'tablet', 'is_dispensable' => true,
            'requires_prescription' => false]);
        $inventory = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 100, 'allocated_stock' => 0, 'batch_no' => 'AREA', 'expiration_date' => today()->addMonth()]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $inventory];
    }

    private function payload(Inventory $inventory, array $extra = []): array
    {
        return array_merge(['inventory_id' => $inventory->id, 'quantity_dispensed' => 10,
            'date_dispensed' => today()->toDateString()], $extra);
    }

    private function assign(User $user, string $barangay): void
    {
        MidwifeBarangay::create(['user_id' => $user->id, 'barangay' => $barangay, 'is_primary' => true]);
    }

    public function test_sole_assignment_defaults_and_dispensing_does_not_require_a_patient_name_or_case_list(): void
    {
        [$unit, $user, $inventory] = $this->fixture();
        $this->assign($user, 'San Juan');
        $this->get(route('dispensing.create'))->assertOk()->assertSee('Barangay served')
            ->assertSee('Patient or recipient name (optional)')->assertViewHas('defaultBarangay', 'San Juan');
        $this->post(route('dispensing.store'), $this->payload($inventory))->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->assertSame('San Juan', $record->barangay);
        $this->assertNull($record->patient_name);
        $this->assertSame(90, $inventory->fresh()->available_stock);
        $this->assertStringContainsString('recorded barangay dispensing in San Juan', StockLog::firstOrFail()->remarks);
        $this->assertDatabaseCount('disease_reports', 0);
        $this->get(route('dispensing.index'))->assertOk()->assertSee('San Juan');
    }

    public function test_multiple_assignments_require_an_explicit_served_area_and_reject_unassigned_areas(): void
    {
        [$unit, $user, $inventory] = $this->fixture();
        $this->assign($user, 'San Juan');
        $this->assign($user, 'San Pedro');
        $this->post(route('dispensing.store'), $this->payload($inventory))->assertSessionHasErrors('barangay');
        $this->post(route('dispensing.store'), $this->payload($inventory, ['barangay' => 'Foreign area']))
            ->assertSessionHasErrors('barangay');
        $this->assertSame(100, $inventory->fresh()->available_stock);
        $this->post(route('dispensing.store'), $this->payload($inventory, ['barangay' => 'San Pedro']))
            ->assertSessionHas('success');
        $this->assertSame('San Pedro', DispensingRecord::firstOrFail()->barangay);
    }

    public function test_unassigned_midwife_can_record_usage_without_inventing_a_barangay(): void
    {
        [$unit, $user, $inventory] = $this->fixture();
        $this->get(route('dispensing.create'))->assertOk()->assertSee('Ask your RHU administrator');
        $this->post(route('dispensing.store'), $this->payload($inventory))->assertSessionHas('success');
        $this->assertNull(DispensingRecord::firstOrFail()->barangay);
        $this->get(route('dispensing.index'))->assertOk()->assertSee('Not recorded');
    }

    public function test_staff_can_select_only_known_same_rhu_barangays(): void
    {
        [$unit, $staff, $inventory] = $this->fixture('staff');
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->assign($midwife, 'Local area');
        $other = HealthUnit::create(['name' => 'Other area RHU']);
        $otherMidwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $other->id]);
        $this->assign($otherMidwife, 'Foreign area');
        $this->get(route('dispensing.create'))->assertOk()->assertViewHas('barangayOptions', ['Local area']);
        $this->post(route('dispensing.store'), $this->payload($inventory, ['barangay' => 'Foreign area']))
            ->assertSessionHasErrors('barangay');
        $this->post(route('dispensing.store'), $this->payload($inventory, ['barangay' => 'Local area']))
            ->assertSessionHas('success');
        $this->assertSame('Local area', DispensingRecord::firstOrFail()->barangay);
    }

    public function test_reassignment_does_not_retag_historical_dispensing_on_edit_or_legacy_api_updates(): void
    {
        [$unit, $user, $inventory] = $this->fixture();
        $this->assign($user, 'Original area');
        $this->post(route('dispensing.store'), $this->payload($inventory))->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        MidwifeBarangay::where('user_id', $user->id)->update(['barangay' => 'New area']);
        $this->get(route('dispensing.edit', $record->id))->assertOk()->assertSee('Original area (recorded area)');
        $this->put(route('dispensing.update', $record->id), $this->payload($inventory))->assertSessionHas('success');
        $this->assertSame('Original area', $record->fresh()->barangay);
        $this->put(route('dispensing.update', $record->id), $this->payload($inventory, ['barangay' => 'Original area']))
            ->assertSessionHasErrors('barangay');
        $this->assertSame('Original area', $record->fresh()->barangay);
        $this->put(route('dispensing.update', $record->id), $this->payload($inventory, ['barangay' => 'Foreign area']))
            ->assertSessionHasErrors('barangay');
        $this->assertSame('Original area', $record->fresh()->barangay);
        $this->assertSame(90, $inventory->fresh()->available_stock);
    }

    public function test_legacy_untagged_records_remain_unknown_when_area_is_omitted(): void
    {
        [$unit, $user, $inventory] = $this->fixture();
        $this->post(route('dispensing.store'), $this->payload($inventory))->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->assign($user, 'Newly assigned');
        $this->put(route('dispensing.update', $record->id), $this->payload($inventory))->assertSessionHas('success');
        $this->assertNull($record->fresh()->barangay);
        $migration = require database_path('migrations/2026_11_06_000000_add_barangay_to_dispensing_records.php');
        $migration->up();
        $this->assertNull($record->fresh()->barangay);
    }
}
