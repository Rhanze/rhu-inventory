<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminMfa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorMfaTest extends TestCase
{
    use RefreshDatabase;

    private const RFC_KEY = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_enrollment_retries_invalid_codes_without_saving_an_authenticator(): void
    {
        $administrator = User::factory()->create(['username' => 'fixture-admin', 'role' => 'admin']);

        $this->artisan('admin:mfa-enroll', ['username' => $administrator->username])
            ->expectsQuestion('Six-digit authenticator code', self::RFC_KEY)
            ->expectsQuestion('Six-digit authenticator code', '12345')
            ->expectsQuestion('Six-digit authenticator code', '1234567')
            ->expectsOutputToContain('Verification failed.')
            ->assertFailed();

        $this->assertNull($administrator->fresh()->mfa_secret);
    }

    public function test_administrator_password_cannot_establish_a_session_until_a_fresh_factor_is_verified(): void
    {
        config(['auth.require_admin_mfa' => true]);
        $this->travelTo(Carbon::createFromTimestamp(59));
        $administrator = User::factory()->create(['username' => 'fixture-admin', 'role' => 'admin',
            'health_unit_id' => null, 'password' => Hash::make('FixtureAdminOnly123!')]);
        $credentials = ['username' => $administrator->username, 'password' => 'FixtureAdminOnly123!'];

        $this->post(route('login.submit'), $credentials)->assertRedirect(route('login'))->assertSessionMissing('user_id');
        $administrator->mfa_secret = self::RFC_KEY;
        $administrator->save();
        $this->assertNotSame(self::RFC_KEY, DB::table('users')->where('id', $administrator->id)->value('mfa_secret'));

        $this->post(route('login.submit'), $credentials)->assertRedirect(route('login.verification'))->assertSessionMissing('user_id');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login.verification'))->assertOk()->assertSee('Authenticator code');
        $this->post(route('login.verify'), ['mfa_code' => '000000'])->assertSessionHasErrors('mfa_code')->assertSessionMissing('user_id');
        $this->post(route('login.verify'), ['mfa_code' => '287082'])->assertRedirect(route('dashboard'))
            ->assertSessionHas('mfa_verified', true)->assertSessionMissing('pending_mfa');
        $this->assertSame(1, $administrator->fresh()->mfa_last_step);

        $this->post(route('logout'));
        $this->post(route('login.submit'), $credentials)->assertRedirect(route('login.verification'));
        $this->post(route('login.verify'), ['mfa_code' => '287082'])->assertSessionHasErrors('mfa_code')->assertSessionMissing('user_id');
    }

    public function test_hosted_management_can_sign_in_an_unenrolled_administrator_with_a_password(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'auth.require_admin_mfa' => false,
            'portals.enabled' => true,
            'portals.surface' => 'management',
            'portals.https_only' => true,
            'portals.management_domain' => 'management.example.test',
        ]);
        app('router')->setRoutes(new RouteCollection);
        app('router')->middleware('web')->group(base_path('routes/web.php'));
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
        $password = 'FixtureAdminOnly123!';
        $administrator = User::factory()->create([
            'username' => 'hosted-admin', 'role' => 'admin', 'health_unit_id' => null,
            'password' => Hash::make($password), 'mfa_secret' => null,
        ]);
        $token = 'hosted-fixture-csrf-token';

        $this->withSession(['_token' => $token])->post('https://management.example.test/login', [
            '_token' => $token, 'username' => $administrator->username, 'password' => $password,
        ])->assertRedirect(route('dashboard'))->assertSessionHas('user_id', $administrator->id)
            ->assertSessionMissing('pending_mfa');

        $this->assertFalse(AdminMfa::required($administrator));
    }
}
