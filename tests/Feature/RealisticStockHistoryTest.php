<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\StockLog;
use App\Models\User;
use App\Services\DiseaseConsumptionTrendService;
use Carbon\Carbon;
use Database\Seeders\RealisticStockHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealisticStockHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Manila'));
        Storage::fake('local');
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Hamtic Rural Health Unit', 'facility_type' => 'Rural Health Unit']);
        $staff = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'admin', 'is_active' => true]);
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife', 'is_active' => true]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Buhang', 'is_primary' => true]);
        $other = HealthUnit::create(['name' => 'Pandan Rural Health Unit', 'facility_type' => 'RHU']);
        User::factory()->create(['health_unit_id' => $other->id, 'role' => 'staff', 'is_active' => true]);
        $condition = Disease::create(['code' => 'HISTORY-CONDITION', 'name' => 'Consumption-linked condition']);
        $medicines = collect();
        foreach ([['Paracetamol', '500 mg', 'Tablet', 'Tablet'], ['Oral Rehydration Salts', '20.5 g', 'Powder sachet', 'Sachet'],
            ['Amlodipine', '5 mg', 'Tablet', 'Tablet'], ['Cetirizine', '10 mg', 'Tablet', 'Tablet']] as [$name, $strength, $form, $unitName]) {
            $medicine = Medicine::create(['medicine_name' => $name, 'dosage' => $strength, 'dosage_form' => $form,
                'unit' => $unitName, 'category' => 'medicine', 'is_dispensable' => true]);
            DiseaseMedicineMap::create(['disease_id' => $condition->id, 'medicine_id' => $medicine->id]);
            $medicines->push($medicine);
        }
        return [$unit, $staff, $midwife, $other, $medicines];
    }

    private function manifest(): array
    {
        $path = collect(Storage::disk('local')->files('generated-data'))->first(fn ($file) => str_ends_with($file, '.json'));
        $this->assertNotNull($path);
        return json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_generated_history_has_normal_display_values_exact_ledgers_and_current_consumption_signals(): void
    {
        [$unit, , $midwife, $other, $medicines] = $this->fixture();
        $vaccine = Medicine::create(['medicine_name' => 'Hepatitis B Vaccine', 'dosage' => '0.5 mL', 'dosage_form' => 'Injection',
            'unit' => 'Vial', 'category' => 'vaccine', 'is_dispensable' => true]);
        $incomplete = Medicine::create(['medicine_name' => 'Incomplete catalog entry', 'is_dispensable' => true]);
        $unrelated = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicines[0]->id,
            'batch_no' => 'BATCH-2026-0915', 'available_stock' => 37]);
        $seeder = new RealisticStockHistorySeeder;
        $seeder->run();

        $this->assertSame(8, $seeder->report['batches_created']);
        $this->assertSame([$other->id], $seeder->report['facilities_without_known_barangay']);
        $this->assertSame(0, $seeder->report['assignments_created']);
        $this->assertSame('Buhang', MidwifeBarangay::where('user_id', $midwife->id)->value('barangay'));
        $this->assertSame(37, $unrelated->fresh()->available_stock);
        $this->assertSame(0, Inventory::whereIn('medicine_id', [$vaccine->id, $incomplete->id])->count());
        $this->assertSame(0, DispensingRecord::whereNotNull('patient_name')->count());
        $this->assertSame(0, PublicUser::count());
        $this->assertDatabaseCount('disease_reports', 0);
        $this->assertSame(0, DispensingRecord::whereDate('date_dispensed', '>=', '2026-11-16')->count());
        $this->assertSame('2026-08-24', DispensingRecord::min('date_dispensed'));
        $this->assertSame('2026-11-13', DispensingRecord::max('date_dispensed'));
        $this->assertGreaterThan(15, DispensingRecord::distinct()->count('quantity_dispensed'));
        foreach (Inventory::where('id', '!=', $unrelated->id)->get() as $batch) {
            $balance = 0;
            $this->assertStringStartsWith('ATQ-', $batch->batch_no);
            $this->assertStringNotContainsString('demo', strtolower($batch->storage_location));
            foreach (StockLog::where('inventory_id', $batch->id)->orderBy('date_added')->orderBy('id')->get() as $log) {
                $balance += $log->quantity_added * ($log->movement_type === 'receipt' ? 1 : -1);
                $this->assertGreaterThanOrEqual(0, $balance);
                $this->assertDoesNotMatchRegularExpression('/demo|sample|fictional/i', $log->remarks);
                $this->assertSame($batch->medicine_id, $log->medicine_id);
                if ($log->dispensing_record_id) {
                    $record = $log->dispensingRecord;
                    $this->assertSame($log->quantity_added, $record->quantity_dispensed);
                    $this->assertSame($batch->health_unit_id, $record->health_unit_id);
                    $this->assertSame($batch->health_unit_id === $unit->id ? 'Buhang' : null, $record->barangay);
                    if ($batch->health_unit_id === $unit->id) {
                        $this->assertSame($midwife->id, $record->dispensed_by);
                    }
                }
            }
            $this->assertSame($balance, $batch->available_stock);
            $this->assertEquals($balance, DB::table('stock_movements')->where('inventory_id', $batch->id)->sum('available_delta'));
        }
        $analysis = app(DiseaseConsumptionTrendService::class)->analyze($unit->id, 'Buhang');
        $this->assertSame(1, $analysis['overview']['elevated']);
        $this->assertCount(4, $analysis['medicine_signals']);
        $this->assertGreaterThan(0, collect($analysis['medicine_signals'])->min('current_quantity'));
        $manifest = $this->manifest();
        $this->assertSame('synthetic', $manifest['source']);
        $this->assertCount(8, $manifest['batches']);
        $this->assertCount(0, $manifest['barangay_assignments']);
        foreach ($manifest['batches'] as $owned) {
            $this->assertSame('synthetic', $owned['source']);
            $this->assertCount(36, $owned['dispensing_ids']);
            $this->assertCount(38, $owned['stock_log_ids']);
            $this->assertCount(38, $owned['stock_movement_ids']);
        }
        $before = [Inventory::withTrashed()->count(), StockLog::count(), DispensingRecord::count(), DB::table('stock_movements')->count()];
        Inventory::where('id', $manifest['batches'][$unit->id.':'.$medicines[0]->id]['inventory_id'])->firstOrFail()->delete();
        $seeder->run();
        $this->assertSame(0, $seeder->report['batches_created']);
        $this->assertSame(8, $seeder->report['batches_preserved']);
        $this->assertSame($before, [Inventory::withTrashed()->count(), StockLog::count(), DispensingRecord::count(), DB::table('stock_movements')->count()]);
    }

    public function test_existing_patient_areas_supply_staff_scope_without_expanding_midwife_assignments(): void
    {
        [$unit, $staff, $midwife] = $this->fixture();
        MidwifeBarangay::where('user_id', $midwife->id)->delete();
        PublicUser::create(['health_unit_id' => $unit->id, 'registered_by' => $staff->id, 'assigned_midwife_id' => $midwife->id,
            'first_name' => 'Maria', 'last_name' => 'Santos', 'barangay' => 'Malandog', 'address' => 'Malandog, Hamtic, Antique']);
        $before = PublicUser::count();
        (new RealisticStockHistorySeeder)->run();
        $this->assertSame([], MidwifeBarangay::where('user_id', $midwife->id)->pluck('barangay')->all());
        $this->assertSame($before, PublicUser::count());
        $this->assertSame(0, DispensingRecord::where('health_unit_id', $unit->id)->where('barangay', '!=', 'Malandog')->count());
    }

    public function test_unowned_batch_collision_rolls_back_all_generated_records_and_assignments(): void
    {
        [$unit, , $midwife, , $medicines] = $this->fixture();
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicines[0]->id,
            'batch_no' => RealisticStockHistorySeeder::batchNumber($unit->id, $medicines[0]->id), 'available_stock' => 19]);
        try {
            (new RealisticStockHistorySeeder)->run();
            $this->fail('An unowned batch must never be adopted.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('unowned stock batch', $error->getMessage());
        }
        $this->assertDatabaseCount('inventory', 1);
        $this->assertDatabaseCount('stock_logs', 0);
        $this->assertDatabaseCount('dispensing_records', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(1, MidwifeBarangay::where('user_id', $midwife->id)->count());
        $this->assertSame([], collect(Storage::disk('local')->files('generated-data'))->filter(fn ($file) => str_ends_with($file, '.json'))->values()->all());
    }

    public function test_nonlocal_environments_are_rejected_before_any_data_or_manifest_is_written(): void
    {
        $this->app->instance('env', 'production');
        try {
            (new RealisticStockHistorySeeder)->run();
            $this->fail('Production seeding must be rejected.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('local and testing', $error->getMessage());
        }
        $this->assertDatabaseCount('inventory', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_existing_nearby_assignments_each_receive_current_weekly_consumption_without_new_assignments(): void
    {
        [$unit, , $midwife] = $this->fixture();
        MidwifeBarangay::where('user_id', $midwife->id)->delete();
        foreach (['Poblacion 1', 'Poblacion 2', 'Poblacion 3'] as $index => $area) {
            MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => $area, 'is_primary' => $index === 0]);
        }
        (new RealisticStockHistorySeeder)->run();
        $this->assertSame(['Poblacion 1', 'Poblacion 2', 'Poblacion 3'], MidwifeBarangay::where('user_id', $midwife->id)->orderBy('id')->pluck('barangay')->all());
        foreach (['Poblacion 1', 'Poblacion 2', 'Poblacion 3'] as $area) {
            $analysis = app(DiseaseConsumptionTrendService::class)->analyze($unit->id, $area);
            $this->assertCount(4, $analysis['medicine_signals']);
            $this->assertSame(1, $analysis['overview']['elevated']);
            foreach ($analysis['medicine_signals'] as $medicine) {
                $this->assertGreaterThan(0, $medicine['current_quantity']);
                $this->assertNotNull($medicine['baseline_average']);
                $this->assertCount(5, $medicine['history']);
            }
        }
        $rhu = app(DiseaseConsumptionTrendService::class)->analyze($unit->id);
        $areaTotal = DispensingRecord::where('health_unit_id', $unit->id)->whereBetween('date_dispensed', ['2026-11-09', '2026-11-15'])->sum('quantity_dispensed');
        $this->assertEquals($areaTotal, array_sum(array_column($rhu['medicine_signals'], 'current_quantity')));
    }

    public function test_remote_database_url_cannot_bypass_the_local_host_guard(): void
    {
        $original = config('database.default');
        config(['database.connections.generated_history_guard' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306', 'database' => 'local_database',
            'username' => 'unused', 'password' => '', 'url' => 'mysql://unused:unused@remote.invalid/hosted_database',
        ], 'database.default' => 'generated_history_guard']);
        try {
            (new RealisticStockHistorySeeder)->run();
            $this->fail('A remote DB_URL must be rejected without connecting.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Generated history cannot be written to a remote database.', $error->getMessage());
        } finally {
            DB::purge('generated_history_guard');
            config(['database.default' => $original]);
        }
        $this->assertDatabaseCount('inventory', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
