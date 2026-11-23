<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_midwife_whose_rhu_is_removed_loses_the_session_without_a_redirect_loop(): void
    {
        $unit = HealthUnit::create(['name' => 'Authentication RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $midwife->update(['health_unit_id' => null]);
        $this->withSession(['user_id' => $midwife->id, 'role' => 'midwife', 'health_unit_id' => $unit->id])
            ->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionMissing('user_id');
        $this->get(route('login'))->assertOk();
    }

    public function test_midwives_without_an_rhu_cannot_start_a_broken_login_session(): void
    {
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => null,
            'password' => Hash::make('LoginPass123!')]);
        $this->post(route('login.submit'), ['username' => $midwife->username, 'password' => 'LoginPass123!'])
            ->assertSessionHasErrors('username')->assertSessionMissing('user_id');
    }

    public function test_failed_logins_are_limited_then_expire_and_success_clears_the_limit(): void
    {
        $unit = HealthUnit::create(['name' => 'Throttle RHU']);
        $user = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id,
            'password' => Hash::make('LoginPass123!')]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'WrongPass123!'])
                ->assertSessionHasErrors('username')->assertSessionMissing('user_id');
        }
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'LoginPass123!'])
            ->assertSessionHasErrors(['username' => 'Too many login attempts. Please try again after 15 minutes.'])
            ->assertSessionMissing('user_id');
        $this->travel(16)->minutes();
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'LoginPass123!'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('user_id', $user->id);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'WrongPass123!'])
            ->assertSessionHasErrors(['username' => 'The provided credentials do not match our records.']);
    }

    public function test_new_bcrypt_passwords_cannot_silently_discard_a_suffix_or_multibyte_bytes(): void
    {
        config(['hashing.driver' => 'bcrypt']);
        $unit = HealthUnit::create(['name' => 'Password RHU']);
        $user = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $oldHash = $user->password;
        $this->withSession(['user_id' => $user->id]);
        foreach ([str_repeat('Aa1!', 19), str_repeat('é', 35).'Aa1!'] as $password) {
            $this->post(route('settings'), ['name' => $user->name,
                'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
            $this->assertSame($oldHash, $user->fresh()->password);
        }
    }

    public function test_password_change_requires_current_password_and_revokes_older_sessions(): void
    {
        $unit = HealthUnit::create(['name' => 'Password Change RHU']);
        $user = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id,
            'password' => Hash::make('OldFixturePass123!')]);
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'OldFixturePass123!'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('auth_version', 0);
        $oldSession = session()->getId();
        $oldToken = session()->token();
        $change = ['name' => 'Updated profile', 'password' => 'NewFixturePass456!', 'password_confirmation' => 'NewFixturePass456!'];

        $this->from(route('settings'))->post(route('settings'), $change)->assertSessionHasErrors('current_password');
        $this->post(route('settings'), $change + ['current_password' => 'IncorrectFixturePass!'])
            ->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input.current_password');
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertTrue(Hash::check('OldFixturePass123!', $user->fresh()->password));

        $this->post(route('settings'), $change + ['current_password' => 'OldFixturePass123!'])
            ->assertRedirect(route('settings'))->assertSessionHas('auth_version', 1);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertTrue(Hash::check('NewFixturePass456!', $user->fresh()->password));
        $this->get(route('settings'))->assertOk();

        $this->withSession(['user_id' => $user->id, 'auth_version' => 0])->get(route('settings'))
            ->assertRedirect(route('login'))->assertSessionMissing('user_id');
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'NewFixturePass456!'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('auth_version', 1);
    }

    public function test_admin_reset_or_role_change_revokes_target_sessions(): void
    {
        $unit = HealthUnit::create(['name' => 'Account Management RHU']);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $user = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $account = ['name' => $user->name, 'username' => $user->username,
            'health_unit_id' => $unit->id, 'role' => 'staff'];

        $this->withSession(['user_id' => $admin->id])->put(route('users.update', $user->id), $account + [
            'password' => 'ResetFixturePass123!', 'password_confirmation' => 'ResetFixturePass123!',
        ])->assertRedirect(route('users.index'));
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->withSession(['user_id' => $user->id])->get(route('settings'))
            ->assertRedirect(route('login'))->assertSessionMissing('user_id');
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'ResetFixturePass123!'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('auth_version', 1);

        $this->withSession(['user_id' => $admin->id, 'auth_version' => 0])
            ->put(route('users.update', $user->id), array_replace($account, ['role' => 'midwife']))
            ->assertRedirect(route('users.index'));
        $this->assertSame(2, $user->fresh()->auth_version);
        $this->withSession(['user_id' => $user->id, 'auth_version' => 1])->get(route('settings'))
            ->assertRedirect(route('login'))->assertSessionMissing('user_id');
    }

    public function test_admin_cannot_use_account_management_to_change_own_password(): void
    {
        $unit = HealthUnit::create(['name' => 'Profile Security RHU']);
        $admin = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $admin->id])->put(route('users.update', $admin->id), [
            'name' => $admin->name, 'username' => $admin->username, 'role' => 'admin', 'health_unit_id' => $unit->id,
            'password' => 'ChangedFixturePass123!', 'password_confirmation' => 'ChangedFixturePass123!',
        ])->assertNotFound();
        $this->assertSame($admin->password, $admin->fresh()->password);
    }
}
