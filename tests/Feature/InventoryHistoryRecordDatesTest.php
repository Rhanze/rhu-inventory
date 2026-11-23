<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\InventoryHistoryService;
use App\Support\HistoricalDateRange;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryHistoryRecordDatesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Dated records RHU', 'facility_type' => 'RHU']);
        $actor = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Dated history medicine', 'dosage' => '500 mg', 'unit' => 'tablet']);
        $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'DATED-BATCH', 'available_stock' => 100, 'allocated_stock' => 0]);
        $this->withSession(['user_id' => $actor->id, 'role' => 'admin', 'health_unit_id' => $unit->id]);

        return [$unit, $actor, $medicine, $batch];
    }

    private function records(array $filters = [])
    {
        $request = Request::create('/inventory', 'GET', $filters);

        return app(InventoryHistoryService::class)->records($request, HistoricalDateRange::fromRequest($request));
    }

    public function test_backdated_allocation_without_a_log_uses_its_allocation_date_and_keeps_the_posting_date(): void
    {
        [$unit, $actor, $medicine, $batch] = $this->fixture();
        $allocation = Allocation::create(['medicine_id' => $medicine->id, 'inventory_id' => $batch->id,
            'health_unit_id' => $unit->id, 'destination' => 'Historical program', 'quantity_allocated' => 10,
            'date_allocated' => '2024-02-03', 'allocated_by' => $actor->id, 'status' => 'received']);
        DB::table('stock_movements')->insert(['owner_type' => 'allocations', 'owner_id' => $allocation->id,
            'inventory_id' => $batch->id, 'available_delta' => -10, 'allocated_delta' => 0,
            'created_at' => '2026-11-17 09:00:00', 'performed_by' => $actor->id]);

        $history = $this->records(['date_from' => '2024-02-03', 'date_to' => '2024-02-03', 'action' => 'allocation']);
        $this->assertSame(1, $history->total());
        $this->assertSame('2024-02-03', Carbon::parse($history->first()->stock_effective_date)->toDateString());
        $this->assertSame('2024-02-03', Carbon::parse($history->first()->allocation_date)->toDateString());
        $this->assertSame('2026-11-17 09:00:00', $history->first()->recorded_at);
        $this->assertSame('Allocation', $history->first()->event_label);
        $this->assertSame(0, $this->records(['date_from' => '2026-11-17', 'date_to' => '2026-11-17'])->total());
    }

    public function test_receipt_ledger_owner_is_not_duplicated_and_receipt_date_is_preserved(): void
    {
        [$unit, $actor, $medicine, $batch] = $this->fixture();
        $receipt = StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'inventory_id' => $batch->id, 'quantity_added' => 100, 'batch_no' => $batch->batch_no,
            'movement_type' => 'receipt', 'date_added' => '2024-01-02', 'added_by' => $actor->id]);
        DB::table('stock_movements')->insert(['owner_type' => 'stock_logs', 'owner_id' => $receipt->id,
            'inventory_id' => $batch->id, 'available_delta' => 100, 'allocated_delta' => 0,
            'created_at' => '2026-11-17 09:00:00', 'performed_by' => $actor->id, 'method' => 'Exact receipt batch']);

        $history = $this->records();
        $this->assertSame(1, $history->total());
        $this->assertSame(100, $history->first()->quantity_added);
        $this->assertSame('2024-01-02', Carbon::parse($history->first()->stock_effective_date)->toDateString());
        $this->assertSame('2026-11-17 09:00:00', $history->first()->recorded_at);
        $this->assertSame('Exact receipt batch', $history->first()->method);
        $this->assertSame(1, $this->records(['date_to' => '2024-12-31'])->total());
    }

    public function test_dispensing_correction_has_its_own_date_and_does_not_move_the_original_log_date(): void
    {
        [$unit, $actor, $medicine, $batch] = $this->fixture();
        $record = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'patient_name' => 'Dated fixture', 'quantity_dispensed' => 5, 'date_dispensed' => '2024-03-04',
            'dispensed_by' => $actor->id, 'stock_source' => 'inventory']);
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'inventory_id' => $batch->id, 'quantity_added' => 5, 'batch_no' => $batch->batch_no,
            'movement_type' => 'dispensing', 'date_added' => '2024-03-04', 'dispensing_record_id' => $record->id,
            'voided_at' => '2026-11-17 10:00:00']);
        $original = DB::table('stock_movements')->insertGetId(['owner_type' => 'dispensing_records', 'owner_id' => $record->id,
            'inventory_id' => $batch->id, 'available_delta' => -5, 'allocated_delta' => 0,
            'created_at' => '2026-11-16 09:00:00', 'reversed_at' => '2026-11-17 10:00:00']);
        DB::table('stock_movements')->insert(['owner_type' => 'dispensing_records', 'owner_id' => $record->id,
            'inventory_id' => $batch->id, 'available_delta' => 5, 'allocated_delta' => 0,
            'created_at' => '2026-11-17 10:00:00', 'reverses_id' => $original]);
        $record->update(['date_dispensed' => '2025-01-01']);

        $old = $this->records(['date_from' => '2024-03-04', 'date_to' => '2024-03-04']);
        $this->assertSame(1, $old->total());
        $this->assertSame('dispensing', $old->first()->event_type);
        $this->assertSame('2024-03-04', Carbon::parse($old->first()->stock_effective_date)->toDateString());
        $correction = $this->records(['date_from' => '2026-11-17', 'date_to' => '2026-11-17']);
        $this->assertSame(1, $correction->total());
        $this->assertSame('reversal', $correction->first()->event_type);
        $this->assertSame(2, $this->records()->total());
    }

    public function test_searches_find_batches_strength_facilities_and_references_without_crossing_facility_scope(): void
    {
        [$unit, $actor, $medicine, $batch] = $this->fixture();
        $allocation = Allocation::create(['medicine_id' => $medicine->id, 'inventory_id' => $batch->id,
            'health_unit_id' => $unit->id, 'destination' => 'Historical program', 'quantity_allocated' => 10,
            'date_allocated' => '2024-02-03', 'allocated_by' => $actor->id, 'status' => 'received']);
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'inventory_id' => $batch->id, 'quantity_added' => 10, 'batch_no' => $batch->batch_no,
            'movement_type' => 'allocation', 'date_added' => '2024-02-03', 'allocation_id' => $allocation->id]);
        $otherUnit = HealthUnit::create(['name' => 'Other restricted facility']);
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $otherUnit->id,
            'quantity_added' => 9, 'batch_no' => 'DATED-BATCH', 'movement_type' => 'receipt', 'date_added' => '2024-02-03']);

        foreach (['DATED-BATCH', '500 mg', 'Dated records RHU', 'Historical program', 'Allocation #'.$allocation->id] as $search) {
            $history = $this->records(['search' => $search, 'medicine_id' => $medicine->id]);
            $this->assertSame(1, $history->total(), $search);
            $this->assertSame($unit->id, $history->first()->health_unit_id);
        }
        $this->assertSame(1, $this->records(['action' => 'allocation'])->total());
        $this->assertSame(0, $this->records(['action' => 'dispensing'])->total());
        $this->assertSame(0, $this->records(['search' => 'Other restricted facility', 'rhu_filter' => (string) $otherUnit->id])->total());
    }

    public function test_transfer_reservations_keep_the_reservation_label_instead_of_the_owner_type(): void
    {
        [$unit, $actor, $medicine, $batch] = $this->fixture();
        $destination = HealthUnit::create(['name' => 'Reservation receiving RHU']);
        $transfer = TransferRequest::create(['from_health_unit_id' => $unit->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'quantity' => 10, 'status' => 'accepted', 'requested_by' => $actor->id]);
        DB::table('stock_movements')->insert(['owner_type' => 'transfer_requests', 'owner_id' => $transfer->id,
            'inventory_id' => $batch->id, 'available_delta' => -10, 'allocated_delta' => 10,
            'created_at' => '2026-11-17 09:00:00', 'performed_by' => $actor->id]);

        $history = $this->records(['action' => 'reservation']);
        $this->assertSame(1, $history->total());
        $this->assertSame('reservation', $history->first()->event_type);
        $this->assertSame('Reservation', $history->first()->event_label);
        $this->assertSame(0, $this->records(['action' => 'transfer'])->total());
    }
}
