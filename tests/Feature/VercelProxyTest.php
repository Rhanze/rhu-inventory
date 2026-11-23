<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VercelProxyTest extends TestCase
{
    use RefreshDatabase;

    private string|false $previousVercel;

    protected function setUp(): void
    {
        $this->previousVercel = getenv('VERCEL');
        putenv('VERCEL=1');
        parent::setUp();
        config(['portals.enabled' => true, 'portals.public_domain' => 'rhu.test',
            'portals.management_domain' => 'management.rhu.test', 'portals.surface' => 'management',
            'portals.https_only' => true]);
        app('router')->setRoutes(new RouteCollection);
        app('router')->middleware('web')->group(base_path('routes/web.php'));
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10']);
        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443']);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            putenv($this->previousVercel === false ? 'VERCEL' : 'VERCEL='.$this->previousVercel);
        }
    }

    public function test_failed_logins_do_not_lock_out_another_client_behind_the_vercel_proxy(): void
    {
        $unit = HealthUnit::create(['name' => 'Proxy RHU']);
        $user = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id,
            'password' => Hash::make('ProxyPass123!')]);

        $this->withHeader('X-Forwarded-For', '203.0.113.10');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('http://management.rhu.test/login', ['username' => $user->username, 'password' => 'WrongPass123!'])
                ->assertSessionHasErrors('username')->assertSessionMissing('user_id');
        }
        $this->post('http://management.rhu.test/login', ['username' => $user->username, 'password' => 'ProxyPass123!'])
            ->assertSessionHasErrors(['username' => 'Too many login attempts. Please try again after 15 minutes.']);

        $this->withHeader('X-Forwarded-For', '203.0.113.11')
            ->post('http://management.rhu.test/login', ['username' => $user->username, 'password' => 'ProxyPass123!'])
            ->assertRedirect('https://management.rhu.test/dashboard')->assertSessionHas('user_id', $user->id);
    }

    public function test_forwarded_host_cannot_change_the_allowed_portal_or_generated_urls(): void
    {
        $this->withHeader('X-Forwarded-Host', 'unconfigured.test')
            ->get('http://management.rhu.test/login')->assertOk()
            ->assertSee('https://management.rhu.test/login', false)->assertDontSee('unconfigured.test');
        $this->withHeader('X-Forwarded-Host', 'management.rhu.test')
            ->get('http://rhu.test/login')->assertNotFound();
    }
}
