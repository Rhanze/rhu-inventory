<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalDomainSplitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.enabled' => true, 'portals.public_domain' => 'rhu.test',
            'portals.management_domain' => 'management.rhu.test', 'portals.surface' => 'both', 'portals.https_only' => false]);
        app('router')->setRoutes(new RouteCollection);
        app('router')->middleware('web')->group(base_path('routes/web.php'));
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }

    public function test_public_search_and_management_login_are_on_distinct_hosts(): void
    {
        $this->get('http://rhu.test/')->assertOk()->assertSee('Search')
            ->assertSee('http://management.rhu.test/login', false)->assertDontSee('Management portal');
        $this->get('http://management.rhu.test/')->assertRedirect('http://management.rhu.test/login');
        $this->get('http://management.rhu.test/login')->assertOk();
        foreach (['/login', '/dashboard', '/inventory', '/public-users/search-suggestions'] as $path) {
            $this->get('http://rhu.test'.$path)->assertNotFound();
        }
        $this->post('http://rhu.test/login', [])->assertNotFound();
        $this->post('http://rhu.test/inventory/store', [])->assertNotFound();
        $this->get('http://management.rhu.test/public')->assertNotFound();
        $this->get('http://unconfigured.test/login')->assertNotFound();
    }

    public function test_management_authentication_and_role_redirects_still_apply(): void
    {
        $this->get('http://management.rhu.test/inventory')->assertRedirect('http://management.rhu.test/login');
        $this->get('http://management.rhu.test/public-users/search-suggestions')->assertRedirect('http://management.rhu.test/login');
        $unit = HealthUnit::create(['name' => 'Domain test RHU']);
        $user = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $user->id, 'role' => 'midwife', 'health_unit_id' => $unit->id])
            ->get('http://management.rhu.test/dashboard')->assertRedirect('http://management.rhu.test/midwife/dashboard');
        $this->get('http://rhu.test/dashboard')->assertNotFound();
    }

    public function test_valid_staff_login_redirects_on_management_domain(): void
    {
        $unit = HealthUnit::create(['name' => 'Login RHU']);
        $user = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id,
            'password' => Hash::make('PortalPass123!'), 'is_active' => true]);
        $this->post('http://management.rhu.test/login', ['username' => $user->username, 'password' => 'PortalPass123!'])
            ->assertRedirect('http://management.rhu.test/dashboard')->assertSessionHas('user_id', $user->id);
    }

    public function test_cookie_names_differ_and_never_share_a_parent_domain(): void
    {
        config(['session.domain' => '.rhu.test']);
        $public = $this->get('http://rhu.test/');
        $this->assertSame('rhu_public_session', config('session.cookie'));
        $this->assertNull(config('session.domain'));
        $this->assertContains('rhu_public_session', array_map(fn ($cookie) => $cookie->getName(), $public->headers->getCookies()));
        foreach ($public->headers->getCookies() as $cookie) {
            $this->assertNull($cookie->getDomain());
        }
        session()->put('public_portal_marker', 'must not cross hosts');
        $management = $this->get('http://management.rhu.test/login')->assertSessionMissing('public_portal_marker');
        $this->assertContains('rhu_management_session', array_map(fn ($cookie) => $cookie->getName(), $management->headers->getCookies()));
        $this->assertSame('rhu_management_session', config('session.cookie'));
        $this->assertNull(config('session.domain'));
        $this->assertTrue(config('session.http_only'));
    }

    public function test_independent_deployments_reject_the_other_surface(): void
    {
        config(['portals.surface' => 'public']);
        $this->get('http://management.rhu.test/login')->assertNotFound();
        $this->get('http://rhu.test/')->assertOk();
        config(['portals.surface' => 'management']);
        $this->get('http://rhu.test/')->assertNotFound();
        $this->get('http://management.rhu.test/login')->assertOk();
    }

    public function test_https_mode_requires_tls_and_uses_host_prefixed_secure_cookies(): void
    {
        config(['portals.https_only' => true]);
        $this->get('http://management.rhu.test/login')->assertStatus(400);
        $response = $this->get('https://management.rhu.test/login')->assertOk();
        $this->assertSame('__Host-rhu_management_session', config('session.cookie'));
        $this->assertTrue(config('session.secure'));
        $this->assertNull(config('session.domain'));
        $this->assertContains('__Host-rhu_management_session', array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies()),
            implode(', ', array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies())));
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === '__Host-rhu_management_session') {
                $this->assertTrue($cookie->isSecure());
                $this->assertTrue($cookie->isHttpOnly());
                $this->assertSame('/', $cookie->getPath());
            }
        }
    }

    public function test_production_rejects_shared_surface_insecure_mode_and_disabled_split(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['portals.https_only' => true, 'portals.surface' => 'both']);
        $this->get('https://management.rhu.test/login')->assertStatus(503);
        config(['portals.surface' => 'management', 'portals.https_only' => false]);
        $this->get('http://management.rhu.test/login')->assertStatus(503);
        config(['portals.enabled' => false]);
        $this->get('http://management.rhu.test/login')->assertStatus(503);
    }
}
