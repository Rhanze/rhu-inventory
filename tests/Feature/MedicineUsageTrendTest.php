<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use App\Services\MedicineUsageTrendService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineUsageTrendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Usage RHU', 'facility_type' => 'Rural Health Unit']);
        $medicine = Medicine::create(['medicine_name' => 'Usage medicine', 'dosage' => '100 mg', 'unit' => 'tablet', 'category' => 'medicine']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);

        return [$unit, $medicine, $user];
    }

    private function dispense(HealthUnit $unit, Medicine $medicine, User $user, string $date, int $quantity): DispensingRecord
    {
        return DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensed_by' => $user->id, 'date_dispensed' => $date, 'quantity_dispensed' => $quantity]);
    }

    public function test_weekly_ols_projects_known_dispensing_totals_and_calendar_boundaries(): void
    {
        [$unit, $medicine, $user] = $this->fixture();
        foreach (['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'] as $index => $date) {
            $this->dispense($unit, $medicine, $user, $date, ($index + 1) * 10);
        }
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 100]);
        $result = app(MedicineUsageTrendService::class)->analyze($unit->id, null, $medicine->id, 'weekly', 4);

        $this->assertSame('ready', $result['status']);
        $this->assertSame('rising', $result['direction']);
        $this->assertSame([10, 20, 30, 40], array_column($result['history'], 'quantity'));
        $this->assertSame('2026-10-05', $result['history'][0]['start']);
        $this->assertSame('2026-11-01', $result['history'][3]['end']);
        $this->assertEquals(10, $result['fit']['slope']);
        $this->assertEquals(0, $result['fit']['intercept']);
        $this->assertEquals(1, $result['fit']['r_squared']);
        $this->assertEquals([50, 60, 70, 80], array_column($result['forecast'], 'quantity'));
        $this->assertSame('2026-11-02', $result['forecast'][0]['start']);
        $this->assertSame('2026-11-29', $result['forecast'][3]['end']);
        $this->assertSame(100, $result['total_quantity']);
        $this->assertSame(4, $result['total_transactions']);
        $this->assertEqualsWithDelta(100 / 3, $result['change_percent'], 0.00001);
        $this->assertEquals(260, $result['forecast_total']);
        $this->assertSame(160, $result['suggested_replenishment']);
    }

    public function test_monthly_ols_uses_completed_calendar_months_across_a_year_boundary(): void
    {
        [$unit, $medicine, $user] = $this->fixture();
        foreach (['2026-07-31', '2026-08-01', '2026-09-30', '2026-10-31'] as $index => $date) {
            $this->dispense($unit, $medicine, $user, $date, ($index + 1) * 10);
        }
        $this->dispense($unit, $medicine, $user, '2026-11-01', 999);
        $result = app(MedicineUsageTrendService::class)->analyze($unit->id, null, $medicine->id, 'monthly', 4);

        $this->assertSame([10, 20, 30, 40], array_column($result['history'], 'quantity'));
        $this->assertSame('2026-07-01', $result['history'][0]['start']);
        $this->assertSame('2026-10-31', $result['history'][3]['end']);
        $this->assertEquals([50, 60, 70, 80], array_column($result['forecast'], 'quantity'));
        $this->assertSame('2026-11-01', $result['forecast'][0]['start']);
        $this->assertSame('2027-02-28', $result['forecast'][3]['end']);
    }

    public function test_personal_and_facility_scopes_exclude_other_units_medicines_deleted_and_partial_period_records(): void
    {
        [$unit, $medicine, $user] = $this->fixture();
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $otherUnit = HealthUnit::create(['name' => 'Other usage RHU']);
        $otherMedicine = Medicine::create(['medicine_name' => 'Other formulation', 'category' => 'medicine']);
        $this->dispense($unit, $medicine, $user, '2026-10-26', 10);
        $this->dispense($unit, $medicine, $colleague, '2026-10-27', 20);
        $this->dispense($otherUnit, $medicine, $user, '2026-10-26', 999);
        $this->dispense($unit, $otherMedicine, $user, '2026-10-26', 999);
        $this->dispense($unit, $medicine, $user, '2026-10-28', 999)->delete();
        $this->dispense($unit, $medicine, $user, '2026-11-02', 999);
        $this->dispense($unit, $medicine, $user, '2026-11-09', 999);
        foreach (['allocation', 'transfer_out', 'adjustment'] as $type) {
            StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'quantity_added' => 999, 'date_added' => '2026-10-26', 'movement_type' => $type]);
        }
        $service = app(MedicineUsageTrendService::class);
        $personal = $service->analyze($unit->id, $user->id, $medicine->id, 'weekly', 4);
        $facility = $service->analyze($unit->id, null, $medicine->id, 'weekly', 4);

        $this->assertSame('personal', $personal['scope']);
        $this->assertSame(10, $personal['total_quantity']);
        $this->assertSame(1, $personal['total_transactions']);
        $this->assertNull($personal['suggested_replenishment']);
        $this->assertSame('rhu', $facility['scope']);
        $this->assertSame(30, $facility['total_quantity']);
        $this->assertSame(2, $facility['total_transactions']);
    }

    public function test_periods_before_recording_remain_unknown_and_later_empty_periods_mean_zero_recorded_dispensing(): void
    {
        [$unit, $medicine, $user] = $this->fixture();
        $this->dispense($unit, $medicine, $user, '2026-10-12', 10);
        $result = app(MedicineUsageTrendService::class)->analyze($unit->id, null, $medicine->id, 'weekly', 6);

        $this->assertSame([null, null, null, 10, 0, 0], array_column($result['history'], 'quantity'));
        $this->assertSame('insufficient_history', $result['status']);
        $this->assertSame(3, $result['observations']);
        $this->assertNull($result['fit']);
        $this->assertSame([null, null, null, null], array_column($result['forecast'], 'quantity'));
        $this->assertSame('no_baseline', $result['change_status']);
        $this->assertNull($result['change_percent']);
    }

    public function test_an_earlier_recording_start_is_retained_when_the_visible_window_has_no_dispensing(): void
    {
        [$unit, $medicine, $user] = $this->fixture();
        $this->dispense($unit, $medicine, $user, '2026-08-01', 10);
        $result = app(MedicineUsageTrendService::class)->analyze($unit->id, null, $medicine->id, 'weekly', 4);

        $this->assertSame([0, 0, 0, 0], array_column($result['history'], 'quantity'));
        $this->assertSame('ready', $result['status']);
        $this->assertSame('stable', $result['direction']);
        $this->assertEquals([0, 0, 0, 0], array_column($result['forecast'], 'quantity'));
        $this->assertSame('no_baseline', $result['change_status']);
    }

    public function test_current_usable_stock_excludes_expired_deleted_and_other_facility_batches(): void
    {
        [$unit, $medicine, $user] = $this->fixture();
        $other = HealthUnit::create(['name' => 'Other stock RHU']);
        foreach ([[10, null], [20, '2026-11-06'], [999, '2026-11-05']] as [$quantity, $expiry]) {
            Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'available_stock' => $quantity, 'expiration_date' => $expiry]);
        }
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 999])->delete();
        Inventory::create(['health_unit_id' => $other->id, 'medicine_id' => $medicine->id, 'available_stock' => 999]);
        $disease = Disease::create(['name' => 'Associated condition', 'code' => 'ASSOC']);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id, 'doses_per_case' => 2]);
        $result = app(MedicineUsageTrendService::class)->analyze($unit->id, null, $medicine->id);

        $this->assertSame(30, $result['usable_stock']);
        $this->assertSame([['id' => $disease->id, 'name' => 'Associated condition']], $result['related_diseases']);
        $this->assertSame('no_history', $result['status']);
        $this->assertSame('unavailable', $result['direction']);
        $this->assertNull($result['forecast_total']);
        $this->assertNull($result['suggested_replenishment']);
        $this->assertNull($result['current_quantity']);
        $this->assertSame('unavailable', $result['change_status']);
    }

    public function test_declining_projections_are_clamped_and_personal_usage_never_drives_facility_replenishment(): void
    {
        [$unit, $medicine, $user] = $this->fixture();
        foreach (['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'] as $index => $date) {
            $this->dispense($unit, $medicine, $user, $date, (4 - $index) * 10);
        }
        $result = app(MedicineUsageTrendService::class)->analyze($unit->id, $user->id, $medicine->id, 'weekly', 4);

        $this->assertSame('declining', $result['direction']);
        $this->assertEquals([0, 0, 0, 0], array_column($result['forecast'], 'quantity'));
        $this->assertNull($result['suggested_replenishment']);
    }
}
