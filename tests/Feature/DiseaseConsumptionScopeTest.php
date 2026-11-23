<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiseaseConsumptionScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Consumption scope RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        foreach (['Poblacion 1', 'Poblacion 2', 'Poblacion 3'] as $index => $area) {
            MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => $area, 'is_primary' => $index === 0]);
        }
        MidwifeBarangay::create(['user_id' => $colleague->id, 'barangay' => 'Other assigned village']);
        $disease = Disease::create(['code' => 'SCOPE-CONSUMPTION', 'name' => 'Scope association']);
        $medicine = Medicine::create(['medicine_name' => 'Scoped medicine', 'dosage' => '5 mg', 'unit' => 'tablet', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);
        foreach ([[$midwife, null, 10], [$midwife, 'Previous recorded village', 3],
            [$midwife, 'Poblacion 2', 7], [$colleague, 'Poblacion 1', 99]] as [$actor, $area, $quantity]) {
            DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $actor->id,
                'medicine_id' => $medicine->id, 'barangay' => $area, 'date_dispensed' => '2026-10-10', 'quantity_dispensed' => $quantity]);
        }
        $otherUnit = HealthUnit::create(['name' => 'Other consumption scope RHU']);
        DispensingRecord::create(['health_unit_id' => $otherUnit->id, 'dispensed_by' => $midwife->id,
            'medicine_id' => $medicine->id, 'date_dispensed' => '2026-10-10', 'quantity_dispensed' => 999]);
        $this->withSession(['user_id' => $midwife->id]);

        return [$unit, $midwife, $disease, $medicine];
    }

    private function dom(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }

    public function test_restored_assignments_keep_personal_legacy_history_as_the_default_without_moving_it(): void
    {
        [, , , $medicine] = $this->fixture();
        $page = $this->get(route('disease_reports.index'))->assertOk()
            ->assertViewHas('consumptionScope', 'personal')->assertViewHas('selectedBarangay', null)
            ->assertViewHas('savedConsumptionCount', 3)
            ->assertViewHas('consumptionRecords', fn ($rows) => $rows->total() === 3)
            ->assertViewHas('usage', fn ($usage) => $usage['scope'] === 'personal' && $usage['total_quantity'] === 20);
        $dom = $this->dom($page->getContent());
        $areas = $dom->query('//*[@id="demand-barangay"]/option/@value');
        $this->assertSame(['Poblacion 1', 'Poblacion 2', 'Poblacion 3'], array_map(fn ($node) => $node->nodeValue, iterator_to_array($areas)));
        $this->assertSame('My recorded consumption', $dom->evaluate('string(//nav[@aria-label="Consumption scope"]/*[@aria-current="page"])'));
        $this->getJson(route('disease_reports.chart_data'))->assertOk()->assertJsonPath('analysis.scope', 'personal')
            ->assertJsonPath('consumption_scope', 'personal')->assertJsonPath('analysis.total_transactions', 3);
        $content = $this->get(route('disease_reports.export', ['consumption_scope' => 'personal', 'medicine_id' => $medicine->id]))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($content)));
        $this->assertCount(4, $rows);
        $quantities = array_map(fn ($row) => (int) $row[6], array_slice($rows, 1));
        sort($quantities);
        $this->assertSame([3, 7, 10], $quantities);
        $this->assertSame(1, DispensingRecord::whereNull('barangay')->where('quantity_dispensed', 10)->count());
        $this->assertSame(1, DispensingRecord::where('barangay', 'Previous recorded village')->count());
    }

    public function test_explicit_area_and_old_named_barangay_links_use_only_that_assigned_area(): void
    {
        $this->fixture();
        foreach ([['consumption_scope' => 'area'], ['barangay' => '  poblacion   1 ']] as $filters) {
            $this->get(route('disease_reports.index', $filters))->assertOk()
                ->assertViewHas('consumptionScope', 'area')->assertViewHas('selectedBarangay', 'Poblacion 1')
                ->assertViewHas('savedConsumptionCount', 1)
                ->assertViewHas('usage', fn ($usage) => $usage['scope'] === 'area' && $usage['total_quantity'] === 99);
        }
        $this->get(route('disease_reports.index', ['consumption_scope' => 'personal', 'barangay' => 'Poblacion 1']))
            ->assertOk()->assertViewHas('selectedBarangay', null)->assertViewHas('savedConsumptionCount', 3);
        foreach (['disease_reports.index', 'disease_reports.chart_data', 'disease_reports.export'] as $route) {
            foreach (['area', 'personal'] as $scope) {
                $this->getJson(route($route, ['consumption_scope' => $scope, 'barangay' => 'Other assigned village']))->assertForbidden();
            }
        }
    }

    public function test_personal_history_search_usage_and_scope_links_preserve_the_explicit_view(): void
    {
        [, , $disease, $medicine] = $this->fixture();
        $filters = ['consumption_scope' => 'personal', 'date_from' => '2026-10-10', 'date_to' => '2026-10-10',
            'medicine_id' => $medicine->id, 'disease_id' => $disease->id, 'period_type' => 'monthly'];
        $page = $this->get(route('disease_reports.index', $filters))->assertOk();
        $dom = $this->dom($page->getContent());
        foreach (['//section[@aria-label="Historical record dates"]/form', '//*[@id="medicine-usage-filter"]/ancestor::form',
            '//*[@id="consumption-record-search"]/ancestor::form'] as $path) {
            $form = $dom->query($path)->item(0);
            $this->assertNotNull($form);
            $this->assertSame('personal', $dom->evaluate('string(.//input[@name="consumption_scope"]/@value)', $form));
        }
        $areaForm = $dom->query('//*[@id="demand-barangay"]/ancestor::form')->item(0);
        $this->assertSame('area', $dom->evaluate('string(.//input[@name="consumption_scope"]/@value)', $areaForm));
        $historyLink = $dom->evaluate('string(//*[@id="saved-consumption-coverage"]/a/@href)');
        parse_str(parse_url($historyLink, PHP_URL_QUERY), $parameters);
        $this->assertSame('personal', $parameters['consumption_scope']);
        $this->get(explode('#', $historyLink)[0])->assertOk()->assertViewHas('consumptionScope', 'personal');
        $areaLink = $dom->evaluate('string(//nav[@aria-label="Consumption scope"]/a[normalize-space(.)="Assigned barangay"]/@href)');
        $this->get(explode('#', $areaLink)[0])->assertOk()->assertViewHas('consumptionScope', 'area');
    }

    public function test_staff_rhu_scope_is_unchanged_and_cannot_be_relabelled_as_personal(): void
    {
        [$unit] = $this->fixture();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $staff->id])->get(route('disease_reports.index'))->assertOk()
            ->assertViewHas('consumptionScope', 'rhu')->assertViewHas('savedConsumptionCount', 4);
        $this->getJson(route('disease_reports.index', ['consumption_scope' => 'personal']))->assertForbidden();
    }
}
