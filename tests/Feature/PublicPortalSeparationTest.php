<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPortalSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_offers_public_search_and_separate_staff_login(): void
    {
        $this->get('/')->assertOk()->assertSee('No account or login required.')
            ->assertSee('Management portal')->assertSee(route('public.home.citizen'), false)
            ->assertSee(route('login'), false);
        $this->get(route('public.home.citizen'))->assertOk();
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

    public function test_signed_in_user_can_still_choose_public_search_or_own_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $user->id, 'role' => 'admin', 'health_unit_id' => null])
            ->get('/')->assertOk()->assertSee('Search medicines')->assertSee('Open your dashboard')
            ->assertSee(route('dashboard'), false);
    }
}
