<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNavigationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_rhu_dashboard_only_shows_quick_actions_available_to_its_admin(): void
    {
        $unit = HealthUnit::create(['name' => 'Quick actions RHU', 'facility_type' => 'Rural Health Unit']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk()
            ->assertDontSee(route('medicine.create'))
            ->assertSee(route('inventory.create'))
            ->assertSee(route('allocation.create'))
            ->assertSee(route('dispensing.create'));
        $this->get(route('medicine.create'))->assertForbidden();
        $this->get(route('inventory.create'))->assertOk();
        $this->get(route('allocation.create'))->assertOk();
        $this->get(route('dispensing.create'))->assertOk();
    }

    public function test_rhu_staff_dashboard_links_to_stock_addition_within_its_permissions(): void
    {
        $unit = HealthUnit::create(['name' => 'Staff quick actions RHU', 'facility_type' => 'Rural Health Unit']);
        $viewer = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk()
            ->assertDontSee(route('inventory.create'))
            ->assertSee(route('stock_logs.create'));
        $this->get(route('inventory.create'))->assertRedirect(route('inventory.index'));
        $this->get(route('stock_logs.create'))->assertOk();
    }

    public function test_provincial_dashboard_does_not_offer_rhu_only_dispensing_actions(): void
    {
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $dashboard = $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk();
        $links = collect($dashboard->viewData('navLinks'))->keyBy(fn ($link) => $link[0]);

        $this->assertFalse($links->has('dispensing.index'));
        $this->assertFalse($links->has('reports.dispensing'));
        $this->get(route('reports.dispensing'))->assertRedirect(route('reports.index'));
    }

    public function test_dashboard_cards_lead_to_usable_pages_for_provincial_and_rhu_users(): void
    {
        $unit = HealthUnit::create(['name' => 'Dashboard navigation RHU', 'facility_type' => 'Rural Health Unit']);
        foreach ([['admin', null], ['admin', $unit->id], ['staff', $unit->id]] as [$role, $unitId]) {
            $viewer = User::factory()->create(['role' => $role, 'health_unit_id' => $unitId]);
            $dashboard = $this->withSession(['user_id' => $viewer->id])->get(route('dashboard'))->assertOk();
            foreach ($dashboard->viewData('navLinks') as [$routeName]) {
                $page = $this->get(route($routeName));
                for ($step = 0; $page->isRedirect() && $step < 3; $step++) {
                    $destination = $page->headers->get('Location');
                    $this->assertNotContains(parse_url($destination, PHP_URL_PATH), ['/dashboard', '/login'],
                        $role.' dashboard card '.$routeName.' must open its advertised module.');
                    $page = $this->get($destination);
                }
                $page->assertOk();
            }
        }
    }
}
