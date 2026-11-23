<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_and_errors_send_browser_security_headers(): void
    {
        foreach (['/public', '/missing-page', '/public?q[]=invalid'] as $path) {
            $this->get($path)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'same-origin')
                ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'; object-src 'none'");
        }
    }

    public function test_login_pages_and_failed_submissions_cannot_be_cached(): void
    {
        $this->get('/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/login', [])->assertRedirect()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_authenticated_pages_cannot_be_cached(): void
    {
        $unit = HealthUnit::create(['name' => 'Private page RHU']);
        $user = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);

        $this->withSession(['user_id' => $user->id, 'role' => 'staff', 'health_unit_id' => $unit->id])
            ->get('/settings')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }
}
