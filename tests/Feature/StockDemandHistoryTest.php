<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\Inventory;
use App\Models\StockLog;
use App\Models\User;
use App\Services\ForecastService;
use App\Services\StockDemandHistoryService;
use App\Services\StockTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockDemandHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name'=>'Existing record RHU','facility_type'=>'RHU']);
        $user = User::factory()->create(['role'=>'staff','health_unit_id'=>$unit->id]);
        $medicine = Medicine::create(['medicine_name'=>'Saved transaction medicine','unit'=>'tablet']);

        return [$unit,$user,$medicine];
    }

    private function dispense($unit,$actor,$medicine,int $quantity,array $extra=[]): DispensingRecord
    {
        return DispensingRecord::create(array_replace(['health_unit_id'=>$unit->id,'medicine_id'=>$medicine->id,
            'dispensed_by'=>$actor->id,'quantity_dispensed'=>$quantity,'date_dispensed'=>today()->subWeek(),
            'patient_name'=>'Fictional demand fixture'],$extra));
    }

    private function log($unit,$actor,$medicine,int $quantity,array $extra=[]): StockLog
    {
        return StockLog::create(array_replace(['health_unit_id'=>$unit->id,'medicine_id'=>$medicine->id,
            'added_by'=>$actor->id,'quantity_added'=>$quantity,'date_added'=>today()->subWeek(),
            'movement_type'=>'dispensing','remarks'=>'Dispensed recorded medicine'],$extra));
    }

    private function events($unit)
    {
        return app(StockDemandHistoryService::class)->events([$unit->id],today()->subWeeks(12)->toDateString(),today()->subDay()->toDateString());
    }

    public function test_saved_dispensing_without_stock_logs_populates_a_stockout_forecast_without_writing_stock(): void
    {
        [$unit,$actor,$medicine]=$this->fixture();
        $this->dispense($unit,$actor,$medicine,14);
        $item=app(ForecastService::class)->getForecast($unit->id)['items']->first();
        $this->assertSame($medicine->id,$item['inventory']->medicine_id);
        $this->assertSame(0,$item['available_stock']);
        $this->assertSame(14,$item['history_total']);
        $this->assertSame(1,$item['model']['periods']);
        $this->assertSame(60,$item['forecast_next_30_days']);
        $this->assertDatabaseCount('stock_logs',0);
        $this->assertDatabaseCount('inventory',0);
    }

    public function test_linked_split_logs_are_counted_once_and_a_partial_log_adds_only_the_missing_quantity(): void
    {
        [$unit,$actor,$medicine]=$this->fixture();
        $record=$this->dispense($unit,$actor,$medicine,10);
        $this->log($unit,$actor,$medicine,3,['dispensing_record_id'=>$record->id]);
        $this->log($unit,$actor,$medicine,7,['dispensing_record_id'=>$record->id]);
        $this->assertSame(10,(int)$this->events($unit)->sum('quantity_added'));
        $other=$this->dispense($unit,$actor,$medicine,8);
        $this->log($unit,$actor,$medicine,2,['dispensing_record_id'=>$other->id]);
        $this->assertSame(18,(int)$this->events($unit)->sum('quantity_added'));
    }

    public function test_matching_unlinked_legacy_logs_are_not_duplicated_by_the_new_fallback(): void
    {
        [$unit,$actor,$medicine]=$this->fixture();
        $this->dispense($unit,$actor,$medicine,14);
        $this->log($unit,$actor,$medicine,14,['movement_type'=>null,'remarks'=>'Released - Dispensed historical medicine']);
        $this->assertSame(14,(int)$this->events($unit)->sum('quantity_added'));
        $this->assertSame(1,$this->events($unit)->count());
    }

    public function test_scope_dates_and_deleted_records_do_not_supply_fabricated_usage(): void
    {
        [$unit,$actor,$medicine]=$this->fixture();
        $this->dispense($unit,$actor,$medicine,9);
        foreach([today(),today()->addDay(),today()->subWeeks(13)] as $date){$this->dispense($unit,$actor,$medicine,999,['date_dispensed'=>$date]);}
        $this->dispense($unit,$actor,$medicine,999)->delete();
        $other=HealthUnit::create(['name'=>'Outside demand RHU']);
        $this->dispense($other,$actor,$medicine,999);
        $this->log($unit,$actor,$medicine,999,['movement_type'=>'transfer_out']);
        $this->assertSame(9,(int)$this->events($unit)->sum('quantity_added'));
        $trend=app(StockTrendService::class)->analyze([$unit->id]);
        $this->assertSame(9,(int)array_sum(array_column($trend[$unit->id][$medicine->id]['history'],'quantity')));
        $this->assertArrayNotHasKey($other->id,$trend);
    }

    public function test_unlinked_legacy_split_batches_are_not_counted_again_as_a_saved_dispensing_record(): void
    {
        [$unit,$actor,$medicine]=$this->fixture();
        $this->dispense($unit,$actor,$medicine,10);
        $this->log($unit,$actor,$medicine,3,['batch_no'=>'LEGACY-A']);
        $this->log($unit,$actor,$medicine,7,['batch_no'=>'LEGACY-B']);
        $this->assertSame(10,(int)$this->events($unit)->sum('quantity_added'));
        $this->assertSame(2,$this->events($unit)->count());
    }

    public function test_only_issued_terminal_allocations_supplement_history_and_personal_dispensing_is_included(): void
    {
        [$unit,$actor,$medicine]=$this->fixture();
        $midwife=User::factory()->create(['role'=>'midwife','health_unit_id'=>$unit->id]);
        $base=['health_unit_id'=>$unit->id,'medicine_id'=>$medicine->id,'allocated_by'=>$actor->id,
            'quantity_allocated'=>11,'date_allocated'=>today()->subWeek(),'destination'=>'Program materials','status'=>'completed'];
        Allocation::create($base);
        Allocation::create(array_replace($base,['status'=>'pending','quantity_allocated'=>999]));
        Allocation::create(array_replace($base,['status'=>'rejected','quantity_allocated'=>999]));
        Allocation::create(array_replace($base,['recipient_user_id'=>$midwife->id,'quantity_allocated'=>999]));
        Allocation::create(array_replace($base,['destination_health_unit_id'=>$unit->id,'quantity_allocated'=>999]));
        Allocation::create(array_replace($base,['allocated_by'=>$midwife->id,'quantity_allocated'=>999]));
        $this->dispense($unit,$midwife,$medicine,4,['stock_source'=>'allocation']);
        $this->assertSame(15,(int)$this->events($unit)->sum('quantity_added'));
        $this->assertSame(2,$this->events($unit)->count());
    }

    public function test_voided_or_fully_reversed_sources_are_not_recreated_as_missing_log_history(): void
    {
        [$unit, $actor, $medicine] = $this->fixture();
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 0]);
        $this->dispense($unit, $actor, $medicine, 9);
        foreach (['voided', 'reversed', 'deleted'] as $state) {
            $dispensing = $this->dispense($unit, $actor, $medicine, 999);
            $allocation = Allocation::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'allocated_by' => $actor->id, 'date_allocated' => today()->subWeek(), 'quantity_allocated' => 999,
                'destination' => 'Local program', 'status' => 'completed']);
            foreach ([[$dispensing, 'dispensing_record_id', 'dispensing'], [$allocation, 'allocation_id', 'allocation']] as [$owner, $link, $type]) {
                $this->log($unit, $actor, $medicine, 999, [$link => $owner->id, 'movement_type' => $type,
                    'voided_at' => $state === 'voided' ? now() : null]);
                if ($state === 'reversed') {
                    DB::table('stock_movements')->insert(['owner_type' => $owner->getTable(), 'owner_id' => $owner->id,
                        'inventory_id' => $batch->id, 'available_delta' => -999, 'allocated_delta' => 0,
                        'reversed_at' => now(), 'created_at' => now()]);
                } elseif ($state === 'deleted') {
                    $owner->delete();
                }
            }
        }

        $this->assertSame(9, (int) $this->events($unit)->sum('quantity_added'));
        $this->assertSame(1, $this->events($unit)->count());
    }
}
