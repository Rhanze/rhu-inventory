<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Database\Seeders\SampleStockHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NormalizeGeneratedStockTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryStorage = base_path('tmp/normalize-generated-stock-test-'.bin2hex(random_bytes(6)));
        $this->app->useStoragePath($this->temporaryStorage);
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->temporaryStorage, base_path('tmp/normalize-generated-stock-test-'))) {
            File::deleteDirectory($this->temporaryStorage);
        }
        parent::tearDown();
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Normalization RHU', 'facility_type' => 'Rural Health Unit']);
        $staff = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff', 'is_active' => true]);
        foreach ([false, true] as $index => $prescription) {
            Medicine::create(['medicine_name' => 'Normalization medicine '.($index + 1), 'unit' => 'tablet',
                'category' => 'medicine', 'is_dispensable' => true, 'requires_prescription' => $prescription]);
        }
        $this->seed(SampleStockHistorySeeder::class);
        $ordinary = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => Medicine::first()->id,
            'batch_no' => 'SUP-2026-99', 'storage_location' => 'Main pharmacy', 'available_stock' => 88, 'allocated_stock' => 4]);

        return [$unit, $staff, $ordinary];
    }

    private function rows(string $table): array
    {
        return DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }

    public function test_preview_does_not_change_rows_or_create_a_provenance_backup(): void
    {
        $this->fixture();
        $inventory = $this->rows('inventory');
        $dispensing = $this->rows('dispensing_records');
        $this->artisan('data:normalize-generated-stock')->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertSame($inventory, $this->rows('inventory'));
        $this->assertSame($dispensing, $this->rows('dispensing_records'));
        $this->assertDirectoryDoesNotExist(storage_path('app/private/data-provenance'));
    }

    public function test_apply_renames_consistent_references_preserves_user_edits_and_records_private_origin(): void
    {
        [$unit, $staff, $ordinary] = $this->fixture();
        $batch = Inventory::where('batch_no', 'like', 'DEMO-V2-%')->firstOrFail();
        $record = DispensingRecord::where('batch_no', $batch->batch_no)->firstOrFail();
        $record->update(['patient_name' => 'Recipient entered by staff', 'doctor_name' => 'Clinician entered by staff']);
        $manual = DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $batch->medicine_id,
            'batch_no' => $batch->batch_no, 'quantity_dispensed' => 2, 'date_dispensed' => today(), 'dispensed_by' => $staff->id,
            'patient_name' => '[DEMO] Visitor '.$unit->id.'-99-99', 'doctor_name' => '[DEMO] Clinician 1']);
        $editedLog = StockLog::where('inventory_id', $batch->id)->where('movement_type', 'dispensing')->firstOrFail();
        $editedLog->update(['remarks' => 'Released - Dispensed: verified by staff', 'storage_location' => 'Custom shelf']);
        $legacyLog = StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $batch->medicine_id,
            'inventory_id' => null, 'batch_no' => $batch->batch_no, 'quantity_added' => 2, 'movement_type' => 'dispensing',
            'date_added' => today(), 'added_by' => $staff->id, 'remarks' => 'Released - Dispensed: manual follow-up']);
        $allocationId = DB::table('allocations')->insertGetId(['health_unit_id' => $unit->id, 'medicine_id' => $batch->medicine_id,
            'inventory_id' => $batch->id, 'allocated_by' => $staff->id,
            'quantity_allocated' => 2, 'date_allocated' => today(), 'status' => 'pending']);
        $transferId = DB::table('transfer_requests')->insertGetId(['from_health_unit_id' => $unit->id, 'to_health_unit_id' => $unit->id,
            'medicine_id' => $batch->medicine_id, 'inventory_id' => $batch->id, 'batch_no' => $batch->batch_no,
            'quantity' => 2, 'status' => 'pending', 'requested_by' => $staff->id]);
        $before = [];
        foreach (['inventory', 'dispensing_records', 'stock_logs', 'stock_movements', 'allocations', 'transfer_requests'] as $table) $before[$table] = $this->rows($table);
        $ordinaryBefore = $ordinary->fresh()->getAttributes();

        $this->artisan('data:normalize-generated-stock', ['--apply' => true])->assertSuccessful();
        $newBatch = $batch->fresh()->batch_no;
        $this->assertSame('ATQ-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT).'-'.$batch->medicine_id, $newBatch);
        $this->assertSame($newBatch, $record->fresh()->batch_no);
        $this->assertSame($newBatch, $manual->fresh()->batch_no);
        $this->assertSame($newBatch, $legacyLog->fresh()->batch_no);
        $this->assertSame('Released - Dispensed: manual follow-up', $legacyLog->fresh()->remarks);
        $this->assertSame($batch->id, DB::table('allocations')->where('id', $allocationId)->value('inventory_id'));
        $this->assertSame($newBatch, DB::table('transfer_requests')->where('id', $transferId)->value('batch_no'));
        $this->assertSame('Recipient entered by staff', $record->fresh()->patient_name);
        $this->assertSame('Clinician entered by staff', $record->fresh()->doctor_name);
        $this->assertSame('[DEMO] Visitor '.$unit->id.'-99-99', $manual->fresh()->patient_name);
        $this->assertSame('[DEMO] Clinician 1', $manual->fresh()->doctor_name);
        $this->assertSame('Released - Dispensed: verified by staff', $editedLog->fresh()->remarks);
        $this->assertSame('Custom shelf', $editedLog->fresh()->storage_location);
        $this->assertSame($ordinaryBefore, $ordinary->fresh()->getAttributes());
        $this->assertSame($before['stock_movements'], $this->rows('stock_movements'));
        foreach (['inventory' => ['available_stock', 'allocated_stock', 'expiration_date'],
            'dispensing_records' => ['quantity_dispensed', 'date_dispensed', 'dispensed_by'],
            'stock_logs' => ['quantity_added', 'date_added', 'voided_at'],
            'allocations' => ['quantity_allocated', 'date_allocated'], 'transfer_requests' => ['quantity', 'status']] as $table => $fields) {
            foreach ($before[$table] as $original) {
                $current = (array) DB::table($table)->where('id', $original['id'])->first();
                foreach ($fields as $field) $this->assertSame($original[$field], $current[$field], $table.'.'.$field);
            }
        }
        $backups = glob(storage_path('app/private/data-provenance/generated-stock-normalization-*.json'));
        $this->assertCount(1, $backups);
        $backup = json_decode(file_get_contents($backups[0]), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('synthetic', $backup['record_origin']);
        $this->assertSame('SampleStockHistorySeeder', $backup['generator']);
        $this->assertCount(2, $backup['batches']);
        $this->assertSame($batch->batch_no, $backup['original_rows']['inventory'][$batch->id]['batch_no']);
        $after = $this->rows('dispensing_records');
        $this->artisan('data:normalize-generated-stock', ['--apply' => true])->expectsOutputToContain('0 verified generated batches')->assertSuccessful();
        $this->assertSame($after, $this->rows('dispensing_records'));
        $this->assertCount(1, glob(storage_path('app/private/data-provenance/generated-stock-normalization-*.json')));
    }

    public function test_unverified_batches_and_collisions_are_rejected_without_any_changes(): void
    {
        [$unit] = $this->fixture();
        $batch = Inventory::where('batch_no', 'like', 'DEMO-V2-%')->firstOrFail();
        $collision = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $batch->medicine_id,
            'batch_no' => 'ATQ-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT).'-'.$batch->medicine_id,
            'available_stock' => 10, 'allocated_stock' => 0]);
        $before = $this->rows('inventory');
        $this->artisan('data:normalize-generated-stock', ['--apply' => true])->expectsOutputToContain('already in use')->assertFailed();
        $this->assertSame($before, $this->rows('inventory'));
        $collision->update(['batch_no' => 'SUP-2026-COLLISION']);
        StockLog::where('inventory_id', $batch->id)->update(['remarks' => 'Entered separately']);
        $this->artisan('data:normalize-generated-stock', ['--apply' => true])->expectsOutputToContain('ownership could not be verified')->assertFailed();
        $this->assertSame($batch->batch_no, $batch->fresh()->batch_no);
        $this->assertSame([], glob(storage_path('app/private/data-provenance/generated-stock-normalization-*.json')));
    }

    public function test_production_and_remote_databases_are_rejected_before_any_queries(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('data:normalize-generated-stock', ['--apply' => true])->expectsOutputToContain('restricted to local/testing')->assertFailed();
        $this->app->detectEnvironment(fn () => 'testing');
        $original = config('database.default');
        try {
            config(['database.default' => 'normalization_guard', 'database.connections.normalization_guard' => [
                'driver' => 'mysql', 'host' => '127.0.0.1', 'url' => 'mysql://user:password@remote.invalid/database',
            ]]);
            $this->artisan('data:normalize-generated-stock', ['--apply' => true])->expectsOutputToContain('Only a local')->assertFailed();
            DB::purge('normalization_guard');
            config(['database.connections.normalization_guard.url' => 'mysql://user:password@127.0.0.1/database?host=remote.invalid']);
            $this->artisan('data:normalize-generated-stock', ['--apply' => true])->expectsOutputToContain('Only a local')->assertFailed();
        } finally {
            DB::purge('normalization_guard');
            config(['database.default' => $original]);
        }
    }
}
