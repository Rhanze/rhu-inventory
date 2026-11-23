<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_rhu_is_selectable_for_public_search_and_staff_account_assignment(): void
    {
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $pho->id])->post(route('rhu.store'), [
            'name' => 'New public RHU', 'municipality' => 'San Jose', 'address' => 'Fixture address',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $unit = HealthUnit::where('name', 'New public RHU')->firstOrFail();
        $this->assertSame('Rural Health Unit', $unit->facility_type);
        $this->get(route('public.home.citizen'))->assertOk()->assertSee('New public RHU');
        $this->get(route('users.create'))->assertOk()->assertViewHas('healthUnits', fn ($units) => $units->contains('id', $unit->id));
    }

    public function test_explicit_false_prescription_flags_are_preserved_on_create_and_update(): void
    {
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $pho->id]);
        $fields = ['medicine_name' => 'Flag fixture', 'description' => 'Regression fixture',
            'category' => 'medicine', 'dosage' => '10 mg', 'dosage_form' => 'Tablet'];

        $this->post(route('medicine.store'), $fields + ['requires_prescription' => '0'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $medicine = Medicine::where('medicine_name', 'Flag fixture')->firstOrFail();
        $this->assertFalse($medicine->requires_prescription);

        $this->put(route('medicine.update', $medicine->id), $fields + ['requires_prescription' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($medicine->fresh()->requires_prescription);
        $this->put(route('medicine.update', $medicine->id), $fields + ['requires_prescription' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($medicine->fresh()->requires_prescription);
        $this->put(route('medicine.update', $medicine->id), $fields)->assertSessionHasNoErrors();
        $this->assertFalse($medicine->fresh()->requires_prescription);
    }
}
