<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\StockMovementService;
use Database\Seeders\SampleStockHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SampleStockHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_samples_are_varied_reconciled_reversible_and_repeatable(): void
    {
        $unit = HealthUnit::create(['name' => 'Sample RHU', 'facility_type' => 'Rural Health Unit']);
        User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff', 'is_active' => true]);
        foreach (range(1, 12) as $index) {
            Medicine::create(['medicine_name' => 'Sample medicine '.str_pad($index, 2, '0', STR_PAD_LEFT), 'category' => 'medicine', 'is_dispensable' => true]);
        }
        $this->seed(SampleStockHistorySeeder::class);
        $this->assertSame(12, Inventory::count());
        $this->assertSame(24, StockLog::where('movement_type', 'receipt')->count());
        $this->assertSame(12, StockLog::distinct()->count('medicine_id'));
        $this->assertGreaterThan(10, StockLog::distinct()->count('quantity_added'));
        $this->assertGreaterThan(12, StockLog::distinct()->count('remarks'));
        $this->assertSame(0, StockLog::whereDate('date_added', '>', today())->count());
        foreach (Inventory::all() as $batch) {
            $balance = 0;
            foreach (StockLog::where('inventory_id', $batch->id)->orderBy('date_added')->orderBy('id')->get() as $log) {
                $balance += ($log->movement_type === 'receipt' ? 1 : -1) * $log->quantity_added;
                $this->assertGreaterThanOrEqual(0, $balance);
                $this->assertSame($batch->medicine_id, $log->medicine_id);
                $this->assertSame($batch->health_unit_id, $log->health_unit_id);
                if ($log->movement_type === 'dispensing') {
                    $this->assertSame($log->quantity_added, $log->dispensingRecord->quantity_dispensed);
                }
            }
            $this->assertSame($balance, $batch->available_stock);
            $this->assertEquals($balance, DB::table('stock_movements')->where('inventory_id', $batch->id)->sum('available_delta'));
        }
        $before = [StockLog::count(), DispensingRecord::count(), DB::table('stock_movements')->count()];
        $this->seed(SampleStockHistorySeeder::class);
        $this->assertSame($before, [StockLog::count(), DispensingRecord::count(), DB::table('stock_movements')->count()]);
        $record = DispensingRecord::firstOrFail();
        $batch = Inventory::where('batch_no', $record->batch_no)->firstOrFail();
        $available = $batch->available_stock;
        DB::transaction(fn () => app(StockMovementService::class)->reverse($record));
        $this->assertSame($available + $record->quantity_dispensed, $batch->fresh()->available_stock);
        $this->assertNotNull($record->stockLogs()->firstOrFail()->voided_at);
    }

    public function test_profiles_include_distinct_directions_and_intermittent_usage(): void
    {
        $seeder = new SampleStockHistorySeeder;
        $rising = $seeder->weeklyQuantities(0);
        $declining = $seeder->weeklyQuantities(1);
        $intermittent = $seeder->weeklyQuantities(3);
        $this->assertGreaterThan($rising[0], $rising[11]);
        $this->assertLessThan($declining[0], $declining[11]);
        $this->assertContains(0, $intermittent);
        $this->assertNotSame($rising, $declining);
    }
}
