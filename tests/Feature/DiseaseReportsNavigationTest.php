<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DiseaseReport;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiseaseReportsNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Manila'));
    }

    private function fixture(string $role = 'midwife'): array
    {
        $unit = HealthUnit::create(['name' => 'Navigation RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => $role]);
        $midwife = $role === 'midwife' ? $user : User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Navigation Village', 'is_primary' => true]);
        $disease = Disease::create(['code' => 'NAVIGATION', 'name' => 'Navigation condition']);
        $medicine = Medicine::create(['medicine_name' => 'Navigation medicine', 'is_dispensable' => true, 'unit' => 'tablet']);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $disease, $medicine];
    }

    private function dom(TestResponse $response): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());

        return new \DOMXPath($document);
    }

    private function parameters(string $url): array
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parameters);

        return $parameters;
    }

    public function test_all_rhu_roles_open_consumption_analysis_without_manual_case_reporting_controls(): void
    {
        [$unit, $midwife, $disease] = $this->fixture();
        DiseaseReport::create(['health_unit_id' => $unit->id, 'disease_id' => $disease->id,
            'report_date' => '2026-11-09', 'period_type' => 'weekly', 'case_count' => 99999,
            'reported_by' => $midwife->id, 'is_complete' => true, 'remarks' => 'LEGACY-MANUAL-CASE-ROW']);
        foreach ([$midwife, User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']),
            User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'admin'])] as $user) {
            $page = $this->withSession(['user_id' => $user->id])->get(route('disease_reports.index'))
                ->assertOk()->assertViewHas('activeView', 'demand')->assertViewHas('diseaseHistoryCount', 0)
                ->assertSee('Disease Trend Analysis')->assertSee('Analyze trends')
                ->assertDontSee('Includes medicine consumption recorded for this barangay')
                ->assertDontSee('Quantities come from patient dispensing; stock deliveries and transfers are excluded.')
                ->assertDontSee('Record medicine consumption')->assertDontSee('Export consumption CSV')
                ->assertDontSee('csv-import-link')
                ->assertDontSee('Record case report')->assertDontSee('Disease case reports')->assertDontSee('LEGACY-MANUAL-CASE-ROW');
            $dom = $this->dom($page);
            $this->assertSame(0, $dom->query('//*[@id="disease-report-dialog"]|//*[@id="case-report-filters-heading"]')->length);
            $this->assertSame(0, $dom->query('//input[@name="case_count"]|//div[contains(concat(" ",normalize-space(@class)," ")," disease-stack ")]//form[@method="POST"]')->length);
            $this->assertSame(null, $page->viewData('selectedBarangay'));
            $this->assertSame($user->role === 'midwife' ? 'personal' : 'rhu', $page->viewData('consumptionScope'));
        }
    }

    public function test_legacy_view_links_show_the_same_consumption_signals_and_keep_filter_selection(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
            'medicine_id' => $medicine->id, 'barangay' => 'Navigation Village',
            'date_dispensed' => '2026-10-26', 'quantity_dispensed' => 3]);
        foreach (['demand', 'reports'] as $legacyView) {
            $page = $this->get(route('disease_reports.index', ['view' => $legacyView, 'medicine_id' => $medicine->id,
                'disease_id' => $disease->id, 'period_type' => 'monthly', 'barangay' => 'Navigation Village']))
                ->assertOk()->assertViewHas('activeView', 'demand')->assertViewHas('diseaseHistoryCount', 1)
                ->assertViewHas('selectedMedicineId', fn ($id) => (int) $id === $medicine->id)
                ->assertViewHas('selectedDiseaseId', $disease->id)->assertSee('id="disease-trends"', false)
                ->assertDontSee('Confirmed disease case trends');
            $dom = $this->dom($page);
            foreach (['demand-barangay' => 'Navigation Village', 'disease-filter' => (string) $disease->id, 'demand-period' => 'monthly'] as $id => $value) {
                $this->assertSame($value, $dom->evaluate('string(//select[@id="'.$id.'"][@data-native-select]/option[@selected]/@value)'));
            }
        }
    }

    public function test_filters_preserve_selected_dates_and_clear_only_the_date_range(): void
    {
        [, , $disease, $medicine] = $this->fixture();
        $parameters = ['view' => 'reports', 'medicine_id' => $medicine->id, 'disease_id' => $disease->id,
            'barangay' => 'Navigation Village', 'period_type' => 'monthly', 'periods' => 8,
            'year' => 2026, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'];
        $page = $this->get(route('disease_reports.index', $parameters))->assertOk();
        $dom = $this->dom($page);
        $forms = $dom->query('//*[@id="main-content"]//form[@method="GET"]');
        $this->assertGreaterThan(1, $forms->length);
        foreach ($forms as $form) {
            foreach (['year', 'date_from', 'date_to'] as $name) {
                $inputs = $dom->query('.//input[@name="'.$name.'"]', $form);
                $this->assertSame(1, $inputs->length);
                $this->assertSame((string) $parameters[$name], $inputs->item(0)->getAttribute('value'));
            }
        }
        $historyForm = $dom->query('//section[@aria-label="Historical record dates"]/form')->item(0);
        foreach (['medicine_id', 'disease_id', 'barangay', 'period_type', 'periods'] as $name) {
            $this->assertSame((string) $parameters[$name], $dom->evaluate('string(.//input[@name="'.$name.'"]/@value)', $historyForm));
        }
        $clear = $dom->evaluate('string(//a[contains(concat(" ",normalize-space(@class)," ")," history-clear ")]/@href)');
        $cleared = $this->parameters($clear);
        foreach (['year', 'date_from', 'date_to'] as $name) {
            $this->assertArrayNotHasKey($name, $cleared);
        }
        $this->assertSame('Navigation Village', $cleared['barangay']);
        $this->get($clear)->assertOk()->assertViewHas('activeView', 'demand')
            ->assertViewHas('selectedDiseaseId', $disease->id)->assertViewHas('historyRange', fn ($range) => ! $range->isFiltered());
    }

    public function test_medicine_details_stay_collapsed_until_a_consumption_evidence_link_is_selected(): void
    {
        [$unit, $user, , $medicine] = $this->fixture('staff');
        DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
            'medicine_id' => $medicine->id, 'barangay' => 'Navigation Village',
            'date_dispensed' => '2026-11-09', 'quantity_dispensed' => 3]);
        $page = $this->get(route('disease_reports.index'))->assertOk()->assertViewHas('showUsageDetails', false);
        $dom = $this->dom($page);
        $this->assertSame(0, $dom->query('//*[@id="medicine-usage-details"][@open]')->length);
        $link = $dom->evaluate('string(//*[@id="disease-trends"]//a[contains(@href,"usage_details=1")]/@href)');
        $this->assertNotSame('', $link);
        $this->assertSame((string) $medicine->id, $this->parameters($link)['medicine_id']);
        $page = $this->get(explode('#', $link, 2)[0])->assertOk()->assertViewHas('showUsageDetails', true);
        $this->assertSame(1, $this->dom($page)->query('//*[@id="medicine-usage-details"][@open]')->length);
    }

    public function test_changing_barangay_drops_an_archived_medicine_that_has_no_history_in_the_new_area(): void
    {
        [$unit, $user, , $medicine] = $this->fixture();
        MidwifeBarangay::create(['user_id' => $user->id, 'barangay' => 'Other Navigation Village']);
        $archived = Medicine::create(['medicine_name' => 'Archived navigation medicine', 'is_dispensable' => false, 'unit' => 'tablet']);
        foreach ([[$archived, 'Navigation Village', 19], [$medicine, 'Other Navigation Village', 7]] as [$item, $area, $quantity]) {
            DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
                'medicine_id' => $item->id, 'barangay' => $area, 'date_dispensed' => '2026-11-09', 'quantity_dispensed' => $quantity]);
        }
        $archived->delete();
        $page = $this->get(route('disease_reports.index', ['barangay' => 'Navigation Village', 'medicine_id' => $archived->id]))->assertOk();
        $dom = $this->dom($page);
        $form = $dom->query('//*[@id="demand-barangay"]/ancestor::form[1]')->item(0);
        $this->assertSame(0, $dom->query('.//input[@name="medicine_id"]', $form)->length);
        $parameters = [];
        foreach ($dom->query('.//input[@type="hidden"]', $form) as $input) {
            $parameters[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $parameters['barangay'] = 'Other Navigation Village';
        $parameters['disease_id'] = '';
        $parameters['period_type'] = 'weekly';
        $target = explode('#', $form->getAttribute('action'), 2)[0].'?'.http_build_query($parameters);
        $this->get($target)->assertOk()->assertViewHas('selectedBarangay', 'Other Navigation Village')
            ->assertViewHas('selectedMedicineId', fn ($id) => (int) $id === $medicine->id)
            ->assertViewHas('medicines', fn ($items) => ! $items->contains('id', $archived->id))
            ->assertViewHas('usage', fn ($usage) => $usage['scope'] === 'area' && $usage['total_quantity'] === 7);
    }

    public function test_entire_rhu_selection_survives_history_filtering_without_guessing_an_area(): void
    {
        [, , , $medicine] = $this->fixture('staff');
        $page = $this->get(route('disease_reports.index', ['barangay' => '', 'medicine_id' => $medicine->id]))
            ->assertOk()->assertViewHas('selectedBarangay', null);
        $dom = $this->dom($page);
        $fields = $dom->query('//section[@aria-label="Historical record dates"]/form/input[@name="barangay"]');
        $this->assertSame(1, $fields->length);
        $this->assertSame('', $fields->item(0)->getAttribute('value'));
        $clear = $dom->evaluate('string(//a[contains(concat(" ",normalize-space(@class)," ")," history-clear ")]/@href)');
        $this->assertSame('', $this->parameters($clear)['barangay']);
        $this->get($clear)->assertOk()->assertViewHas('selectedBarangay', null);
    }
}
