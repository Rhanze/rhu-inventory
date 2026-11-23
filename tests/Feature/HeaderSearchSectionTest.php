<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\ForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderSearchSectionTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'admin'): array
    {
        $unit = HealthUnit::create(['name' => 'Search local RHU']);
        $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user];
    }

    public function test_empty_selected_sections_never_fall_back_to_medicine_or_all_results(): void
    {
        $this->signIn();
        Medicine::create(['medicine_name' => 'Search matching catalogue only']);

        foreach (['transfers' => 'transferResults', 'forecast' => 'forecastResults', 'inventory' => 'inventoryResults'] as $scope => $variable) {
            $this->get(route('search.index', ['scope' => $scope, 'q' => 'matching catalogue']))->assertOk()
                ->assertViewHas('scope', $scope)
                ->assertViewHas($variable, fn ($rows) => $rows->isEmpty())
                ->assertViewHas('medicineResults', fn ($rows) => $rows->isEmpty())
                ->assertSee('No '.($scope === 'transfers' ? 'transfer' : $scope).' results found.')
                ->assertDontSee('Search matching catalogue only');
            $this->getJson(route('search.autocomplete', ['scope' => $scope, 'q' => 'matching catalogue']))
                ->assertOk()->assertExactJson([]);
        }
    }

    public function test_all_sections_renders_only_matching_section_cards_and_one_empty_state(): void
    {
        $this->signIn();
        Medicine::create(['medicine_name' => 'Search catalogue result']);

        $this->get(route('search.index', ['q' => 'catalogue result']))->assertOk()
            ->assertSee('Search catalogue result')->assertDontSee('No inventory results found.')
            ->assertDontSee('No transfer results found.')->assertDontSee('No forecast results found.');
        $response = $this->get(route('search.index', ['q' => 'totally nonexistent']))->assertOk()
            ->assertSee('No matching records found in the sections you can access.')
            ->assertDontSee('No medicine results found.');
        $this->assertSame(1, substr_count($response->getContent(), 'No matching records found in the sections you can access.'));
    }

    public function test_transfer_search_respects_facility_scope_and_tied_page_order(): void
    {
        [$unit, $user] = $this->signIn();
        $destination = HealthUnit::create(['name' => 'Search destination RHU']);
        $outside = HealthUnit::create(['name' => 'Search outside RHU']);
        $medicine = Medicine::create(['medicine_name' => 'Transfer target medicine']);
        $base = ['from_health_unit_id' => $unit->id, 'to_health_unit_id' => $destination->id,
            'medicine_id' => $medicine->id, 'quantity' => 1, 'status' => 'pending', 'requested_by' => $user->id,
            'created_at' => now()->startOfDay()];
        $target = TransferRequest::create($base + ['notes' => 'Unique target transfer']);
        for ($index = 0; $index < 20; $index++) {
            TransferRequest::create($base);
        }
        TransferRequest::create(array_replace($base, ['from_health_unit_id' => $outside->id,
            'to_health_unit_id' => $outside->id, 'notes' => 'Unique target transfer outside']));

        $url = route('transfers.index', ['page' => 2]).'#transfer-'.$target->id;
        $this->get(route('search.index', ['scope' => 'transfers', 'q' => 'Unique target transfer']))->assertOk()
            ->assertViewHas('transferResults', fn ($rows) => $rows->modelKeys() === [$target->id] && $rows->first()->search_page === 2)
            ->assertSee('href="'.$url.'"', false)->assertDontSee('Unique target transfer outside');
        $this->getJson(route('search.autocomplete', ['scope' => 'transfers', 'q' => 'Unique target transfer']))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.type', 'Transfer')->assertJsonPath('0.url', $url);
        $this->get($url)->assertOk()->assertSee('id="transfer-'.$target->id.'"', false);
    }

    public function test_forecast_search_links_to_the_matching_facility_item_on_its_original_page(): void
    {
        [$unit] = $this->signIn();
        $outside = HealthUnit::create(['name' => 'Outside search unit']);
        $target = null;
        for ($index = 0; $index < 17; $index++) {
            $medicine = Medicine::create(['medicine_name' => 'Forecast search medicine '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
            Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
                'available_stock' => 20, 'expiration_date' => today()->addYear()]);
            $target = $medicine;
        }
        Inventory::create(['medicine_id' => $target->id, 'health_unit_id' => $outside->id,
            'available_stock' => 20, 'expiration_date' => today()->addYear()]);

        $response = $this->get(route('search.index', ['scope' => 'forecast', 'q' => $target->medicine_name]))->assertOk()
            ->assertViewHas('forecastResults', fn ($rows) => $rows->count() === 1 && $rows->first()['inventory']->health_unit_id === $unit->id)
            ->assertViewHas('medicineResults', fn ($rows) => $rows->isEmpty());
        $item = $response->viewData('forecastResults')->first();
        $this->assertStringContainsString('#forecast-item-'.$unit->id.'-'.$target->id, $item['search_url']);
        $this->assertStringContainsString('forecast_page=', $item['search_url']);
        $this->getJson(route('search.autocomplete', ['scope' => 'forecast', 'q' => $target->medicine_name]))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.type', 'Forecast')->assertJsonPath('0.url', $item['search_url']);
        $this->get($item['search_url'])->assertOk()
            ->assertSee('id="forecast-item-'.$unit->id.'-'.$target->id.'"', false);
    }

    public function test_forecast_autocomplete_does_not_build_stock_projections(): void
    {
        [$unit] = $this->signIn();
        $medicine = Medicine::create(['medicine_name' => 'Autocomplete forecast target']);
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'available_stock' => 20]);
        $this->mock(ForecastService::class)->shouldNotReceive('getForecast');

        $this->getJson(route('search.autocomplete', ['scope' => 'forecast', 'q' => $medicine->medicine_name]))
            ->assertOk()->assertJsonPath('0.type', 'Forecast')
            ->assertJsonPath('0.url', route('forecast.index', ['forecast_page' => 1]).'#forecast-item-'.$unit->id.'-'.$medicine->id);
    }

    public function test_invalid_or_unauthorized_scopes_and_malformed_queries_are_rejected(): void
    {
        $this->signIn('midwife');
        foreach (['transfers', 'forecast', 'users', 'unknown-section'] as $scope) {
            foreach (['search.index', 'search.autocomplete'] as $route) {
                $this->getJson(route($route, ['q' => 'medicine', 'scope' => $scope]))->assertUnprocessable()->assertJsonValidationErrors('scope');
            }
        }
        foreach (['search.index', 'search.autocomplete'] as $route) {
            $this->getJson(route($route, ['q' => ['invalid'], 'scope' => 'medicine']))->assertUnprocessable()->assertJsonValidationErrors('q');
        }
    }
}
