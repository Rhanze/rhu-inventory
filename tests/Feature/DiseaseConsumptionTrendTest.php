<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DiseaseReport;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\DiseaseConsumptionTrendService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiseaseConsumptionTrendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Consumption signals RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $disease = Disease::create(['code' => 'CONSUMPTION', 'name' => 'Consumption-linked condition', 'is_active' => true]);
        $medicine = Medicine::create(['medicine_name' => 'Consumption tablets', 'unit' => 'tablet', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);

        return [$unit, $user, $medicine, $disease];
    }

    private function dispense(HealthUnit $unit, User $user, Medicine $medicine, string $date, int $quantity, ?string $barangay = 'Poblacion'): DispensingRecord
    {
        return DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
            'medicine_id' => $medicine->id, 'date_dispensed' => $date, 'quantity_dispensed' => $quantity,
            'barangay' => $barangay]);
    }

    private function baseline(HealthUnit $unit, User $user, Medicine $medicine, int $quantity = 10): void
    {
        foreach (['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'] as $date) {
            $this->dispense($unit, $user, $medicine, $date, $quantity);
        }
    }

    public function test_elevated_disease_signals_keep_medicine_units_separate_and_ignore_manual_case_totals(): void
    {
        [$unit, $user, $tablets, $disease] = $this->fixture();
        $liquid = Medicine::create(['medicine_name' => 'Consumption liquid', 'unit' => 'mL', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $liquid->id]);
        $otherDisease = Disease::create(['code' => 'SECOND-CONSUMPTION', 'name' => 'Second linked condition', 'is_active' => true]);
        DiseaseMedicineMap::create(['disease_id' => $otherDisease->id, 'medicine_id' => $tablets->id]);
        foreach ([$tablets, $liquid] as $medicine) {
            $this->baseline($unit, $user, $medicine);
            $this->dispense($unit, $user, $medicine, '2026-10-26', $medicine->id === $tablets->id ? 15 : 14);
        }
        $service = app(DiseaseConsumptionTrendService::class);
        $result = $service->analyze($unit->id, 'Poblacion');
        $signal = collect($result['signals'])->firstWhere('id', $disease->id);
        $medicines = collect($signal['medicines'])->keyBy('id');

        $this->assertSame('elevated', $signal['status']);
        $this->assertSame(1, $signal['elevated_medicines']);
        $this->assertSame(2, $signal['medicines_reviewed']);
        $this->assertSame('tablet', $medicines[$tablets->id]['unit']);
        $this->assertSame('mL', $medicines[$liquid->id]['unit']);
        $this->assertEquals(50, $medicines[$tablets->id]['change_percent']);
        $this->assertSame('typical', $medicines[$liquid->id]['status']);
        $this->assertSame(10, $result['total_transactions']);
        $this->assertSame(2, $result['overview']['elevated']);
        $this->assertArrayNotHasKey('total_quantity', $signal);
        $this->assertArrayNotHasKey('probability', $signal);
        $this->assertArrayNotHasKey('cases', $signal);
        DiseaseReport::create(['health_unit_id' => $unit->id, 'disease_id' => $disease->id,
            'period_type' => 'weekly', 'report_date' => '2026-10-26', 'case_count' => 99999,
            'reported_by' => $user->id, 'is_complete' => true]);
        $this->assertSame($result, $service->analyze($unit->id, 'Poblacion'));
    }

    public function test_zero_baselines_and_short_or_absent_history_do_not_claim_elevated_trends(): void
    {
        [$unit, $user, $medicine, $disease] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2026-09-01', 1);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 5);
        foreach (['short', 'empty'] as $type) {
            $condition = Disease::create(['code' => 'CONSUMPTION-'.$type, 'name' => $type, 'is_active' => true]);
            $item = Medicine::create(['medicine_name' => $type, 'is_dispensable' => true]);
            DiseaseMedicineMap::create(['medicine_id' => $item->id, 'disease_id' => $condition->id]);
            if ($type === 'short') {
                $this->dispense($unit, $user, $item, '2026-10-26', 999);
            }
        }
        $result = app(DiseaseConsumptionTrendService::class)->analyze($unit->id, 'Poblacion');
        $signals = collect($result['signals'])->keyBy('name');

        $this->assertSame('new', $signals[$disease->name]['status']);
        $this->assertEquals(0, $signals[$disease->name]['medicines'][0]['baseline_average']);
        $this->assertNull($signals[$disease->name]['medicines'][0]['change_percent']);
        $this->assertSame('insufficient', $signals['short']['status']);
        $this->assertSame('insufficient', $signals['empty']['status']);
        $this->assertSame(0, $result['overview']['elevated']);
        $this->assertSame(1, $result['overview']['new']);
        $this->assertSame(2, $result['overview']['insufficient']);
    }

    public function test_area_personal_and_rhu_scopes_exclude_other_areas_facilities_deleted_and_future_records(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $outside = HealthUnit::create(['name' => 'Outside consumption RHU']);
        $this->baseline($unit, $user, $medicine);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 7);
        $this->dispense($unit, $colleague, $medicine, '2026-10-27', 8);
        $this->dispense($unit, $colleague, $medicine, '2026-10-27', 3, 'Other area');
        $this->dispense($outside, $user, $medicine, '2026-10-27', 999);
        $this->dispense($unit, $user, $medicine, '2026-10-27', 999)->delete();
        $this->dispense($unit, $user, $medicine, '2026-11-04', 2);
        $this->dispense($unit, $user, $medicine, '2026-11-09', 999);
        $service = app(DiseaseConsumptionTrendService::class);
        $area = $service->analyze($unit->id, 'Poblacion', 'weekly', $user->id);
        $personal = $service->analyze($unit->id, null, 'weekly', $user->id);
        $rhu = $service->analyze($unit->id);

        $this->assertSame('area', $area['scope']);
        $this->assertSame(15, $area['signals'][0]['medicines'][0]['current_quantity']);
        $this->assertSame(7, $area['total_transactions']);
        $this->assertSame('personal', $personal['scope']);
        $this->assertSame(7, $personal['signals'][0]['medicines'][0]['current_quantity']);
        $this->assertSame(6, $personal['total_transactions']);
        $this->assertSame('rhu', $rhu['scope']);
        $this->assertSame(18, $rhu['signals'][0]['medicines'][0]['current_quantity']);
        $this->assertSame(8, $rhu['total_transactions']);
    }

    public function test_historical_views_use_exact_selected_dates_keep_archived_units_and_hide_current_signals(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        foreach (['2024-01-14' => 99, '2024-01-15' => 5, '2024-01-16' => 999, '2025-01-15' => 9999] as $date => $quantity) {
            $this->dispense($unit, $user, $medicine, $date, $quantity);
        }
        $medicine->delete();
        $result = app(DiseaseConsumptionTrendService::class)->analyze($unit->id, 'Poblacion', 'monthly', null, '2024-01-15', '2024-01-15');
        $signal = $result['signals'][0];
        $evidence = $signal['medicines'][0];

        $this->assertTrue($result['historical']);
        $this->assertSame('historical', $signal['status']);
        $this->assertSame(['from' => '2024-01-15', 'to' => '2024-01-15'], $result['window']);
        $this->assertSame(5, $evidence['total_quantity']);
        $this->assertSame(1, $evidence['transactions']);
        $this->assertNull($evidence['current_quantity']);
        $this->assertNull($evidence['baseline_average']);
        $this->assertNull($evidence['change_percent']);
        $this->assertSame('2024-01-15', $evidence['history'][0]['included_start']);
        $this->assertSame('2024-01-15', $evidence['history'][0]['included_end']);
        $this->assertSame(0, $result['overview']['elevated']);
        $this->assertSame([], app(DiseaseConsumptionTrendService::class)->analyze($unit->id)['signals']);
    }

    public function test_reversed_dispensing_is_excluded_but_an_edited_record_with_an_active_movement_remains(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 0]);
        $this->baseline($unit, $user, $medicine);
        $valid = $this->dispense($unit, $user, $medicine, '2026-10-26', 15);
        $voided = $this->dispense($unit, $user, $medicine, '2026-10-26', 999);
        StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensing_record_id' => $voided->id, 'quantity_added' => 999,
            'movement_type' => 'dispensing', 'date_added' => '2026-10-26', 'voided_at' => now()]);
        $reversed = $this->dispense($unit, $user, $medicine, '2026-10-26', 999);
        foreach ([[$valid, now()], [$valid, null], [$reversed, now()]] as [$record, $reversedAt]) {
            DB::table('stock_movements')->insert(['owner_type' => 'dispensing_records', 'owner_id' => $record->id,
                'inventory_id' => $batch->id, 'available_delta' => -$record->quantity_dispensed,
                'allocated_delta' => 0, 'reversed_at' => $reversedAt, 'created_at' => now()]);
        }
        $result = app(DiseaseConsumptionTrendService::class)->analyze($unit->id, 'Poblacion');

        $this->assertSame(15, $result['signals'][0]['medicines'][0]['current_quantity']);
        $this->assertSame(5, $result['total_transactions']);
        $this->assertSame([$valid->id], app(DiseaseConsumptionTrendService::class)->recordsQuery($unit->id, 'Poblacion')
            ->whereDate('date_dispensed', '2026-10-26')->pluck('id')->all());
    }

    public function test_monthly_bounds_and_end_only_history_are_derived_only_from_mapped_consumption(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        foreach (['2026-06-30', '2026-07-01', '2026-08-31', '2026-09-30'] as $date) {
            $this->dispense($unit, $user, $medicine, $date, 5);
        }
        $this->dispense($unit, $user, $medicine, '2026-10-31', 10);
        $this->dispense($unit, $user, $medicine, '2026-11-01', 999);
        $unmapped = Medicine::create(['medicine_name' => 'Unmapped old history', 'is_dispensable' => true]);
        $this->dispense($unit, $user, $unmapped, '2000-01-01', 999);
        $service = app(DiseaseConsumptionTrendService::class);
        $current = $service->analyze($unit->id, null, 'monthly');
        $past = $service->analyze($unit->id, null, 'monthly', null, null, '2026-10-31');

        $this->assertSame('2026-10-01', $current['period_start']);
        $this->assertSame('2026-10-31', $current['period_end']);
        $this->assertSame('2026-06-01', $current['baseline_start']);
        $this->assertEquals(100, $current['signals'][0]['medicines'][0]['change_percent']);
        $this->assertSame('2026-06-30', $past['window']['from']);
        $this->assertSame(30, $past['signals'][0]['medicines'][0]['total_quantity']);
        $this->assertSame(5, $past['total_transactions']);
        $this->assertSame(7, $service->recordsQuery($unit->id)->count());
    }

    public function test_selected_disease_resolves_its_history_start_before_applying_the_chart_window_limit(): void
    {
        [$unit, $user, $medicine, $disease] = $this->fixture();
        $otherDisease = Disease::create(['code' => 'OLD-CONSUMPTION', 'name' => 'Other old condition', 'is_active' => true]);
        $oldMedicine = Medicine::create(['medicine_name' => 'Other old medicine', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['medicine_id' => $oldMedicine->id, 'disease_id' => $otherDisease->id]);
        $this->dispense($unit, $user, $oldMedicine, '2001-01-01', 999);
        $this->dispense($unit, $user, $medicine, '2023-01-15', 5);
        $this->dispense($unit, $user, $medicine, '2024-12-01', 7);
        $result = app(DiseaseConsumptionTrendService::class)->analyze($unit->id, null, 'monthly', null, null, '2024-12-31', $disease->id);

        $this->assertSame('2023-01-15', $result['window']['from']);
        $this->assertSame([$disease->id], array_column($result['signals'], 'id'));
        $this->assertSame(12, $result['signals'][0]['medicines'][0]['total_quantity']);
        $this->assertSame(2, $result['total_transactions']);
    }

    public function test_graph_series_align_calendar_periods_keep_unknown_history_and_do_not_duplicate_shared_medicine(): void
    {
        [$unit, $user, $medicine, $disease] = $this->fixture();
        $secondDisease = Disease::create(['code' => 'SHARED-GRAPH', 'name' => 'Second graph condition']);
        DiseaseMedicineMap::create(['medicine_id' => $medicine->id, 'disease_id' => $secondDisease->id]);
        $laterMedicine = Medicine::create(['medicine_name' => 'Later graph liquid', 'unit' => 'mL', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['medicine_id' => $laterMedicine->id, 'disease_id' => $disease->id]);
        foreach (['2026-09-28' => 8, '2026-10-05' => 10, '2026-10-19' => 22, '2026-10-26' => 20] as $date => $quantity) {
            $this->dispense($unit, $user, $medicine, $date, $quantity);
        }
        $this->dispense($unit, $user, $laterMedicine, '2026-10-12', 4);
        // Both records lie outside the graph's completed-period window.
        $this->dispense($unit, $user, $medicine, '2026-11-02', 1000);
        $this->dispense($unit, $user, $medicine, '2026-11-09', 2000);

        $analysis = app(DiseaseConsumptionTrendService::class)->analyze($unit->id, 'Poblacion');
        $series = collect($analysis['medicine_signals'])->keyBy('id');
        $expectedPeriods = ['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'];
        $this->assertCount(2, $series);
        $this->assertSame($expectedPeriods, array_column($series[$medicine->id]['history'], 'start'));
        $this->assertSame($expectedPeriods, array_column($series[$laterMedicine->id]['history'], 'start'));
        $this->assertSame([8, 10, 0, 22, 20], array_column($series[$medicine->id]['history'], 'quantity'));
        $this->assertSame([null, null, 4, 0, 0], array_column($series[$laterMedicine->id]['history'], 'quantity'));
        $this->assertEquals(10, $series[$medicine->id]['baseline_average']);
        $this->assertEquals(100, $series[$medicine->id]['change_percent']);
        $this->assertSame('tablet', $series[$medicine->id]['unit']);
        $this->assertSame('mL', $series[$laterMedicine->id]['unit']);
        $this->assertSame(60, $series[$medicine->id]['total_quantity']);
        $this->assertSame($series[$medicine->id], collect($analysis['signals'])->firstWhere('id', $secondDisease->id)['medicines'][0]);
    }
}
