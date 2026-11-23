<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiseaseMedicinePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
    }

    public function test_rhu_page_uses_facility_dispensing_and_explains_the_model_even_without_diseases(): void
    {
        $unit = HealthUnit::create(['name' => 'Trend RHU']);
        $otherUnit = HealthUnit::create(['name' => 'Other RHU']);
        $staff = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $midwife = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $other = User::factory()->create(['health_unit_id' => $otherUnit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'Trend medicine', 'dosage' => '10 mg',
            'dosage_form' => 'tablet', 'unit' => 'tablet', 'is_dispensable' => true]);
        foreach ([$staff, $midwife, $other] as $user) {
            DispensingRecord::create(['health_unit_id' => $user->health_unit_id, 'medicine_id' => $medicine->id,
                'dispensed_by' => $user->id, 'quantity_dispensed' => $user->id === $other->id ? 999 : 10,
                'date_dispensed' => '2026-10-26']);
        }

        $this->withSession(['user_id' => $staff->id])->get(route('disease_reports.index', [
            'medicine_id' => $medicine->id, 'periods' => 4, 'health_unit_id' => $otherUnit->id,
        ]))->assertOk()->assertViewHas('usage', fn ($usage) => $usage['total_quantity'] === 20 && $usage['scope'] === 'rhu')
            ->assertSee('Medicine consumption details')->assertSee('About these estimates')
            ->assertDontSee('y = a + bt')->assertDontSee('Mathematical model')
            ->assertSee('Disease Trend Analysis')->assertDontSee('Disease case reports')->assertDontSee('id="case-trend-details"', false);

        $this->get(route('disease_reports.index', ['view' => 'reports']))->assertOk()
            ->assertViewHas('activeView', 'demand')->assertSee('Disease signals from medicine consumption')->assertDontSee('Confirmed disease case trends');

        $this->withSession(['user_id' => $midwife->id])->get(route('disease_reports.index', [
            'medicine_id' => $medicine->id, 'dispensed_by' => $staff->id,
        ]))
            ->assertOk()->assertViewHas('usage', fn ($usage) => $usage['total_quantity'] === 10
                && $usage['scope'] === 'personal' && $usage['suggested_replenishment'] === null);
    }

    public function test_usage_filters_reject_invalid_inputs_and_hidden_medicines(): void
    {
        $unit = HealthUnit::create(['name' => 'Validation RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'admin']);
        $internal = Medicine::create(['medicine_name' => 'Internal only', 'is_dispensable' => false]);
        $deleted = Medicine::create(['medicine_name' => 'Removed', 'is_dispensable' => true]);
        $deleted->delete();
        $this->withSession(['user_id' => $user->id]);
        foreach ([$internal->id, $deleted->id, 999999] as $medicineId) {
            $this->getJson(route('disease_reports.index', ['medicine_id' => $medicineId]))
                ->assertUnprocessable()->assertJsonValidationErrors('medicine_id');
        }
        $this->getJson(route('disease_reports.index', ['period_type' => 'daily', 'periods' => 3]))
            ->assertUnprocessable()->assertJsonValidationErrors(['period_type', 'periods']);
    }

    public function test_empty_catalog_renders_and_guests_and_unassigned_pho_cannot_read_rhu_trends(): void
    {
        $this->get(route('disease_reports.index'))->assertRedirect(route('login'));
        $unit = HealthUnit::create(['name' => 'Empty RHU']);
        $staff = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $this->withSession(['user_id' => $staff->id])->get(route('disease_reports.index'))
            ->assertOk()->assertViewHas('usage', null);
        $pho = User::factory()->create(['health_unit_id' => null, 'role' => 'admin']);
        $this->withSession(['user_id' => $pho->id])->get(route('disease_reports.index'))->assertForbidden();
    }
}
