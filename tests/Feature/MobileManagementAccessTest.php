<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use App\Support\ManagementDeviceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE_AGENT = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0.0.0 Mobile Safari/537.36';

    private const DESKTOP_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36';

    public function test_mobile_admins_and_staff_cannot_sign_in_or_start_a_session(): void
    {
        $unit = HealthUnit::create(['name' => 'Mobile access RHU']);
        $this->withHeader('User-Agent', self::MOBILE_AGENT);

        foreach ([['admin', null], ['admin', $unit->id], ['staff', $unit->id]] as [$role, $unitId]) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unitId]);
            $this->from(route('login'))->post(route('login.submit'), [
                'username' => $user->username, 'password' => 'Password!123',
            ])->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username' => ManagementDeviceAccess::MOBILE_MESSAGE])
                ->assertSessionHasInput('username', $user->username)
                ->assertSessionMissing('user_id');
        }

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_mobile_midwives_can_sign_in_and_use_shared_workflows(): void
    {
        $unit = HealthUnit::create(['name' => 'Mobile midwife RHU']);
        $user = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->withHeader('User-Agent', self::MOBILE_AGENT)
            ->post(route('login.submit'), ['username' => $user->username, 'password' => 'Password!123'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('user_id', $user->id);

        $this->get(route('dashboard'))->assertRedirect(route('dashboard.midwife'));
        $this->get(route('dashboard.midwife'))->assertOk();
        $this->post(route('settings'), ['name' => 'Updated midwife'])->assertRedirect(route('settings'));
        $this->assertSame('Updated midwife', $user->fresh()->name);
        $this->getJson(route('notifications.unread'))->assertOk();
    }

    public function test_existing_mobile_sessions_are_blocked_using_the_current_database_role(): void
    {
        $unit = HealthUnit::create(['name' => 'Existing mobile session RHU']);
        $this->withHeader('User-Agent', self::MOBILE_AGENT);

        foreach ([['admin', null], ['admin', $unit->id], ['staff', $unit->id]] as [$role, $unitId]) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unitId]);
            // A stale midwife role in the session must not bypass the restriction.
            $this->withSession(['user_id' => $user->id, 'role' => 'midwife']);
            foreach (['dashboard', 'dashboard.midwife', 'dispensing.index', 'dispensing.export',
                'medicine.index', 'settings'] as $route) {
                $this->get(route($route))->assertForbidden()->assertViewIs('auth.desktop-required')
                    ->assertSee(ManagementDeviceAccess::MOBILE_MESSAGE)
                    ->assertSee('Sign out')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            }
            $this->getJson(route('notifications.unread'))->assertForbidden()
                ->assertExactJson(['message' => ManagementDeviceAccess::MOBILE_MESSAGE]);
            $this->post(route('settings'), ['name' => 'Blocked name'])->assertForbidden();
            $this->assertSame($user->name, $user->fresh()->name);
        }
    }

    public function test_blocked_mobile_users_can_sign_out_and_public_pages_remain_available(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withHeader('User-Agent', self::MOBILE_AGENT)->withSession(['user_id' => $user->id]);
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('user_id');
        $this->get(route('login'))->assertOk()->assertDontSee(ManagementDeviceAccess::MOBILE_MESSAGE);
        $this->get(route('public.home'))->assertOk();
        $this->get(route('privacy'))->assertOk();
    }

    public function test_desktop_admins_and_staff_can_still_sign_in_and_access_management(): void
    {
        $unit = HealthUnit::create(['name' => 'Desktop access RHU']);
        $this->withHeader('User-Agent', self::DESKTOP_AGENT);

        foreach ([['admin', null], ['admin', $unit->id], ['staff', $unit->id]] as [$role, $unitId]) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unitId]);
            $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'Password!123'])
                ->assertRedirect(route('dashboard'))->assertSessionHas('user_id', $user->id);
            $this->get(route('notifications.index'))->assertOk();
            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }
}
