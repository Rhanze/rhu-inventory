<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\StockLog;
use App\Models\User;
use App\Services\DiseaseConsumptionTrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DispensingRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Read scope RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Assigned area']);
        $medicine = Medicine::create(['medicine_name' => 'Scoped dispensing medicine', 'unit' => 'tablet']);
        $records = [];
        foreach ([[$staff, ' assigned area ', 5], [$midwife, 'Assigned area', 7], [$staff, 'Other area', 9],
            [$midwife, 'Previously served area', 11], [$midwife, null, 13]] as [$actor, $area, $quantity]) {
            $records[] = DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $actor->id,
                'medicine_id' => $medicine->id, 'barangay' => $area, 'date_dispensed' => today(), 'quantity_dispensed' => $quantity]);
        }
        $foreign = HealthUnit::create(['name' => 'Foreign scope RHU']);
        DispensingRecord::create(['health_unit_id' => $foreign->id, 'dispensed_by' => $midwife->id,
            'medicine_id' => $medicine->id, 'barangay' => 'Assigned area', 'date_dispensed' => today(), 'quantity_dispensed' => 999]);

        return [$midwife, $staff, $records];
    }

    public function test_midwife_reads_assigned_area_activity_and_can_switch_to_own_historical_records(): void
    {
        [$midwife, , $records] = $this->fixture();
        $this->withSession(['user_id' => $midwife->id])->get(route('dispensing.index'))->assertOk()
            ->assertViewHas('dispensingScope', 'area')
            ->assertViewHas('records', fn ($rows) => $rows->total() === 2 && $rows->pluck('id')->sort()->values()->all() === [$records[0]->id, $records[1]->id]);
        $csv = $this->get(route('dispensing.export'))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
        $this->assertCount(3, $rows);
        $this->assertStringNotContainsString('Previously served area', $csv);
        $this->assertStringNotContainsString('999', $csv);
        $this->get(route('dispensing.index', ['dispensing_scope' => 'personal']))->assertOk()
            ->assertViewHas('records', fn ($rows) => $rows->total() === 3)
            ->assertSee('Previously served area')->assertSee('My recorded dispensing');
        $csv = $this->get(route('dispensing.export', ['dispensing_scope' => 'personal']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Previously served area', $csv);
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
        $this->assertContains('', array_column(array_slice($rows, 1), array_search('Barangay served', $rows[0])));
        $this->assertDatabaseCount('dispensing_records', 6);
        foreach (['dispensing.index', 'dispensing.export'] as $route) {
            $this->getJson(route($route, ['dispensing_scope' => 'area', 'barangay' => 'Other area']))->assertUnprocessable();
        }
        $this->get(route('dispensing.edit', $records[0]->id))->assertNotFound();
    }

    public function test_facility_totals_and_barangay_breakdown_reuse_the_same_single_records(): void
    {
        [, $staff] = $this->fixture();
        $this->withSession(['user_id' => $staff->id])->get(route('dispensing.index'))->assertOk()
            ->assertViewHas('facilityRecordCount', 5)
            ->assertViewHas('records', fn ($rows) => $rows->total() === 5)
            ->assertViewHas('barangayBreakdown', function ($rows) {
                $assigned = $rows->first(fn ($row) => mb_strtolower($row->area) === 'assigned area');

                return $rows->count() === 4 && (int) ($assigned?->quantity ?? 0) === 12;
            })
            ->assertSee('Dispensing by barangay')->assertSee('Stock source')->assertSee('RHU shared stock');
        $this->get(route('reports.dispensing'))->assertOk()->assertViewHas('facilityRecordCount', 5)
            ->assertViewHas('barangayBreakdown', fn ($rows) => $rows->count() === 4 && $rows->sum('record_count') === 5);
        $csv = $this->get(route('reports.dispensing.csv'))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
        $this->assertCount(6, $rows);
        $this->assertContains('Barangay served', $rows[0]);
        $this->assertContains('Stock source', $rows[0]);
        $this->assertContains('rhu', array_column(array_slice($rows, 1), array_search('Stock source', $rows[0])));
        $this->assertStringNotContainsString('999', $csv);
    }

    public function test_counts_lists_exports_and_consumption_exclude_voided_and_fully_reversed_dispensing(): void
    {
        [$midwife, $staff, $records] = $this->fixture();
        StockLog::create(['medicine_id' => $records[0]->medicine_id, 'health_unit_id' => $staff->health_unit_id,
            'quantity_added' => 5, 'date_added' => today(), 'dispensing_record_id' => $records[0]->id,
            'movement_type' => 'dispensing', 'voided_at' => now()]);
        $movement = ['owner_type' => 'dispensing_records', 'inventory_id' => 1, 'available_delta' => -7,
            'allocated_delta' => 0, 'created_at' => now(), 'reversed_at' => now()];
        DB::table('stock_movements')->insert($movement + ['owner_id' => $records[1]->id]);
        $records[2]->update(['quantity_dispensed' => 0]);
        $records[3]->delete();
        // A correction retains its new issue despite older reversed movements.
        DB::table('stock_movements')->insert($movement + ['owner_id' => $records[4]->id]);
        DB::table('stock_movements')->insert(array_replace($movement, ['owner_id' => $records[4]->id, 'reversed_at' => null]));

        $this->withSession(['user_id' => $staff->id])->get(route('dispensing.index'))->assertOk()
            ->assertViewHas('facilityRecordCount', 1)
            ->assertViewHas('records', fn ($rows) => $rows->pluck('id')->all() === [$records[4]->id]);
        $this->get(route('reports.dispensing'))->assertOk()->assertViewHas('facilityRecordCount', 1);
        foreach (['dispensing.export', 'reports.dispensing.csv'] as $route) {
            $csv = $this->get(route($route))->assertOk()->streamedContent();
            $this->assertCount(2, array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv))));
        }
        $this->assertSame([$records[4]->id], app(DiseaseConsumptionTrendService::class)
            ->recordsQuery((int) $staff->health_unit_id)->pluck('id')->all());
        $this->withSession(['user_id' => $midwife->id])->get(route('dashboard.midwife'))->assertOk()
            ->assertViewHas('dispensedToday', 1)->assertViewHas('dispensedThisMonth', 1)
            ->assertViewHas('recentDispensing', fn ($rows) => $rows->pluck('id')->all() === [$records[4]->id]);
    }

    public function test_barangay_and_medicine_drilldowns_preserve_dates_and_handle_missing_areas_exactly(): void
    {
        [$midwife, $staff, $records] = $this->fixture();
        $medicine = Medicine::create(['medicine_name' => 'Liquid area medicine', 'unit' => 'bottle']);
        DispensingRecord::create(['health_unit_id' => $staff->health_unit_id, 'dispensed_by' => $staff->id,
            'medicine_id' => $medicine->id, 'barangay' => 'Assigned area', 'date_dispensed' => today(), 'quantity_dispensed' => 2]);
        DispensingRecord::create(['health_unit_id' => $staff->health_unit_id, 'dispensed_by' => $staff->id,
            'medicine_id' => $records[0]->medicine_id, 'barangay' => 'Not recorded', 'date_dispensed' => today(), 'quantity_dispensed' => 17]);
        $dates = ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()];
        $areaFilters = $dates + ['medicine_id' => $records[0]->medicine_id, 'barangay' => 'Assigned area'];
        $missingFilters = $dates + ['medicine_id' => $records[0]->medicine_id, 'barangay_missing' => 1];
        $response = $this->withSession(['user_id' => $staff->id])->get(route('dispensing.index', $dates))->assertOk();
        $assigned = $response->viewData('barangayBreakdown')->first(fn ($row) => mb_strtolower($row->recorded_area ?? '') === 'assigned area');
        $this->assertNotNull($assigned);
        $response->assertSee(route('dispensing.index', array_replace($areaFilters, ['barangay' => $assigned->recorded_area])))
            ->assertSee(route('dispensing.index', $missingFilters));
        foreach (['dispensing.index', 'reports.dispensing'] as $route) {
            $this->get(route($route, $areaFilters))->assertOk()->assertViewHas('facilityRecordCount', 2)
                ->assertViewHas('barangayBreakdown', fn ($rows) => $rows->count() === 1 && (int) $rows->first()->quantity === 12);
            $this->get(route($route, $missingFilters))->assertOk()->assertViewHas('facilityRecordCount', 1)
                ->assertViewHas('barangayBreakdown', fn ($rows) => $rows->first()->recorded_area === null && (int) $rows->first()->quantity === 13);
            $this->get(route($route, $dates + ['medicine_id' => $records[0]->medicine_id, 'barangay' => 'Not recorded']))
                ->assertOk()->assertViewHas('facilityRecordCount', 1)
                ->assertViewHas('barangayBreakdown', fn ($rows) => (int) $rows->first()->quantity === 17);
        }
        foreach (['dispensing.export', 'reports.dispensing.csv'] as $route) {
            $csv = $this->get(route($route, $areaFilters))->assertOk()->streamedContent();
            $this->assertCount(3, array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv))));
            $this->assertStringNotContainsString('Liquid area medicine', $csv);
        }
        $this->withSession(['user_id' => $midwife->id])->get(route('dispensing.index', $areaFilters))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 2);
        $this->get(route('dispensing.index', ['dispensing_scope' => 'personal', 'barangay' => 'Previously served area']))
            ->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1);
    }
}
