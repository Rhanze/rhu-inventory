<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

class FrontendWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_transfer_form_uses_its_own_catalog_endpoint_with_complete_metadata(): void
    {
        config(['portals.enabled' => true, 'portals.public_domain' => 'public.test',
            'portals.management_domain' => 'management.test', 'portals.surface' => 'both', 'portals.https_only' => false]);
        app('router')->setRoutes(new RouteCollection);
        app('router')->middleware('web')->group(base_path('routes/web.php'));
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
        $unit = HealthUnit::create(['name' => 'Autocomplete RHU', 'facility_type' => 'Rural Health Unit']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Internal supply', 'dosage' => '10 mg', 'dosage_form' => 'tablet',
            'category' => 'medicine', 'unit' => 'tablet', 'requires_prescription' => true, 'is_dispensable' => false]);
        $this->withSession(['user_id' => $staff->id])->get(route('transfers.create'))->assertOk()
            ->assertDontSee('/search/public-autocomplete');
        $this->getJson(route('stock_logs.autocomplete', ['q' => 'Internal']))->assertOk()
            ->assertJsonPath('0.id', $medicine->id)->assertJsonPath('0.dosage_form', 'tablet')
            ->assertJsonPath('0.category', 'medicine')->assertJsonPath('0.unit', 'tablet')
            ->assertJsonPath('0.requires_prescription', true);
        $this->getJson(route('stock_logs.autocomplete', ['recommendations' => 1]))->assertOk()->assertJsonPath('0.id', $medicine->id);
        $medicine->delete();
        $this->getJson(route('stock_logs.autocomplete', ['recommendations' => 1]))->assertOk()->assertExactJson([]);
    }
}
