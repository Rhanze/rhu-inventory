<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\MidwifeBarangay;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidwifeBarangayManagementTest extends TestCase
{
    use RefreshDatabase;

    private function signInAdmin(HealthUnit $unit): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $admin->id]);

        return $admin;
    }

    public function test_rhu_admin_sees_midwife_barangays_primary_and_unassigned_status_without_other_rhu_users(): void
    {
        $unit = HealthUnit::create(['name' => 'Assignment RHU']);
        $other = HealthUnit::create(['name' => 'Other assignment RHU']);
        $this->signInAdmin($unit);
        $midwife = User::factory()->create(['name' => 'Assigned midwife', 'role' => 'midwife', 'health_unit_id' => $unit->id]);
        User::factory()->create(['name' => 'Unassigned midwife', 'role' => 'midwife', 'health_unit_id' => $unit->id]);
        $outsider = User::factory()->create(['name' => 'Outside midwife', 'role' => 'midwife', 'health_unit_id' => $other->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Local Barangay', 'is_primary' => true]);
        MidwifeBarangay::create(['user_id' => $outsider->id, 'barangay' => 'Outside Barangay']);

        $this->get(route('users.index', ['role_filter' => 'midwife']))->assertOk()
            ->assertSee('Assigned Barangays')->assertSee('Local Barangay')->assertSee('Primary')->assertSee('Not assigned')
            ->assertDontSee('Outside Barangay')->assertDontSee('Outside midwife')
            ->assertViewHas('users', fn ($users) => $users->every(fn ($user) => $user->role === 'midwife'
                && $user->relationLoaded('assignedBarangays')));
    }

    public function test_new_barangays_can_be_assigned_without_registering_any_patients(): void
    {
        $unit = HealthUnit::create(['name' => 'New area RHU']);
        $this->signInAdmin($unit);
        $this->get(route('users.create'))->assertOk()->assertSee('name="barangay_names"', false)
            ->assertSee('before registering any patients');
        $this->post(route('users.store'), ['name' => 'New Area Midwife', 'username' => 'newareamidwife',
            'password' => 'AreaPassword123!', 'password_confirmation' => 'AreaPassword123!', 'role' => 'midwife',
            'health_unit_id' => $unit->id, 'barangay_names' => "  San Jose  \n san jose\nPoblacion",
            'primary_barangay' => 'SAN JOSE'])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $midwife = User::where('username', 'newareamidwife')->firstOrFail();
        $this->assertSame(0, PublicUser::count());
        $this->assertSame(2, $midwife->assignedBarangays()->count());
        $this->assertDatabaseHas('midwife_barangay', ['user_id' => $midwife->id, 'barangay' => 'San Jose', 'is_primary' => true]);
        $this->get(route('users.edit', $midwife->id))->assertOk()->assertSee('San Jose')->assertSee('Poblacion');
    }

    public function test_updating_assignments_demotes_a_primary_only_in_the_same_rhu(): void
    {
        $unit = HealthUnit::create(['name' => 'Primary RHU']);
        $other = HealthUnit::create(['name' => 'Other primary RHU']);
        $this->signInAdmin($unit);
        $oldPrimary = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $target = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $outside = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $other->id]);
        foreach ([$oldPrimary, $outside] as $user) {
            MidwifeBarangay::create(['user_id' => $user->id, 'barangay' => 'Poblacion', 'is_primary' => true]);
        }
        MidwifeBarangay::create(['user_id' => $target->id, 'barangay' => 'Old assignment']);
        $this->put(route('users.update', $target->id), ['name' => $target->name, 'username' => $target->username,
            'role' => 'midwife', 'health_unit_id' => $unit->id, 'barangay_names' => 'Poblacion',
            'primary_barangay' => 'Poblacion'])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();

        $this->assertFalse($oldPrimary->assignedBarangays()->firstOrFail()->is_primary);
        $this->assertTrue($outside->assignedBarangays()->firstOrFail()->is_primary);
        $this->assertSame(['Poblacion'], $target->assignedBarangays()->pluck('barangay')->all());
        $this->assertTrue($target->assignedBarangays()->firstOrFail()->is_primary);
        $this->get(route('users.edit', $outside->id))->assertNotFound();
    }

    public function test_invalid_primary_and_long_barangay_name_do_not_replace_existing_assignments(): void
    {
        $unit = HealthUnit::create(['name' => 'Validation area RHU']);
        $this->signInAdmin($unit);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Original']);
        $data = ['name' => $midwife->name, 'username' => $midwife->username, 'role' => 'midwife', 'health_unit_id' => $unit->id];
        $this->put(route('users.update', $midwife->id), $data + ['barangay_names' => 'Poblacion', 'primary_barangay' => 'Other'])
            ->assertSessionHasErrors('primary_barangay');
        $this->put(route('users.update', $midwife->id), $data + ['barangay_names' => str_repeat('a', 101)])
            ->assertSessionHasErrors('barangay_names');
        $this->assertSame(['Original'], $midwife->assignedBarangays()->pluck('barangay')->all());
    }

    public function test_list_delimiters_numbering_and_bullets_are_rejected_without_replacing_assignments(): void
    {
        $unit = HealthUnit::create(['name' => 'Line validation RHU']);
        $this->signInAdmin($unit);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Original']);
        $payload = ['name' => $midwife->name, 'username' => $midwife->username, 'role' => 'midwife', 'health_unit_id' => $unit->id];

        foreach (['San Jose,Poblacion', 'San Jose;Poblacion', 'San Jose | Poblacion', 'San Jose / Poblacion',
            "San Jose\tPoblacion", "1. San Jose\n2. Poblacion", "- San Jose\n- Poblacion", '• San Jose',
            '(1) San Jose', 'a) San Jose'] as $names) {
            $this->put(route('users.update', $midwife->id), $payload + ['barangay_names' => $names])
                ->assertSessionHasErrors('barangay_names');
            $this->assertSame(['Original'], $midwife->assignedBarangays()->pluck('barangay')->all());
        }
    }

    public function test_valid_legacy_checkbox_arrays_still_normalize_and_merge_with_one_name_per_line(): void
    {
        $unit = HealthUnit::create(['name' => 'Checkbox compatibility RHU']);
        $this->signInAdmin($unit);
        $this->post(route('users.store'), ['name' => 'Legacy form midwife', 'username' => 'legacycheckboxmidwife',
            'password' => 'AreaPassword123!', 'password_confirmation' => 'AreaPassword123!', 'role' => 'midwife',
            'health_unit_id' => $unit->id, 'barangays' => ['  San   Jose ', 'san jose', 'Barangay 1'],
            'barangay_names' => "Poblacion\r\n  Poblacion  ", 'primary_barangay' => 'San Jose'])
            ->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

        $midwife = User::where('username', 'legacycheckboxmidwife')->firstOrFail();
        $this->assertSame(['San Jose', 'Barangay 1', 'Poblacion'], $midwife->assignedBarangays()->orderBy('id')->pluck('barangay')->all());
        $this->put(route('users.update', $midwife->id), ['name' => $midwife->name, 'username' => $midwife->username,
            'role' => 'midwife', 'health_unit_id' => $unit->id, 'barangays' => null, 'barangay_names' => "San Jose\nPoblacion"])
            ->assertSessionHasNoErrors()->assertRedirect(route('users.index'));
        $this->assertSame(['San Jose', 'Poblacion'], $midwife->assignedBarangays()->orderBy('id')->pluck('barangay')->all());
    }
}
