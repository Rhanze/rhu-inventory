<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class NavigationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_get_routes_without_record_parameters_handle_all_supported_roles_without_server_errors(): void
    {
        $unit = HealthUnit::create(['name' => 'Navigation RHU', 'facility_type' => 'Rural Health Unit']);
        $actors = [
            User::factory()->create(['role' => 'admin', 'health_unit_id' => null]),
            User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]),
            User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]),
            User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]),
        ];
        foreach ($actors as $actor) {
            foreach (app('router')->getRoutes() as $route) {
                if (! $route->getName() || ! in_array('GET', $route->methods(), true) || $route->parameterNames()) {
                    continue;
                }
                $this->withSession(['user_id' => $actor->id, 'role' => $actor->role, 'health_unit_id' => $actor->health_unit_id]);
                $response = $this->get(route($route->getName()));
                $this->assertLessThan(500, $response->getStatusCode(), $route->getName().' failed for '.$actor->role.' / '.($actor->health_unit_id ?? 'PHO'));
                if ($response->baseResponse instanceof StreamedResponse && $response->isSuccessful()) {
                    $response->streamedContent();
                }
            }
        }
    }
}
