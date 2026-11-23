<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicSearchAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_footer_and_search_result_links_open_pages_with_specific_titles(): void
    {
        $rhu = HealthUnit::create(['name' => 'Public RHU', 'facility_type' => 'Rural Health Unit']);
        $medicine = Medicine::create(['medicine_name' => 'Fixture Medicine & Vaccine', 'is_dispensable' => true]);
        Inventory::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id, 'available_stock' => 12]);
        $search = $this->get(route('public.home.citizen', ['q' => 'Fixture', 'health_unit_id' => $rhu->id]))
            ->assertOk()->assertSee('<title>Medicine and Vaccine Search | PHO Supply Portal</title>', false);
        foreach (['privacy' => 'Privacy Policy', 'terms' => 'Terms of Use', 'accessibility' => 'Accessibility'] as $route => $title) {
            $url = route($route);
            $search->assertSee('href="'.$url.'"', false);
            $this->get($url)->assertOk()->assertSee('<title>'.$title.' | PHO Supply Portal</title>', false);
        }
        $detail = route('public.medicine.show', ['id' => $medicine->id, 'health_unit_id' => $rhu->id, 'q' => 'Fixture']);
        $search->assertSee('href="'.e($detail).'"', false);
        $this->get($detail)->assertOk()->assertSee('<title>Fixture Medicine &amp; Vaccine | PHO Supply Portal</title>', false);
    }

    public function test_medicine_details_use_the_name_when_present_and_fall_back_for_a_blank_name(): void
    {
        foreach ([['0', '0'], ['   ', 'Medicine Details']] as [$name, $title]) {
            $medicine = Medicine::create(['medicine_name' => $name, 'is_dispensable' => true]);
            $this->get(route('public.medicine.show', ['id' => $medicine->id]))
                ->assertOk()->assertSee('<title>'.$title.' | PHO Supply Portal</title>', false);
        }
    }

    public function test_landing_search_keeps_its_query_and_search_row_above_the_rhu_selector(): void
    {
        $rhu = HealthUnit::create(['name' => 'Public RHU', 'facility_type' => 'Rural Health Unit']);
        $pho = HealthUnit::create(['name' => 'Provincial warehouse', 'facility_type' => 'Provincial Health Office']);
        $response = $this->get('/public?q=Public&health_unit_id='.$rhu->id)->assertOk()
            ->assertSee('js/form-choice-search.js')
            ->assertDontSee('Please select your RHU.');
        $previousErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new \DOMXPath($document);
        $form = $xpath->query('//form[contains(concat(" ", normalize-space(@class), " "), " public-search-form ")]')->item(0);
        $this->assertNotNull($form);
        $this->assertSame(1, $xpath->query('./div[contains(@class,"search-form-flex")]/input[@name="q"][@value="Public"]', $form)->length);
        $this->assertSame(1, $xpath->query('./div[contains(@class,"search-form-flex")]/button[@type="submit"]', $form)->length);
        $this->assertSame(1, $xpath->query('./div[contains(@class,"rhu-field")]/preceding-sibling::div[contains(@class,"search-form-flex")]', $form)->length);
        $select = $xpath->query('./div[contains(@class,"rhu-field")]/select[@name="health_unit_id"]', $form)->item(0);
        $this->assertNotNull($select);
        $this->assertSame('Public RHU', $xpath->evaluate('string(option[@selected][@value="'.$rhu->id.'"])', $select));
        $this->assertSame(0, $xpath->query('option[@value="'.$pho->id.'"]', $select)->length);
        $this->assertSame(1, $xpath->query('//h1[contains(@class,"hero-heading")]/br')->length);
        $this->get('/public?q=Public')->assertOk()->assertSee('Please select your RHU.');
    }

    public function test_public_search_rejects_malformed_query_values(): void
    {
        $medicine = Medicine::create(['medicine_name' => 'Public medicine', 'is_dispensable' => true]);

        foreach (['/', '/public', '/search/public-autocomplete', '/medicine/'.$medicine->id] as $path) {
            $this->getJson($path.'?q[]=malformed')->assertUnprocessable()->assertJsonValidationErrors('q');
            $this->getJson($path.'?health_unit_id[]=1')->assertUnprocessable()->assertJsonValidationErrors('health_unit_id');
            $this->getJson($path.'?q='.str_repeat('a', 201))->assertUnprocessable()->assertJsonValidationErrors('q');
        }
    }

    public function test_blank_search_gives_a_medicine_prompt_and_preserves_the_selected_rhu(): void
    {
        $rhu = HealthUnit::create(['name' => 'Public RHU', 'facility_type' => 'Rural Health Unit']);
        $this->get('/public')->assertOk()->assertDontSee('Enter a medicine name or dosage to search.');

        foreach (['', '%20%20'] as $query) {
            $response = $this->get('/public?q='.$query.'&health_unit_id='.$rhu->id)->assertOk()
                ->assertSee('Enter a medicine name or dosage to search.')
                ->assertDontSee('id="search-results"', false);
            $document = new \DOMDocument;
            $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $this->assertCount(1, $xpath->query(
                '//input[@name="q"][@required][@aria-invalid="true"][@aria-describedby="public-search-error"]',
            ));
            $this->assertCount(1, $xpath->query('//*[@id="public-search-error"][@role="alert"]'));
            $this->assertCount(1, $xpath->query('//select[@name="health_unit_id"]/option[@value="'.$rhu->id.'"][@selected]'));
        }
    }

    public function test_public_totals_and_next_expiry_describe_stock_that_can_actually_be_dispensed(): void
    {
        $unit = HealthUnit::create(['name' => 'Public RHU', 'facility_type' => 'Rural Health Unit']);
        $medicine = Medicine::create(['medicine_name' => 'Public medicine', 'is_dispensable' => true]);
        $createBatch = fn (int $stock, string $expiration, array $extra = []) => Inventory::create(array_merge([
            'medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'available_stock' => $stock, 'allocated_stock' => 0, 'expiration_date' => $expiration,
        ], $extra));

        $createBatch(0, today()->addDay()->toDateString(), ['allocated_stock' => 20]);
        $createBatch(15, today()->addDays(30)->toDateString());
        $createBatch(5, today()->addDays(60)->toDateString());
        $createBatch(50, today()->subDay()->toDateString());
        $createBatch(50, today()->addDays(2)->toDateString())->delete();

        $this->get('/public?q=Public&health_unit_id='.$unit->id)->assertOk()
            ->assertViewHas('medicines', fn ($items) => $items->count() === 1 && (int) $items->first()->total_available === 20);
        $this->get('/medicine/'.$medicine->id.'?health_unit_id='.$unit->id)->assertOk()
            ->assertViewHas('availability', fn ($items) => $items->count() === 1
                && (int) $items->first()->total_available === 20
                && $items->first()->nearest_expiration === today()->addDays(30)->toDateString());
    }

    public function test_medicine_details_return_to_the_original_search_for_zero(): void
    {
        $unit = HealthUnit::create(['name' => 'Public RHU', 'facility_type' => 'Rural Health Unit']);
        $medicine = Medicine::create(['medicine_name' => 'Public medicine', 'dosage' => '0.5 mg', 'is_dispensable' => true]);
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 10]);
        $filters = ['q' => '0', 'health_unit_id' => $unit->id];
        $search = route('public.home.citizen', $filters);
        $details = route('public.medicine.show', ['id' => $medicine->id, 'health_unit_id' => $unit->id, 'q' => '0']);

        $this->get($search)->assertOk()->assertSee($details);
        $this->get($details)->assertOk()->assertSee($search);
        $this->assertSame(10, Inventory::sole()->available_stock);
    }

    public function test_autocomplete_cannot_advertise_stock_at_internal_facilities(): void
    {
        $unit = HealthUnit::create(['name' => 'Provincial warehouse', 'facility_type' => 'Provincial Health Office']);
        $medicine = Medicine::create(['medicine_name' => 'Public medicine', 'is_dispensable' => true]);
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'available_stock' => 25, 'allocated_stock' => 0]);

        $this->getJson('/search/public-autocomplete?q=Public&health_unit_id='.$unit->id)
            ->assertOk()->assertExactJson([]);
    }

    public function test_autocomplete_failure_does_not_return_database_details(): void
    {
        DB::partialMock()->shouldReceive('table')->with('medicines')
            ->once()->andThrow(new \RuntimeException('SQLSTATE private_database_secret'));

        $this->getJson('/search/public-autocomplete?q=Public')->assertStatus(500)
            ->assertJson(['error' => true, 'message' => 'Medicine suggestions are temporarily unavailable. Please try again.'])
            ->assertDontSee('private_database_secret')->assertDontSee('SQLSTATE');
    }
}
