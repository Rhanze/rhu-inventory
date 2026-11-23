<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\User;
use App\Services\AreaMedicineDemandService;
use App\Services\MedicineUsageTrendService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaMedicineDemandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Demand RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $medicine = Medicine::create(['medicine_name' => 'Demand medicine', 'dosage' => '100 mg',
            'dosage_form' => 'tablet', 'unit' => 'tablet', 'is_dispensable' => true]);
        $disease = Disease::create(['name' => 'Associated condition', 'code' => 'DEMAND', 'is_active' => true]);
        DiseaseMedicineMap::create(['medicine_id' => $medicine->id, 'disease_id' => $disease->id, 'doses_per_case' => 2]);

        return [$unit, $user, $medicine, $disease];
    }

    private function dispense(HealthUnit $unit, User $user, Medicine $medicine, string $date, int $quantity, ?string $barangay = 'Poblacion'): DispensingRecord
    {
        return DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensed_by' => $user->id, 'date_dispensed' => $date, 'quantity_dispensed' => $quantity,
            'barangay' => $barangay]);
    }

    private function baseline(HealthUnit $unit, User $user, Medicine $medicine, int $quantity = 10): void
    {
        foreach (['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'] as $date) {
            $this->dispense($unit, $user, $medicine, $date, $quantity);
        }
    }

    public function test_high_demand_uses_latest_completed_week_and_four_prior_weeks_without_case_conversion(): void
    {
        [$unit, $user, $medicine, $disease] = $this->fixture();
        $this->baseline($unit, $user, $medicine);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 15);
        $result = app(AreaMedicineDemandService::class)->analyze($unit->id, 'Poblacion');
        $signal = $result['signals'][0];

        $this->assertSame('2026-10-26', $result['period_start']);
        $this->assertSame('2026-11-01', $result['period_end']);
        $this->assertSame('2026-09-28', $result['baseline_start']);
        $this->assertSame('2026-10-25', $result['baseline_end']);
        $this->assertSame('high', $signal['status']);
        $this->assertSame(15, $signal['current_quantity']);
        $this->assertEquals(10, $signal['baseline_average']);
        $this->assertEquals(50, $signal['change_percent']);
        $this->assertSame(1, $result['overview']['high']);
        $this->assertSame([['id' => $disease->id, 'name' => $disease->name]], $signal['related_diseases']);
        $this->assertArrayNotHasKey('cases', $signal);
        $this->assertArrayNotHasKey('is_outbreak', $signal);
    }

    public function test_area_aggregation_includes_colleagues_but_excludes_other_areas_facilities_deleted_and_partial_records(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $otherUnit = HealthUnit::create(['name' => 'Other demand RHU']);
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $this->baseline($unit, $user, $medicine);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 7);
        $this->dispense($unit, $colleague, $medicine, '2026-10-27', 8);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 999, 'Other barangay');
        $this->dispense($otherUnit, $user, $medicine, '2026-10-26', 999);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 999)->delete();
        $this->dispense($unit, $user, $medicine, '2026-11-02', 999);
        $this->dispense($unit, $user, $medicine, '2026-11-09', 999);

        $result = app(AreaMedicineDemandService::class)->analyze($unit->id, 'Poblacion');
        $this->assertCount(1, $result['signals']);
        $this->assertSame(15, $result['signals'][0]['current_quantity']);
        $this->assertEquals(10, $result['signals'][0]['baseline_average']);
        $this->assertSame('high', $result['signals'][0]['status']);
    }

    public function test_monthly_comparison_uses_calendar_months_and_excludes_current_month(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        foreach (['2026-06-30', '2026-07-01', '2026-08-31', '2026-09-30'] as $date) {
            $this->dispense($unit, $user, $medicine, $date, 5);
        }
        $this->dispense($unit, $user, $medicine, '2026-10-31', 10);
        $this->dispense($unit, $user, $medicine, '2026-11-01', 999);
        $result = app(AreaMedicineDemandService::class)->analyze($unit->id, 'Poblacion', 'monthly');

        $this->assertSame('2026-10-01', $result['period_start']);
        $this->assertSame('2026-10-31', $result['period_end']);
        $this->assertSame('2026-06-01', $result['baseline_start']);
        $this->assertSame('2026-09-30', $result['baseline_end']);
        $this->assertSame('high', $result['signals'][0]['status']);
        $this->assertEquals(100, $result['signals'][0]['change_percent']);
    }

    public function test_new_demand_and_short_history_do_not_claim_a_high_signal_or_infinite_increase(): void
    {
        [$unit, $user, $medicine, $disease] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2026-09-01', 1);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 5);
        $short = Medicine::create(['medicine_name' => 'Newly recorded medicine', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['medicine_id' => $short->id, 'disease_id' => $disease->id]);
        $this->dispense($unit, $user, $short, '2026-10-26', 999);
        $signals = collect(app(AreaMedicineDemandService::class)->analyze($unit->id, 'Poblacion')['signals'])->keyBy('id');

        $this->assertSame('new_demand', $signals[$medicine->id]['status']);
        $this->assertEquals(0, $signals[$medicine->id]['baseline_average']);
        $this->assertNull($signals[$medicine->id]['change_percent']);
        $this->assertSame('insufficient_history', $signals[$short->id]['status']);
        $this->assertNull($signals[$short->id]['baseline_average']);
        $this->assertNull($signals[$short->id]['change_percent']);
    }

    public function test_only_active_mapped_medicines_are_compared_and_shared_disease_mappings_do_not_duplicate_quantities(): void
    {
        [$unit, $user, $medicine, $disease] = $this->fixture();
        $secondDisease = Disease::create(['name' => 'Second association', 'code' => 'SECOND', 'is_active' => true]);
        DiseaseMedicineMap::create(['medicine_id' => $medicine->id, 'disease_id' => $secondDisease->id]);
        $liquid = Medicine::create(['medicine_name' => 'Liquid medicine', 'dosage' => '10 mg/ml', 'dosage_form' => 'liquid', 'unit' => 'bottle', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['medicine_id' => $liquid->id, 'disease_id' => $disease->id]);
        foreach ([$medicine, $liquid] as $item) {
            $this->baseline($unit, $user, $item);
            $this->dispense($unit, $user, $item, '2026-10-26', $item->id === $medicine->id ? 15 : 14);
        }
        $unmapped = Medicine::create(['medicine_name' => 'Unmapped', 'is_dispensable' => true]);
        $internal = Medicine::create(['medicine_name' => 'Internal', 'is_dispensable' => false]);
        $deleted = Medicine::create(['medicine_name' => 'Deleted', 'is_dispensable' => true]);
        foreach ([$unmapped, $internal, $deleted] as $item) {
            if ($item->id !== $unmapped->id) {
                DiseaseMedicineMap::create(['medicine_id' => $item->id, 'disease_id' => $disease->id]);
            }
            $this->dispense($unit, $user, $item, '2026-10-26', 999);
        }
        $deleted->delete();
        $result = app(AreaMedicineDemandService::class)->analyze($unit->id, 'Poblacion');
        $signals = collect($result['signals'])->keyBy('id');

        $this->assertCount(2, $signals);
        $this->assertCount(2, $signals[$medicine->id]['related_diseases']);
        $this->assertSame(15, $signals[$medicine->id]['current_quantity']);
        $this->assertSame('tablet', $signals[$medicine->id]['unit']);
        $this->assertSame('bottle', $signals[$liquid->id]['unit']);
        $this->assertSame('typical', $signals[$liquid->id]['status']);
        $this->assertSame(1, $result['overview']['high']);
        $this->assertSame(1, $result['overview']['typical']);
        $this->assertArrayNotHasKey('total_quantity', $result);
    }

    public function test_unattributed_history_stays_out_of_area_signals_and_is_counted_for_review(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        foreach ([null, '', '  '] as $area) {
            $this->dispense($unit, $user, $medicine, '2026-10-26', 999, $area);
        }
        $this->dispense($unit, $user, $medicine, '2026-09-01', 999, null);
        $this->dispense($unit, $user, $medicine, '2026-11-02', 999, null);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 999, null)->delete();
        $result = app(AreaMedicineDemandService::class)->analyze($unit->id, 'Poblacion');

        $this->assertSame([], $result['signals']);
        $this->assertSame(0, $result['overview']['high']);
        $this->assertSame(3, $result['unassigned_records']);
    }

    public function test_area_usage_and_demand_keep_recorded_location_after_midwife_reassignment(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $assignment = MidwifeBarangay::create(['user_id' => $user->id, 'barangay' => 'Poblacion', 'is_primary' => true]);
        $this->baseline($unit, $user, $medicine);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 15);
        $assignment->update(['barangay' => 'New assignment']);
        $result = app(AreaMedicineDemandService::class)->analyze($unit->id, 'Poblacion');
        $otherArea = app(AreaMedicineDemandService::class)->analyze($unit->id, 'New assignment');
        $usage = app(MedicineUsageTrendService::class)->analyze($unit->id, null, $medicine->id, 'weekly', 5, 'Poblacion');

        $this->assertSame('high', $result['signals'][0]['status']);
        $this->assertSame([], $otherArea['signals']);
        $this->assertSame('area', $usage['scope']);
        $this->assertSame('Poblacion', $usage['location']);
        $this->assertSame(55, $usage['total_quantity']);
        $this->assertNull($usage['suggested_replenishment']);
        $this->assertSame(0, app(MedicineUsageTrendService::class)->analyze($unit->id, null, $medicine->id, 'weekly', 5, 'New assignment')['total_quantity']);
    }
}
