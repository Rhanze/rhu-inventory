<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPortalSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_opens_public_search_on_both_phone_and_desktop(): void
    {
        foreach (['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'] as $agent) {
            $this->withHeader('User-Agent', $agent)->get('/')->assertOk()->assertViewIs('public.home')
                ->assertDontSee('name="password"', false);
        }
        $this->get(route('public.home.citizen'))->assertOk()
            ->assertDontSee('>Home</a>', false)->assertDontSee('>Staff login</a>', false);
        $this->get(route('login'))->assertOk();
        $this->assertFalse(session()->has('user_id'));
    }

    public function test_guest_cannot_open_management_or_submit_inventory_changes(): void
    {
        foreach (['/dashboard', '/inventory', '/stock-logs', '/gis'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post(route('inventory.store'), [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('inventory', 0);
    }

    public function test_removed_public_map_and_its_data_endpoints_are_unavailable(): void
    {
        foreach (['/map', '/map/rhus', '/map/search-stock', '/map/medicine-search'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->assertFalse(app('router')->has('public.map'));
        $this->assertTrue(app('router')->has('gis.index'));
    }

    public function test_staff_can_use_public_search_and_reach_dashboard_through_login(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $user->id, 'role' => 'admin', 'health_unit_id' => null])
            ->get('/')->assertOk()->assertViewIs('public.home');
        $this->get(route('login'))->assertRedirect(route('dashboard'));
    }
}
