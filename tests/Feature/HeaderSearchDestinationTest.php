<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeaderSearchDestinationTest extends TestCase
{
    use RefreshDatabase;

    private function phoViewer(): User
    {
        $user = User::factory()->create(['name' => 'Search PHO operator', 'role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $user->id]);

        return $user;
    }

    public function test_pho_inventory_search_links_to_the_selected_facility_and_its_configured_page(): void
    {
        $this->phoViewer();
        config(['pagination.inventory' => 2]);
        $warehouse = HealthUnit::create(['name' => 'Search warehouse', 'facility_type' => 'Provincial Health Office']);
        $outside = HealthUnit::create(['name' => 'Search other RHU', 'facility_type' => 'Rural Health Unit']);
        $medicine = Medicine::create(['medicine_name' => 'Stock destination medicine']);
        $base = ['medicine_id' => $medicine->id, 'health_unit_id' => $warehouse->id, 'available_stock' => 10];
        $target = Inventory::create($base + ['batch_no' => 'PHO inventory target']);
        Inventory::create($base);
        Inventory::create($base);
        Inventory::create(array_replace($base, ['health_unit_id' => $outside->id]));

        $response = $this->get(route('search.index', ['scope' => 'inventory', 'q' => 'PHO inventory target']))->assertOk();
        $result = $response->viewData('inventoryResults')->sole();
        $this->assertSame($target->id, $result->id);
        $this->assertSame(2, $result->search_page);
        $this->assertStringContainsString('rhu_filter='.$warehouse->id, $result->search_url);
        $this->getJson(route('search.autocomplete', ['scope' => 'inventory', 'q' => 'PHO inventory target']))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.url', $result->search_url);
        $this->get($result->search_url)->assertOk()->assertSee('id="inventory-'.$target->id.'"', false);
    }

    public function test_pho_stock_log_links_preserve_receipt_time_when_counting_the_destination_page(): void
    {
        $user = $this->phoViewer();
        $unit = HealthUnit::create(['name' => 'Search receipt RHU', 'facility_type' => 'Rural Health Unit']);
        $medicine = Medicine::create(['medicine_name' => 'Receipt destination medicine']);
        $base = ['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id, 'quantity_added' => 1,
            'added_by' => $user->id, 'movement_type' => 'receipt'];
        $target = DB::table('stock_logs')->insertGetId($base + ['batch_no' => 'Timed receipt target', 'date_added' => '2024-01-01 09:00:00']);
        for ($index = 0; $index < 20; $index++) {
            DB::table('stock_logs')->insert($base + ['batch_no' => 'Earlier receipt', 'date_added' => '2024-01-01 08:00:00']);
        }
        DB::table('stock_logs')->insert($base + ['batch_no' => 'Later receipt', 'date_added' => '2024-01-01 10:00:00']);

        $response = $this->get(route('search.index', ['scope' => 'stock_logs', 'q' => 'Timed receipt target']))->assertOk();
        $result = $response->viewData('stockLogResults')->sole();
        $this->assertSame($target, $result->id);
        $this->assertSame(1, $result->search_page);
        $this->assertStringContainsString('rhu_filter='.$unit->id, $result->search_url);
        $this->getJson(route('search.autocomplete', ['scope' => 'stock_logs', 'q' => 'Timed receipt target']))->assertOk()
            ->assertJsonPath('0.url', $result->search_url);
        $this->get($result->search_url)->assertOk()->assertSee('id="stock-log-'.$target.'"', false);
    }

    public function test_allocations_search_includes_incoming_rhu_records_and_provincewide_pho_records(): void
    {
        $pho = $this->phoViewer();
        config(['pagination.inventory' => 2]);
        $source = HealthUnit::create(['name' => 'Search allocation source']);
        $recipient = HealthUnit::create(['name' => 'Search allocation recipient']);
        $medicine = Medicine::create(['medicine_name' => 'Allocation destination medicine']);
        $base = ['medicine_id' => $medicine->id, 'health_unit_id' => $source->id,
            'destination_health_unit_id' => $recipient->id, 'quantity_allocated' => 2,
            'allocated_by' => $pho->id, 'date_allocated' => '2024-01-01', 'status' => 'completed'];
        $target = Allocation::create($base + ['destination' => 'Unique incoming allocation']);
        Allocation::create($base);
        Allocation::create($base);
        foreach ([$pho, User::factory()->create(['role' => 'staff', 'health_unit_id' => $recipient->id])] as $viewer) {
            $this->withSession(['user_id' => $viewer->id]);
            $response = $this->get(route('search.index', ['scope' => 'allocation', 'q' => 'Unique incoming allocation']))->assertOk();
            $result = $response->viewData('allocationResults')->sole();
            $this->assertSame($target->id, $result->id);
            $this->assertSame(2, $result->search_page);
            $url = route('allocation.index', ['page' => 2, 'highlight' => $target->id]).'#allocation-'.$target->id;
            $this->getJson(route('search.autocomplete', ['scope' => 'allocation', 'q' => 'Unique incoming allocation']))->assertOk()
                ->assertJsonPath('0.url', $url);
            $this->get($url)->assertOk()->assertSee('id="allocation-'.$target->id.'"', false);
        }
    }

    public function test_midwife_own_search_opens_personal_history_at_the_matching_record(): void
    {
        $unit = HealthUnit::create(['name' => 'Shared dispensing search RHU']);
        $viewer = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $other = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Own dispensing search target']);
        $base = ['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'quantity_dispensed' => 1, 'date_dispensed' => '2024-01-01', 'dispensed_by' => $viewer->id];
        $target = DispensingRecord::create($base);
        config(['pagination.dispensing' => 2]);
        DispensingRecord::create(array_replace($base, ['dispensed_by' => $other->id]));
        DispensingRecord::create(array_replace($base, ['dispensed_by' => $other->id]));
        $this->withSession(['user_id' => $viewer->id]);

        $response = $this->get(route('search.index', ['scope' => 'dispensing', 'q' => $medicine->medicine_name]))->assertOk();
        $result = $response->viewData('dispensingResults')->sole();
        $this->assertSame($target->id, $result->id);
        $this->assertSame(1, $result->search_page);
        $url = route('dispensing.index', ['page' => 1, 'highlight' => $target->id, 'dispensing_scope' => 'personal']).'#dispensing-'.$target->id;
        $response->assertSee($url);
        $this->getJson(route('search.autocomplete', ['scope' => 'dispensing', 'q' => $medicine->medicine_name]))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.url', $url);
        $this->get($url)->assertOk()->assertSee('id="dispensing-'.$target->id.'"', false);
    }

    public function test_name_sorted_destinations_use_configured_page_sizes_and_id_ties(): void
    {
        $this->phoViewer();
        config(['pagination.medicines' => 2, 'pagination.inventory' => 2]);
        Medicine::create(['medicine_name' => 'Same medicine']);
        Medicine::create(['medicine_name' => 'Same medicine']);
        $medicine = Medicine::create(['medicine_name' => 'Same medicine', 'dosage' => 'Unique medicine dosage']);
        User::factory()->count(2)->create(['name' => 'Same user', 'role' => 'staff', 'health_unit_id' => null]);
        $user = User::factory()->create(['name' => 'Same user', 'username' => 'unique-user-destination', 'role' => 'staff', 'health_unit_id' => null]);
        HealthUnit::create(['name' => 'Same RHU']);
        HealthUnit::create(['name' => 'Same RHU']);
        $unit = HealthUnit::create(['name' => 'Same RHU', 'municipality' => 'Unique RHU municipality']);

        foreach ([['medicine', 'Unique medicine dosage', 'medicineResults', 'medicine.index', 'medicine', $medicine],
            ['users', $user->username, 'userResults', 'users.index', 'user', $user],
            ['rhu', 'Unique RHU municipality', 'rhuResults', 'rhu.index', 'rhu', $unit]] as [$scope, $term, $variable, $route, $anchor, $record]) {
            $response = $this->get(route('search.index', ['scope' => $scope, 'q' => $term]))->assertOk();
            $this->assertSame(2, $response->viewData($variable)->sole()->search_page);
            $url = route($route, ['page' => 2, 'highlight' => $record->id]).'#'.$anchor.'-'.$record->id;
            $this->getJson(route('search.autocomplete', ['scope' => $scope, 'q' => $term]))->assertOk()
                ->assertJsonPath('0.url', $url);
            $this->get($url)->assertOk()->assertSee('id="'.$anchor.'-'.$record->id.'"', false);
        }
    }

    public function test_pho_headers_and_all_search_do_not_offer_inaccessible_dispensing_data(): void
    {
        $this->phoViewer();
        $this->get(route('search.index', ['q' => 'No matching target']))->assertOk()
            ->assertDontSee('<option value="dispensing"', false)
            ->assertViewHas('dispensingResults', fn ($rows) => $rows->isEmpty());
        $this->getJson(route('search.autocomplete', ['scope' => 'dispensing', 'q' => 'target']))
            ->assertUnprocessable()->assertJsonValidationErrors('scope');
    }

    public function test_broad_search_paginates_and_ranks_the_page_in_one_query(): void
    {
        $this->phoViewer();
        foreach (range(1, 22) as $index) {
            Medicine::create(['medicine_name' => sprintf('Fixture search medicine %02d', $index)]);
        }

        $filters = ['scope' => 'medicine', 'q' => 'Fixture search medicine'];
        DB::enableQueryLog();
        try {
            $first = $this->get(route('search.index', $filters));
            $rankQueries = collect(DB::getQueryLog())->filter(fn ($entry) => str_contains($entry['query'], 'ROW_NUMBER()'));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $first->assertOk()->assertViewHas('medicineResults', fn ($rows) => $rows->count() === 20 && $rows->hasMorePages())
            ->assertSee('medicine_results_page=2#search-medicine');
        $this->assertCount(1, $rankQueries);
        $this->get(route('search.index', $filters + ['medicine_results_page' => 2]))->assertOk()
            ->assertViewHas('medicineResults', fn ($rows) => $rows->count() === 2 && $rows->first()->search_page === 2);
    }
}
