<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobilePortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_midwife_keeps_one_mobile_navigation_bar_across_shared_workflows(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148 Safari/604.1');
        $unit = HealthUnit::create(['name' => 'Mobile navigation RHU', 'facility_type' => 'Rural Health Unit']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);

        foreach (['dashboard.midwife', 'dispensing.index', 'dispensing.create', 'public_users.index',
            'public_users.create', 'notifications.index', 'supply_requests.index', 'supply_requests.create',
            'prenatal.index', 'postpartum.index', 'family_planning.index', 'immunization.tcl'] as $route) {
            $response = $this->withSession(['user_id' => $midwife->id])->get(route($route))->assertOk();
            $response->assertSee('aria-label="Midwife quick navigation"', false)
                ->assertSee('aria-controls="app-sidebar"', false)
                ->assertSee('aria-label="Close navigation"', false);
            $this->assertSame(1, substr_count($response->getContent(), 'aria-label="Midwife quick navigation"'), $route);
        }
    }

    public function test_management_roles_and_public_pages_do_not_receive_midwife_mobile_controls(): void
    {
        $unit = HealthUnit::create(['name' => 'Desktop navigation RHU', 'facility_type' => 'Rural Health Unit']);
        foreach ([['admin', null], ['admin', $unit->id], ['staff', $unit->id]] as [$role, $unitId]) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unitId]);
            $this->withSession(['user_id' => $user->id])->get(route('notifications.index'))->assertOk()
                ->assertDontSee('aria-label="Midwife quick navigation"', false)
                ->assertDontSee('id="midwife-menu-toggle"', false)
                ->assertDontSee('data-midwife-menu-close', false);
        }
        $this->flushSession();
        $this->get('/')->assertOk()->assertDontSee('id="midwife-menu-toggle"', false);
    }
}
