<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\AllocationConsumption;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\AllocationDispensingService;
use App\Services\ForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationDispensingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Ledger RHU', 'facility_type' => 'Rural Health Unit']);
        $admin = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'admin']);
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $medicine = Medicine::create(['medicine_name' => 'Ledger medicine', 'category' => 'medicine', 'unit' => 'tablet']);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 100, 'allocated_stock' => 0, 'batch_no' => 'LEDGER', 'expiration_date' => today()->addMonth()]);
        $this->withSession(['user_id' => $admin->id]);

        return [$unit, $admin, $midwife, $medicine, $batch];
    }

    private function allocate(Inventory $batch, User $recipient, int $quantity): Allocation
    {
        $this->post(route('allocation.store'), ['inventory_id' => $batch->id, 'recipient_user_id' => $recipient->id,
            'destination' => 'Barangay clinic', 'quantity_allocated' => $quantity, 'date_allocated' => today()->toDateString()])
            ->assertSessionHas('success');

        return Allocation::latest('id')->firstOrFail();
    }

    private function dispense(Inventory $batch, int $quantity, array $extra = [])
    {
        return $this->post(route('dispensing.store'), array_merge(['inventory_id' => $batch->id, 'stock_source' => 'allocation',
            'quantity_dispensed' => $quantity, 'date_dispensed' => today()->toDateString()], $extra));
    }

    public function test_received_allocation_consumption_edits_and_deletion_preserve_exact_stock_and_owner(): void
    {
        [$unit, $admin, $midwife, $medicine, $batch] = $this->fixture();
        $allocation = $this->allocate($batch, $midwife, 40);
        $this->assertTrue($allocation->dispensing_enabled);
        $this->assertCount(0, app(AllocationDispensingService::class)->remainingBalances([$unit->id]));
        $this->withSession(['user_id' => $midwife->id])->post(route('allocation.mark_received', $allocation->id))->assertSessionHas('success');
        $payload = ['medicine_id' => $medicine->id, 'quantity_dispensed' => 15, 'stock_source' => 'allocation',
            'date_dispensed' => today()->toDateString()];
        $preview = $this->postJson(route('dispensing.preview'), $payload)->assertOk()->assertJsonCount(1, 'lots')
            ->assertJsonPath('lots.0.inventory_id', $batch->id)->assertJsonPath('lots.0.quantity', 15);
        $this->post(route('dispensing.store'), $payload + ['lot_preview' => $preview->json('token')])->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->assertSame([60, 25], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertSame(15, (int) StockLog::demandReleases()->sum('quantity_added'));
        $item = app(ForecastService::class)->getForecast($unit->id)['items']->first();
        $this->assertSame([85, 60, 25], [$item['available_stock'], $item['shared_available_stock'], $item['recipient_available_stock']]);
        $this->get(route('dispensing.edit', $record->id))->assertOk()->assertViewHas('allocationInventories', fn ($rows) => $rows->first()->available_stock === 40);

        $this->withSession(['user_id' => $admin->id])->delete(route('allocation.destroy', $allocation->id))->assertSessionHas('error');
        // Omitted source retains the allocation workflow, even when an administrator corrects it.
        unset($payload['stock_source']);
        $payload['quantity_dispensed'] = 30;
        $preview = $this->postJson(route('dispensing.preview_edit', $record->id), $payload)->assertOk()
            ->assertJsonPath('lots.0.quantity', 30);
        $this->put(route('dispensing.update', $record->id), $payload + ['lot_preview' => $preview->json('token')])->assertSessionHas('success');
        $this->assertSame('allocation', $record->fresh()->stock_source);
        $this->assertSame($midwife->id, $record->fresh()->dispensed_by);
        $this->assertSame([60, 10], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertSame(30, (int) AllocationConsumption::whereNull('reversed_at')->sum('quantity'));
        $this->delete(route('dispensing.destroy', $record->id))->assertSessionHas('success');
        $this->assertSame([60, 40], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertSame(0, (int) StockLog::demandReleases()->sum('quantity_added'));
        $this->delete(route('allocation.destroy', $allocation->id))->assertSessionHas('success');
        $this->assertSame([100, 0], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
    }

    public function test_one_recipient_cannot_use_another_recipients_holdings_or_unreceived_supplies(): void
    {
        [$unit, $admin, $midwife, $medicine, $batch] = $this->fixture();
        $other = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $own = $this->allocate($batch, $midwife, 5);
        $unreceived = $this->allocate($batch, $midwife, 20);
        $their = $this->allocate($batch, $other, 30);
        $this->withSession(['user_id' => $other->id])->post(route('allocation.mark_received', $their->id))->assertSessionHas('success');
        $this->withSession(['user_id' => $midwife->id])->post(route('allocation.mark_received', $own->id))->assertSessionHas('success');
        $this->dispense($batch, 6)->assertSessionHas('error');
        $this->assertDatabaseCount('dispensing_records', 0);
        $this->assertSame([45, 55], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertSame(5, (int) app(AllocationDispensingService::class)->remainingBalances([$unit->id], $midwife->id)->sum('quantity'));
        $this->dispense($batch, 5)->assertSessionHas('success');
        $this->assertSame([45, 50], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
    }

    public function test_legacy_receipt_does_not_enable_inferred_balances_and_expired_batches_cannot_be_consumed(): void
    {
        [$unit, $admin, $midwife, $medicine, $batch] = $this->fixture();
        $legacy = Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'inventory_id' => $batch->id,
            'recipient_user_id' => $midwife->id, 'quantity_allocated' => 50, 'status' => 'approved', 'date_allocated' => today(), 'allocated_by' => $midwife->id]);
        $new = $this->allocate($batch, $midwife, 10);
        $this->withSession(['user_id' => $midwife->id])->post(route('allocation.mark_received', $legacy->id))->assertSessionHas('success');
        $this->assertFalse($legacy->fresh()->dispensing_enabled);
        $this->dispense($batch, 1)->assertSessionHas('error');
        $this->post(route('allocation.mark_received', $new->id))->assertSessionHas('success');
        $batch->update(['expiration_date' => today()->subDay()]);
        $this->dispense($batch, 1)->assertSessionHas('error');
        $this->assertDatabaseCount('allocation_consumptions', 0);
        $this->assertSame([90, 10], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
    }

    public function test_allocation_fefo_spans_batches_and_failed_edit_rolls_back_reversal(): void
    {
        [$unit, $admin, $midwife, $medicine, $a] = $this->fixture();
        $a->update(['available_stock' => 10]);
        $b = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 10,
            'batch_no' => 'LATER', 'expiration_date' => today()->addMonths(2)]);
        $this->get(route('allocation.create'))->assertOk()->assertDontSee('This Batch')
            ->assertSee('20 available now')->assertSee('earliest-expiring eligible batches');
        $allocation = $this->allocate($b, $midwife, 15);
        $this->assertSame($a->id, (int) $allocation->inventory_id);
        $this->get(route('allocation.edit', $allocation->id))->assertOk()->assertSee('5 available now');
        $this->withSession(['user_id' => $midwife->id])->post(route('allocation.mark_received', $allocation->id))->assertSessionHas('success');
        $this->dispense($b, 12)->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->assertSame([0, 5, 0, 3], [$a->fresh()->available_stock, $b->fresh()->available_stock, $a->fresh()->allocated_stock, $b->fresh()->allocated_stock]);
        $this->assertSame([10, 2], AllocationConsumption::whereNull('reversed_at')->orderBy('id')->pluck('quantity')->all());
        $this->put(route('dispensing.update', $record->id), ['inventory_id' => $b->id, 'quantity_dispensed' => 16,
            'date_dispensed' => today()->toDateString()])->assertSessionHas('error');
        $this->assertSame(12, $record->fresh()->quantity_dispensed);
        $this->assertSame([0, 3], [$a->fresh()->allocated_stock, $b->fresh()->allocated_stock]);
        $this->delete(route('dispensing.destroy', $record->id))->assertSessionHas('success');
        $this->assertSame([10, 5], [$a->fresh()->allocated_stock, $b->fresh()->allocated_stock]);
    }

    public function test_shared_source_remains_explicit_and_non_midwives_cannot_start_recipient_issues(): void
    {
        [$unit, $admin, $midwife, $medicine, $batch] = $this->fixture();
        $this->dispense($batch, 5)->assertSessionHasErrors('stock_source');
        $this->withSession(['user_id' => $midwife->id]);
        $this->dispense($batch, 5, ['stock_source' => 'rhu'])->assertSessionHas('success');
        $this->assertSame('rhu', DispensingRecord::firstOrFail()->stock_source);
        $this->assertSame([95, 0], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->get(route('dispensing.create'))->assertOk()->assertSee('My received supplies');
    }

    public function test_reissuing_a_terminal_allocation_to_a_recipient_enables_only_its_new_traceable_stock(): void
    {
        [$unit, $admin, $midwife, $medicine, $batch] = $this->fixture();
        $payload = ['inventory_id' => $batch->id, 'destination' => 'Clinic', 'quantity_allocated' => 10,
            'date_allocated' => today()->toDateString()];
        $this->post(route('allocation.store'), $payload)->assertSessionHas('success');
        $allocation = Allocation::firstOrFail();
        $this->assertFalse($allocation->dispensing_enabled);
        $this->put(route('allocation.update', $allocation->id), $payload + ['recipient_user_id' => $midwife->id])->assertSessionHas('success');
        $this->assertTrue($allocation->fresh()->dispensing_enabled);
        $this->withSession(['user_id' => $midwife->id])->post(route('allocation.mark_received', $allocation->id))->assertSessionHas('success');
        $this->dispense($batch, 5)->assertSessionHas('success');
        $this->assertSame([90, 5], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
    }

    public function test_backdated_dispensing_cannot_consume_supplies_allocated_later(): void
    {
        [$unit, $admin, $midwife, $medicine, $batch] = $this->fixture();
        $allocation = $this->allocate($batch, $midwife, 10);
        $this->withSession(['user_id' => $midwife->id])->post(route('allocation.mark_received', $allocation->id))->assertSessionHas('success');
        $this->dispense($batch, 5, ['date_dispensed' => today()->subDay()->toDateString()])->assertSessionHas('error');
        $this->assertSame([90, 10], [$batch->fresh()->available_stock, $batch->fresh()->allocated_stock]);
        $this->assertDatabaseCount('dispensing_records', 0);
    }
}
