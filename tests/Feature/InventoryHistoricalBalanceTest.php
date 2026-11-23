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
use App\Services\InventoryHistoricalBalanceService;
use App\Support\HistoricalDateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryHistoricalBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 17)->startOfDay());
    }

    private function fixture(array $batchValues = []): array
    {
        $unit = HealthUnit::create(['name' => 'Recorded stock RHU', 'facility_type' => 'Rural Health Unit']);
        $user = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Recorded tablets', 'unit' => 'tablet', 'dosage' => '500mg']);
        $batch = Inventory::create(array_replace(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'batch_no' => 'FIRST', 'available_stock' => 100, 'allocated_stock' => 0, 'expiration_date' => '2027-06-30'], $batchValues));
        session(['user_id' => $user->id, 'health_unit_id' => $unit->id, 'role' => 'admin']);

        return [$unit, $user, $medicine, $batch];
    }

    private function log(Inventory $batch, string $date, int $quantity, string $type = 'receipt', array $extra = []): StockLog
    {
        return StockLog::create(array_replace(['medicine_id' => $batch->medicine_id, 'health_unit_id' => $batch->health_unit_id,
            'inventory_id' => $batch->id, 'batch_no' => $batch->batch_no, 'quantity_added' => $quantity,
            'movement_type' => $type, 'date_added' => $date], $extra));
    }

    private function movement(object $owner, Inventory $batch, int $available, int $reserved = 0, array $extra = []): int
    {
        return DB::table('stock_movements')->insertGetId(array_replace(['owner_type' => $owner->getTable(), 'owner_id' => $owner->id,
            'inventory_id' => $batch->id, 'available_delta' => $available, 'allocated_delta' => $reserved,
            'created_at' => now()], $extra));
    }

    private function summary(array $filters = []): array
    {
        $request = Request::create('/inventory', 'GET', $filters);
        $request->setLaravelSession(app('session.store'));

        return app(InventoryHistoricalBalanceService::class)->summarize($request, HistoricalDateRange::fromRequest($request));
    }

    public function test_business_dates_reconstruct_month_end_without_double_counting_logs_or_personal_dispensing(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture(['available_stock' => 70, 'allocated_stock' => 15]);
        $receipt = $this->log($batch, '2026-09-01', 100);
        $this->movement($receipt, $batch, 100);
        $allocation = Allocation::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'inventory_id' => $batch->id, 'quantity_allocated' => 20, 'date_allocated' => '2026-10-05',
            'allocated_by' => $user->id, 'destination' => 'Local clinic', 'status' => 'completed']);
        $this->log($batch, '2026-10-05', 20, 'allocation', ['allocation_id' => $allocation->id]);
        $this->movement($allocation, $batch, -20, 20);
        $shared = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'quantity_dispensed' => 10, 'date_dispensed' => '2026-10-15', 'dispensed_by' => $user->id, 'stock_source' => 'rhu']);
        $this->log($batch, '2026-10-15', 10, 'dispensing', ['dispensing_record_id' => $shared->id]);
        $this->movement($shared, $batch, -10);
        $personal = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'quantity_dispensed' => 5, 'date_dispensed' => '2026-11-02', 'dispensed_by' => $user->id, 'stock_source' => 'allocation']);
        $this->log($batch, '2026-11-02', 5, 'dispensing', ['dispensing_record_id' => $personal->id]);
        $this->movement($personal, $batch, 0, -5);

        $row = $this->summary(['date_from' => '2026-10-01', 'date_to' => '2026-10-31'])['rows']->sole();
        $this->assertTrue($row->balance_complete);
        $this->assertSame(70, $row->closing_available);
        $this->assertSame(20, $row->closing_reserved);
        $this->assertSame(10, $row->dispensed_units);
        $this->assertSame(1, $row->dispensing_records);
        $this->assertSame(20, $row->allocated_units);
        $this->assertSame(1, $row->allocation_records);
        $row = $this->summary(['date_from' => '2026-11-02', 'date_to' => '2026-11-02'])['rows']->sole();
        $this->assertSame(70, $row->closing_available);
        $this->assertSame(15, $row->closing_reserved);
        $this->assertSame(5, $row->dispensed_units);
        $this->assertSame(1, $row->dispensing_records);
    }

    public function test_split_batches_count_one_transaction_and_batch_filter_counts_only_that_batch_quantity(): void
    {
        [$unit, $user, $medicine, $first] = $this->fixture(['available_stock' => 93]);
        $second = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => 'SECOND', 'available_stock' => 47, 'allocated_stock' => 0]);
        $this->log($first, '2026-09-01', 100);
        $this->log($second, '2026-09-01', 50);
        $record = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'quantity_dispensed' => 10, 'date_dispensed' => '2026-10-10', 'dispensed_by' => $user->id,
            'stock_source' => 'rhu', 'batch_no' => 'FIRST']);
        foreach ([[$first, 7], [$second, 3]] as [$batch, $quantity]) {
            $this->log($batch, '2026-10-10', $quantity, 'dispensing', ['dispensing_record_id' => $record->id]);
            $this->movement($record, $batch, -$quantity);
        }
        $all = $this->summary(['date_to' => '2026-10-31'])['rows']->sole();
        $this->assertSame(140, $all->closing_available);
        $this->assertSame(10, $all->dispensed_units);
        $this->assertSame(1, $all->dispensing_records);
        $filtered = $this->summary(['date_to' => '2026-10-31', 'batch_no' => 'SECOND'])['rows']->sole();
        $this->assertSame(47, $filtered->closing_available);
        $this->assertSame(3, $filtered->dispensed_units);
        $this->assertSame(1, $filtered->dispensing_records);
        StockLog::where('dispensing_record_id', $record->id)->delete();
        $withoutLogs = $this->summary(['date_to' => '2026-10-31', 'batch_no' => 'SECOND'])['rows']->sole();
        $this->assertTrue($withoutLogs->balance_complete);
        $this->assertSame(47, $withoutLogs->closing_available);
        $this->assertSame(3, $withoutLogs->dispensed_units);
        $this->assertSame(1, $withoutLogs->dispensing_records);
    }

    public function test_missing_openings_and_ambiguous_batches_are_unknown_and_search_does_not_change_balances(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $row = $this->summary(['date_to' => '2026-10-31'])['rows']->sole();
        $this->assertFalse($row->balance_complete);
        $this->assertNull($row->closing_available);
        $this->assertNull($row->closing_reserved);
        $this->log($batch, '2026-09-01', 100);
        $row = $this->summary(['date_to' => '2026-10-31', 'search' => 'does not exist', 'type' => 'reversal'])['rows']->sole();
        $this->assertTrue($row->balance_complete);
        $this->assertSame(100, $row->closing_available);
        $this->assertFalse($this->summary(['date_to' => '2026-08-31'])['rows']->sole()->balance_complete);
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'batch_no' => $batch->batch_no, 'available_stock' => 100, 'allocated_stock' => 0]);
        $this->log($batch, '2026-09-02', 100, 'receipt', ['inventory_id' => null]);
        $row = $this->summary(['date_to' => '2026-10-31'])['rows']->sole();
        $this->assertFalse($row->balance_complete);
        $this->assertNull($row->closing_available);
    }

    public function test_corrected_records_expiry_and_deleted_batches_keep_reconciled_current_effective_history(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture(['expiration_date' => '2026-10-31']);
        $this->log($batch, '2026-09-01', 100);
        $record = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'quantity_dispensed' => 10, 'date_dispensed' => '2026-10-10', 'dispensed_by' => $user->id]);
        $log = $this->log($batch, '2026-10-10', 10, 'dispensing', ['dispensing_record_id' => $record->id]);
        $original = $this->movement($record, $batch, -10, 0, ['reversed_at' => now()]);
        $this->movement($record, $batch, 10, 0, ['reverses_id' => $original]);
        $log->update(['voided_at' => now()]);
        $record->delete();
        $row = $this->summary(['date_to' => '2026-10-31'])['rows']->sole();
        $this->assertTrue($row->balance_complete);
        $this->assertSame(100, $row->closing_available);
        $this->assertSame(0, $row->dispensed_units);
        $this->assertSame(0, $this->summary()['rows']->sole()->closing_available);
        $batch->delete();
        $this->log($batch, '2026-11-03', 100, 'adjustment_out');
        $this->assertSame(100, $this->summary(['date_to' => '2026-10-31'])['rows']->sole()->closing_available);
        $this->assertTrue($this->summary()['rows']->sole()->balance_complete);
    }

    public function test_facility_medicine_actor_filters_and_current_year_cutoff_are_enforced(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $this->log($batch, '2026-09-01', 100);
        $outside = HealthUnit::create(['name' => 'Outside RHU']);
        $otherMedicine = Medicine::create(['medicine_name' => 'Outside vials', 'unit' => 'vial']);
        $otherBatch = Inventory::create(['medicine_id' => $otherMedicine->id, 'health_unit_id' => $outside->id,
            'batch_no' => 'OUTSIDE', 'available_stock' => 50, 'allocated_stock' => 0]);
        $this->log($otherBatch, '2026-09-01', 50);
        $scoped = $this->summary(['year' => 2026, 'rhu_filter' => (string) $outside->id]);
        $this->assertSame('2026-11-17', $scoped['as_of']);
        $this->assertSame($medicine->id, $scoped['rows']->sole()->medicine_id);
        $this->assertCount(0, $this->summary(['medicine_id' => $otherMedicine->id])['rows']);
        session(['role' => 'admin', 'health_unit_id' => null]);
        $this->assertSame($otherMedicine->id, $this->summary(['rhu_filter' => (string) $outside->id])['rows']->sole()->medicine_id);
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        foreach ([[$user->id, 2], [$midwife->id, 3]] as [$actorId, $quantity]) {
            DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'quantity_dispensed' => $quantity, 'date_dispensed' => '2026-10-10', 'dispensed_by' => $actorId]);
        }
        session(['role' => 'midwife', 'user_id' => $midwife->id, 'health_unit_id' => $unit->id]);
        $this->assertSame(3, $this->summary(['date_from' => '2026-10-10', 'date_to' => '2026-10-10'])['rows']->sole()->dispensed_units);
    }

    public function test_missing_one_facility_balance_prevents_partial_province_totals_and_pending_allocations_do_not_count(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $this->log($batch, '2026-09-01', 100);
        $outside = HealthUnit::create(['name' => 'Incomplete stock RHU']);
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $outside->id,
            'batch_no' => 'NO-OPENING', 'available_stock' => 50, 'allocated_stock' => 0]);
        foreach (['pending', 'rejected'] as $status) {
            Allocation::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'quantity_allocated' => 25, 'date_allocated' => '2026-10-10', 'allocated_by' => $user->id,
                'destination' => 'Unfulfilled request', 'status' => $status]);
        }
        session(['role' => 'admin', 'health_unit_id' => null]);
        $province = $this->summary(['date_to' => '2026-10-31'])['rows']->sole();
        $this->assertFalse($province->balance_complete);
        $this->assertNull($province->closing_available);
        $this->assertSame(0, $province->allocated_units);
        $this->assertSame(0, $province->allocation_records);
        $facilities = collect($province->facilities)->keyBy('health_unit_id');
        $this->assertTrue($facilities[$unit->id]['balance_complete']);
        $this->assertSame(100, $facilities[$unit->id]['closing_available']);
        $this->assertFalse($facilities[$outside->id]['balance_complete']);
        $this->assertNull($facilities[$outside->id]['closing_available']);
        $facility = $this->summary(['date_to' => '2026-10-31', 'rhu_filter' => (string) $unit->id])['rows']->sole();
        $this->assertTrue($facility->balance_complete);
        $this->assertSame(100, $facility->closing_available);
    }

    public function test_stale_unvoided_logs_do_not_restore_fully_reversed_or_cancelled_activity_to_corrected_history(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $this->log($batch, '2026-09-01', 100);
        $dispensing = DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'quantity_dispensed' => 10, 'date_dispensed' => '2026-10-10', 'dispensed_by' => $user->id]);
        $allocation = Allocation::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'inventory_id' => $batch->id, 'quantity_allocated' => 20, 'date_allocated' => '2026-10-11',
            'allocated_by' => $user->id, 'destination' => 'Reversed program supply', 'status' => 'completed']);
        foreach ([[$dispensing, 10, 'dispensing', 'dispensing_record_id'], [$allocation, 20, 'allocation', 'allocation_id']] as [$record, $quantity, $type, $key]) {
            $this->log($batch, '2026-10-10', $quantity, $type, [$key => $record->id]);
            $original = $this->movement($record, $batch, -$quantity, 0, ['reversed_at' => now()]);
            $this->movement($record, $batch, $quantity, 0, ['reverses_id' => $original]);
        }
        $destination = HealthUnit::create(['name' => 'Cancelled history destination']);
        $transfer = TransferRequest::create(['from_health_unit_id' => $unit->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'quantity' => 5, 'status' => 'cancelled', 'requested_by' => $user->id]);
        $this->log($batch, '2026-10-12', 5, 'transfer_out', ['transfer_id' => $transfer->id]);

        $row = $this->summary(['date_from' => '2026-10-01', 'date_to' => '2026-10-31'])['rows']->sole();
        $this->assertTrue($row->balance_complete);
        $this->assertSame(100, $row->closing_available);
        $this->assertSame(0, $row->dispensed_units);
        $this->assertSame(0, $row->allocated_units);
        $this->assertSame(0, $row->dispensing_records);
        $this->assertSame(0, $row->allocation_records);
        $response = $this->get(route('inventory.index', ['view' => 'history',
            'date_from' => '2026-10-01', 'date_to' => '2026-10-31']))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = new \DOMXPath($document);
        foreach (['0 allocated. View allocation movements', '0 dispensed. View dispensing movements'] as $label) {
            $url = $links->evaluate('string(//a[@aria-label="'.$label.' for Recorded tablets."]/@href)');
            $this->assertNotSame('', $url);
            $this->get($url)->assertOk()->assertViewHas('stockLogs', fn ($logs) => $logs->total() === 0);
        }
    }
}
