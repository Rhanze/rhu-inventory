<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Inventory history RHU', 'facility_type' => 'Rural Health Unit']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'admin']);
        $medicine = Medicine::create(['medicine_name' => 'History medicine', 'category' => 'medicine', 'unit' => 'tablet']);
        $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'available_stock' => 100, 'allocated_stock' => 0, 'batch_no' => 'HISTORY', 'expiration_date' => today()->addYear()]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $medicine, $batch];
    }

    private function receipt(Inventory $batch, string $date, array $extra = []): StockLog
    {
        return StockLog::create(array_replace([
            'medicine_id' => $batch->medicine_id, 'health_unit_id' => $batch->health_unit_id,
            'inventory_id' => $batch->id, 'batch_no' => $batch->batch_no, 'quantity_added' => 100,
            'movement_type' => 'receipt', 'remarks' => 'Received historical delivery', 'date_added' => $date,
        ], $extra));
    }

    public function test_fefo_uses_receipt_fifo_for_equal_and_missing_expiry_and_ignores_adjustments(): void
    {
        [$unit, $user, $medicine, $newer] = $this->fixture();
        $older = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'OLDER', 'available_stock' => 100, 'expiration_date' => $newer->expiration_date]);
        $firstExpiry = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'FIRST-EXPIRY', 'available_stock' => 100, 'expiration_date' => today()->addMonth()]);
        $unknown = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'UNKNOWN', 'available_stock' => 100]);
        $undatedNewer = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'UNDATED-NEW', 'available_stock' => 100]);
        $undatedOlder = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'UNDATED-OLD', 'available_stock' => 100]);
        $this->receipt($newer, '2025-05-01');
        $this->receipt($older, '2024-01-01', ['inventory_id' => null, 'movement_type' => null, 'remarks' => 'Added legacy delivery']);
        $this->receipt($newer, '2020-01-01', ['movement_type' => 'adjustment_in', 'remarks' => 'Added inventory adjustment']);
        $this->receipt($newer, '2019-01-01', ['voided_at' => now()]);
        $this->receipt($undatedNewer, '2025-01-01');
        $this->receipt($undatedOlder, '2023-01-01');

        $batches = Inventory::availableFefo($unit->id, $medicine->id)->get();
        $this->assertSame([$firstExpiry->id, $older->id, $newer->id, $undatedOlder->id, $undatedNewer->id, $unknown->id], $batches->pluck('id')->all());
        $this->assertSame(600, (int) Inventory::availableFefo($unit->id, $medicine->id)->sum('available_stock'));
    }

    public function test_dispensing_and_transfer_reservation_follow_the_actual_receipt_date(): void
    {
        [$unit, $user, $medicine, $newer] = $this->fixture();
        $older = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'OLDEST-RECEIPT', 'available_stock' => 100, 'allocated_stock' => 0,
            'expiration_date' => $newer->expiration_date]);
        $this->receipt($newer, '2025-06-01');
        $this->receipt($older, '2024-01-01');
        $this->post(route('dispensing.store'), ['inventory_id' => $newer->id, 'quantity_dispensed' => 5,
            'date_dispensed' => today()->toDateString(), 'patient_name' => 'History patient'])->assertSessionHas('success');
        $this->assertSame(95, $older->fresh()->available_stock);
        $this->assertSame(100, $newer->fresh()->available_stock);
        $this->get(route('dispensing.create'))->assertOk()
            ->assertViewHas('inventories', fn ($rows) => $rows->contains('id', $older->id) && $rows->contains('id', $newer->id));
        $destination = HealthUnit::create(['name' => 'Transfer history destination']);
        $transfer = TransferRequest::create(['from_health_unit_id' => $unit->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'quantity' => 10, 'status' => 'pending', 'requested_by' => $user->id]);
        app(TransferService::class)->reserve($transfer->id, $unit->id);
        $this->assertSame($older->id, (int) $transfer->fresh()->inventory_id);
    }

    public function test_history_keeps_old_and_deleted_medicine_records_and_enforces_facility_scope(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $this->receipt($batch, '2023-03-01', ['remarks' => 'Included early history']);
        $this->receipt($batch, '2024-12-31', ['remarks' => 'Included end date', 'added_by' => $user->id]);
        $this->receipt($batch, '2025-01-01', ['remarks' => 'After historical cutoff']);
        $otherUnit = HealthUnit::create(['name' => 'Other inventory history RHU']);
        $this->receipt($batch, '2023-01-01', ['health_unit_id' => $otherUnit->id, 'remarks' => 'Outside facility secret']);
        $medicine->delete();

        $this->get(route('inventory.index', ['view' => 'history', 'date_to' => '2024-12-31', 'rhu_filter' => $otherUnit->id]))
            ->assertOk()->assertSee('Inventory history')->assertSee('Included early history')->assertSee('Included end date')
            ->assertDontSee('After historical cutoff')->assertDontSee('Outside facility secret')
            ->assertViewHas('movements', fn ($rows) => $rows->total() === 2);
        $this->get(route('inventory.index', ['view' => 'history', 'year' => 2024, 'batch_no' => 'HISTORY']))
            ->assertOk()->assertViewHas('movements', fn ($rows) => $rows->total() === 1);
        $this->get(route('inventory.index', ['view' => 'history', 'date_from' => '2025-01-01', 'date_to' => '2024-01-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_reversals_show_the_actor_reason_and_exact_deltas_without_duplicating_the_original_ledger(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $this->receipt($batch, today()->toDateString(), ['added_by' => $user->id]);
        $this->post(route('dispensing.store'), ['inventory_id' => $batch->id, 'quantity_dispensed' => 10,
            'date_dispensed' => today()->toDateString(), 'patient_name' => 'Reversal history patient'])->assertSessionHas('success');
        $record = DispensingRecord::firstOrFail();
        $this->delete(route('dispensing.destroy', $record->id))->assertSessionHas('success');
        $this->assertDatabaseHas('stock_movements', ['owner_type' => 'dispensing_records', 'owner_id' => $record->id,
            'performed_by' => $user->id, 'reason' => 'Dispensing record deleted', 'method' => 'Exact original batch reversal', 'available_delta' => 10]);

        $this->get(route('inventory.index', ['view' => 'history']))->assertOk()
            ->assertViewHas('movements', fn ($rows) => $rows->total() === 3)
            ->assertSee('Reversed or corrected')->assertSee('Exact original batch reversal');
        $this->get(route('inventory.index', ['view' => 'history', 'type' => 'reversal']))->assertOk()
            ->assertViewHas('movements', fn ($rows) => $rows->total() === 1
                && $rows->first()->available_delta === 10 && $rows->first()->actor_name === $user->name);
        $this->get(route('stock_logs.index', ['status' => 'reversed', 'medicine_id' => $medicine->id]))->assertOk()
            ->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 1 && $rows->first()->event_source === 'log')
            ->assertSee('Reversed or corrected');
        $this->get(route('stock_logs.index', ['status' => 'reversal', 'batch_no' => $batch->batch_no]))->assertOk()
            ->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 1 && $rows->first()->available_delta === 10);
        $export = $this->get(route('stock_logs.export', ['status' => 'reversal', 'batch_no' => $batch->batch_no]))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Dispensing record deleted', $export);
        $this->assertStringNotContainsString('Received historical delivery', $export);
        $this->assertSame(100, $batch->fresh()->available_stock);
    }

    public function test_unlogged_transfer_reservations_and_cancellations_remain_in_history(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $destination = HealthUnit::create(['name' => 'Cancellation destination']);
        $transfer = TransferRequest::create(['from_health_unit_id' => $unit->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'quantity' => 10, 'status' => 'pending', 'requested_by' => $user->id]);
        app(TransferService::class)->reserve($transfer->id, $unit->id);
        $this->get(route('inventory.index', ['view' => 'history', 'type' => 'reservation']))->assertOk()
            ->assertViewHas('movements', fn ($rows) => $rows->total() === 1 && $rows->first()->available_delta === -10);
        $this->withSession(['health_unit_id' => $destination->id, 'role' => 'admin']);
        app(TransferService::class)->cancel($transfer->id);
        $this->withSession(['user_id' => $user->id]);
        $this->get(route('inventory.index', ['view' => 'history']))->assertOk()
            ->assertViewHas('movements', fn ($rows) => $rows->total() === 2)
            ->assertSee('Transfer cancelled and reserved stock returned');
        $this->get(route('stock_logs.index', ['status' => 'cancelled', 'rhu_filter' => $destination->id]))->assertOk()
            ->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 2 && $rows->every(fn ($row) => $row->health_unit_id === $unit->id))
            ->assertSee('Cancellation reversal');
        $this->get(route('stock_logs.index', ['status' => 'active', 'evidence' => 1]))->assertOk()
            ->assertViewHas('stockLogs', fn ($rows) => $rows->isEmpty());
        $this->assertSame(100, $batch->fresh()->available_stock);
    }

    public function test_inventory_entry_preserves_receipt_date_and_reason_and_defaults_old_callers_to_today(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $payload = ['medicine_id' => $medicine->id, 'available_stock' => 20, 'allocated_stock' => 0,
            'batch_no' => 'IMPORTED-HISTORY', 'date_added' => '2023-04-05', 'remarks' => 'Verified opening balance'];
        $this->post(route('inventory.store'), $payload)->assertSessionHas('success');
        $entry = StockLog::where('batch_no', 'IMPORTED-HISTORY')->firstOrFail();
        $this->assertSame('2023-04-05', $entry->date_added->toDateString());
        $this->assertNotNull($entry->inventory_id);
        $this->assertStringContainsString('Verified opening balance', $entry->remarks);
        unset($payload['date_added']);
        $payload['batch_no'] = 'DEFAULT-DATE';
        $this->post(route('inventory.store'), $payload)->assertSessionHas('success');
        $this->assertSame(today()->toDateString(), StockLog::where('batch_no', 'DEFAULT-DATE')->firstOrFail()->date_added->toDateString());
    }
}
