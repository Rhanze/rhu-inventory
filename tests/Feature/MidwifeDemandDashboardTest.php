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

class MidwifeDemandDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_automatically_shows_demand_for_the_primary_assigned_area_without_a_case_form(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Dashboard RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Secondary area']);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Primary area', 'is_primary' => true]);
        $medicine = Medicine::create(['medicine_name' => 'Local demand medicine', 'is_dispensable' => true, 'unit' => 'tablet']);
        $disease = Disease::create(['name' => 'Associated condition', 'code' => 'DASH', 'is_active' => true]);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);
        foreach (['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'] as $index => $date) {
            DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'dispensed_by' => $colleague->id, 'barangay' => 'Primary area',
                'date_dispensed' => $date, 'quantity_dispensed' => $index === 4 ? 30 : 10]);
        }
        $response = $this->withSession(['user_id' => $midwife->id])->get(route('dashboard.midwife', ['barangay' => 'Unassigned area']));
        $response->assertOk()->assertViewHas('demandBarangay', 'Primary area')
            ->assertViewHas('areaDemand', fn ($demand) => $demand['location'] === 'Primary area' && $demand['overview']['elevated'] === 1)
            ->assertSee('Disease trend signals')->assertSee('Primary area')->assertSee('Secondary area')
            ->assertSee('Local demand medicine')->assertSee('Associated condition')->assertSee('Higher demand')
            ->assertSee('2026-10-26')->assertSee('2026-11-01')->assertSee('Higher consumption of medicines linked to a condition')
            ->assertDontSee('name="case_count"', false)->assertDontSee('diseaseTrendChart')
            ->assertDontSee('Outbreak Alerts')->assertDontSee('Cases / Month');
    }

    public function test_unassigned_midwife_sees_assignment_guidance_instead_of_guessed_area_demand(): void
    {
        $unit = HealthUnit::create(['name' => 'Unassigned dashboard RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $midwife->id])->get(route('dashboard.midwife'))
            ->assertOk()->assertViewHas('areaDemand', null)->assertViewHas('demandBarangay', null)
            ->assertSee('No barangay is assigned')->assertSee('RHU administrator')->assertSee('Record medicine quantities');
    }
}
