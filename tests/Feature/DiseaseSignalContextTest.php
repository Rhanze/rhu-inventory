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

class DiseaseSignalContextTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
        $unit = HealthUnit::create(['name' => 'Comparable RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Primary area', 'is_primary' => true]);
        $this->withSession(['user_id' => $midwife->id]);

        return [$unit, $midwife];
    }

    private function medicine(string $name): Medicine
    {
        $medicine = Medicine::create(['medicine_name' => $name, 'unit' => 'tablet', 'is_dispensable' => true]);
        $disease = Disease::create(['code' => 'CONTEXT-'.$medicine->id, 'name' => $name.' association', 'is_active' => true]);
        DiseaseMedicineMap::create(['medicine_id' => $medicine->id, 'disease_id' => $disease->id]);

        return $medicine;
    }

    private function record(HealthUnit $unit, User $actor, Medicine $medicine, string $date, int $quantity): void
    {
        DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $actor->id,
            'medicine_id' => $medicine->id, 'barangay' => 'Primary area', 'date_dispensed' => $date, 'quantity_dispensed' => $quantity]);
    }

    public function test_dashboard_and_disease_page_use_the_same_area_dates_and_disease_signal_counts(): void
    {
        [$unit, $midwife] = $this->fixture();
        foreach (['Elevated evidence', 'Usual evidence'] as $name) {
            $medicine = $this->medicine($name);
            foreach (['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'] as $index => $date) {
                $this->record($unit, $midwife, $medicine, $date, $name === 'Elevated evidence' && $index === 4 ? 30 : 10);
            }
        }
        $this->record($unit, $midwife, $this->medicine('Recent evidence'), '2026-10-26', 8);

        $dashboard = $this->get(route('dashboard.midwife'))->assertOk();
        $page = $this->get(route('disease_reports.index', ['consumption_scope' => 'area', 'barangay' => 'Primary area']))->assertOk();
        $overview = $dashboard->viewData('areaDemand')['overview'];
        $this->assertSame($overview, $page->viewData('diseaseTrends')['overview']);
        $this->assertSame([1, 1, 1], [$overview['elevated'], $overview['typical'], $overview['insufficient']]);
        foreach ([$dashboard, $page] as $response) {
            $response->assertSee('Primary area')->assertSee('2026-10-26')->assertSee('2026-11-01')
                ->assertSee('2026-09-28')->assertSee('2026-10-25')->assertSee('Elevated disease signals')
                ->assertSee('Within usual consumption')->assertSee('Building history')->assertSee('Elevated evidence')
                ->assertSee('Usual evidence')->assertSee('Recent evidence');
        }
    }

    public function test_older_records_show_a_comparison_empty_state_and_a_scoped_saved_history_link(): void
    {
        [$unit, $midwife] = $this->fixture();
        $this->record($unit, $midwife, $this->medicine('Older evidence'), '2025-01-10', 9);
        foreach ([route('dashboard.midwife'), route('disease_reports.index', ['consumption_scope' => 'area', 'barangay' => 'Primary area'])] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('No qualifying records in this comparison period.')
                ->assertSee('View saved consumption history');
            $data = $response->original->getData();
            $analysis = $data['areaDemand'] ?? $data['diseaseTrends'];
            $this->assertSame(0, $analysis['overview']['typical']);
            $this->assertSame(1, $analysis['overview']['insufficient']);
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $link = $xpath->query('//a[normalize-space(.)="View saved consumption history"]')->item(0);
            $this->assertNotNull($link);
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $filters);
            $this->assertSame('area', $filters['consumption_scope']);
            $this->assertSame('Primary area', $filters['barangay']);
            $this->assertSame('2025-01-10', $filters['date_from']);
            $this->get($link->getAttribute('href'))->assertOk()->assertViewHas('savedConsumptionCount', 1);
        }
    }

    public function test_sidebar_opens_the_same_assigned_area_and_history_links_clear_search_without_losing_the_disease(): void
    {
        [$unit, $midwife] = $this->fixture();
        $medicine = $this->medicine('Saved selected evidence');
        $diseaseId = DiseaseMedicineMap::where('medicine_id', $medicine->id)->value('disease_id');
        $this->record($unit, $midwife, $medicine, '2025-01-10', 9);
        $dashboard = $this->get(route('dashboard.midwife'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($dashboard->getContent());
        $xpath = new \DOMXPath($document);
        $sidebarUrl = $xpath->evaluate('string(//nav[contains(@class,"nav-links")]/a[normalize-space(.)="Disease Trend Analysis"]/@href)');
        $this->assertNotSame('', $sidebarUrl);
        $this->get($sidebarUrl)->assertOk()->assertViewHas('consumptionScope', 'area')
            ->assertViewHas('selectedBarangay', 'Primary area');

        $page = $this->get(route('disease_reports.index', ['consumption_scope' => 'area', 'barangay' => 'Primary area',
            'disease_id' => $diseaseId, 'consumption_search' => 'no-matching-medicine']))->assertOk();
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $historyUrl = $xpath->evaluate('string(//a[normalize-space(.)="View saved consumption history"]/@href)');
        parse_str(parse_url($historyUrl, PHP_URL_QUERY), $filters);
        $this->assertArrayNotHasKey('consumption_search', $filters);
        $this->assertSame((string) $diseaseId, $filters['disease_id']);
        $this->get($historyUrl)->assertOk()->assertViewHas('selectedDiseaseId', $diseaseId)
            ->assertViewHas('consumptionRecords', fn ($records) => $records->total() === 1);
    }
}
