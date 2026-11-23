<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaDemandPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_midwife_sees_automatic_assigned_area_signals_without_case_or_patient_records(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Area RHU']);
        $otherUnit = HealthUnit::create(['name' => 'Other RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Assigned Village', 'is_primary' => true]);
        $medicine = Medicine::create(['medicine_name' => 'Area medicine', 'dosage' => '10 mg', 'unit' => 'tablet', 'is_dispensable' => true]);
        $disease = Disease::create(['name' => 'Associated condition', 'code' => 'AREA', 'is_active' => true]);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);
        foreach (['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'] as $index => $date) {
            DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'dispensed_by' => $colleague->id, 'date_dispensed' => $date, 'barangay' => 'Assigned Village',
                'quantity_dispensed' => $index === 4 ? 20 : 10]);
        }
        DispensingRecord::create(['medicine_id' => $medicine->id, 'health_unit_id' => $otherUnit->id,
            'dispensed_by' => $midwife->id, 'date_dispensed' => '2026-10-26', 'barangay' => 'Assigned Village', 'quantity_dispensed' => 999]);

        $this->withSession(['user_id' => $midwife->id])->get(route('disease_reports.index', ['consumption_scope' => 'area']))
            ->assertOk()->assertViewHas('selectedBarangay', 'Assigned Village')
            ->assertViewHas('diseaseTrends', fn ($demand) => $demand['overview']['elevated'] === 1
                && $demand['signals'][0]['medicines'][0]['current_quantity'] === 20)
            ->assertViewHas('usage', fn ($usage) => $usage['scope'] === 'area' && $usage['total_quantity'] === 60
                && $usage['suggested_replenishment'] === null)
            ->assertSee('Associated condition')->assertSee('Possible rising trend')
            ->assertDontSee('y = a + bt')->assertDontSee('Mathematical model');
        $this->assertDatabaseCount('disease_reports', 0);
        $this->assertDatabaseCount('public_users', 0);
        $this->get(route('disease_reports.index', ['barangay' => 'Unassigned Village']))->assertForbidden();
    }

    public function test_midwife_without_assignment_gets_no_guessed_area_and_staff_cannot_select_another_rhu_area(): void
    {
        $unit = HealthUnit::create(['name' => 'No area RHU']);
        $otherUnit = HealthUnit::create(['name' => 'Outside RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $otherMidwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $otherUnit->id]);
        MidwifeBarangay::create(['user_id' => $otherMidwife->id, 'barangay' => 'Outside Village']);
        $this->withSession(['user_id' => $midwife->id])->get(route('disease_reports.index'))
            ->assertOk()->assertViewHas('selectedBarangay', null)->assertViewHas('diseaseTrends', fn ($result) => $result['scope'] === 'personal');
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $staff->id])->get(route('disease_reports.index', ['barangay' => 'Outside Village']))
            ->assertForbidden();
    }
}
