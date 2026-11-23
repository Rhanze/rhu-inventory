<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReferenceExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_and_old_reference_links_show_only_the_provincial_inventory(): void
    {
        $unit = HealthUnit::create(['name' => 'Local Antique RHU']);
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
            $this->withSession(['user_id' => $user->id]);
            foreach ([[], ['view' => 'published', 'month' => '2023-06', 'item' => 3], ['view' => 'history']] as $query) {
                $this->get(route('inventory.index', $query))->assertOk()
                    ->assertViewIs(($query['view'] ?? '') === 'history' ? 'inventory.history' : 'inventory.index')
                    ->assertDontSee('Published stock reports')
                    ->assertDontSee('Manuel A. Roxas District Hospital')
                    ->assertDontSee('provinceofisabela.ph');
            }
        }
        $this->assertDatabaseCount('inventory', 0);
        $this->assertDatabaseCount('medicines', 0);
        $this->assertDatabaseCount('dispensing_records', 0);
        $this->assertDatabaseCount('stock_logs', 0);
    }
}
