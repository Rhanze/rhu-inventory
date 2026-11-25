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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiseaseSavedGraphTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Saved graph RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $disease = Disease::create(['code' => 'SAVED-GRAPH', 'name' => 'Saved graph condition']);
        $medicine = $this->medicine($disease, 'Recorded graph medicine');
        $this->medicine($disease, 'A medicine without consumption');
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $disease, $medicine];
    }

    private function medicine(Disease $disease, string $name): Medicine
    {
        $medicine = Medicine::create(['medicine_name' => $name, 'dosage' => '5 mg',
            'dosage_form' => 'Tablet', 'unit' => 'tablet', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);

        return $medicine;
    }

    private function dispense(HealthUnit $unit, User $user, Medicine $medicine, string $date, int $quantity, ?string $area = null): void
    {
        DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
            'medicine_id' => $medicine->id, 'date_dispensed' => $date,
            'quantity_dispensed' => $quantity, 'barangay' => $area]);
    }

    private function payload(TestResponse $response): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $element = (new \DOMXPath($document))->query('//script[@id="disease-consumption-chart-data"]')->item(0);
        $this->assertNotNull($element);

        return json_decode($element->textContent, true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_quiet_current_window_displays_saved_personal_graph_with_its_actual_dates(): void
    {
        [$unit, $user, , $medicine] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2026-09-28', 5);
        $this->dispense($unit, $user, $medicine, '2026-10-05', 23);
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $this->dispense($unit, $colleague, $medicine, '2026-08-01', 999);
        $otherUnit = HealthUnit::create(['name' => 'Other saved graph RHU']);
        $this->dispense($otherUnit, $user, $medicine, '2026-08-02', 999);

        $page = $this->get(route('disease_reports.index'))->assertOk()
            ->assertViewHas('consumptionChartUsesSavedHistory', true)
            ->assertViewHas('diseaseTrends', fn ($analysis) => ! $analysis['historical']
                && array_sum(array_column($analysis['medicine_signals'], 'total_quantity')) === 0)
            ->assertSee('No disease-linked dispensing was recorded in the current comparison dates')
            ->assertSee('Showing saved consumption history from 2026-09-28 to 2026-10-05.')
            ->assertDontSee('id="diseaseConsumptionChangeChart"', false);
        $payload = $this->payload($page);
        $this->assertTrue($payload['historical']);
        $this->assertTrue($payload['saved_history']);
        $this->assertCount(1, $payload['medicines']);
        $this->assertSame($medicine->id, $payload['medicines'][0]['id']);
        $this->assertSame([5, 23], array_column($payload['medicines'][0]['history'], 'quantity'));
        $this->assertSame(['from' => '2026-09-28', 'to' => '2026-10-05'], $payload['window']);
        $this->getJson(route('disease_reports.chart_data'))->assertOk()
            ->assertJsonPath('analysis.historical', false)->assertJsonPath('consumption_chart.historical', true)
            ->assertJsonPath('consumption_chart_uses_saved_history', true)
            ->assertJsonPath('consumption_chart.total_transactions', 2);
    }

    public function test_explicit_empty_dates_do_not_load_history_outside_the_selected_range(): void
    {
        [$unit, $user, , $medicine] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2026-09-28', 5);
        $this->get(route('disease_reports.index', ['date_from' => '2026-11-01', 'date_to' => '2026-11-15']))
            ->assertOk()->assertViewHas('consumptionChartUsesSavedHistory', false)
            ->assertViewHas('consumptionChartAnalysis', fn ($analysis) => $analysis['historical'] && $analysis['total_transactions'] === 0)
            ->assertDontSee('id="disease-consumption-chart-data"', false)
            ->assertDontSee('id="consumption-chart-history-notice"', false);
    }

    public function test_recent_consumption_keeps_the_current_comparison_and_excludes_unrecorded_medicines_from_graphs(): void
    {
        [$unit, $user, , $medicine] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2026-11-09', 13);
        $page = $this->get(route('disease_reports.index'))->assertOk()
            ->assertViewHas('consumptionChartUsesSavedHistory', false)
            ->assertViewHas('diseaseTrends', fn ($analysis) => count($analysis['medicine_signals']) === 2)
            ->assertSee('id="diseaseConsumptionChangeChart"', false);
        $payload = $this->payload($page);
        $this->assertFalse($payload['historical']);
        $this->assertCount(1, $payload['medicines']);
        $this->assertSame(13, $payload['medicines'][0]['current_quantity']);
    }

    public function test_consumption_graph_picker_shows_a_matching_form_and_unit_once(): void
    {
        [$unit, $user, $disease, $tablet] = $this->fixture();
        $solution = $this->medicine($disease, 'Bottled graph medicine');
        $solution->update(['dosage' => '10 mg/mL', 'dosage_form' => 'Oral solution', 'unit' => 'Bottle']);
        $this->dispense($unit, $user, $tablet, '2026-11-09', 13);
        $this->dispense($unit, $user, $solution, '2026-11-09', 3);

        $page = $this->get(route('disease_reports.index'))->assertOk()
            ->assertSee('<option value="'.$tablet->id.'">Recorded graph medicine 5 mg Tablet</option>', false)
            ->assertSee('<option value="'.$solution->id.'">Bottled graph medicine 10 mg/mL Oral solution (Bottle)</option>', false);

        $medicines = collect($this->payload($page)['medicines'])->keyBy('id');
        $this->assertSame('tablet', $medicines[$tablet->id]['unit']);
        $this->assertSame('Bottle', $medicines[$solution->id]['unit']);
    }

    public function test_saved_graph_preserves_the_selected_assigned_area_and_disease(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        MidwifeBarangay::create(['user_id' => $user->id, 'barangay' => 'Assigned graph village', 'is_primary' => true]);
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $this->dispense($unit, $colleague, $medicine, '2026-09-29', 11, 'Assigned graph village');
        $this->dispense($unit, $user, $medicine, '2026-09-01', 999, 'Another village');
        $otherDisease = Disease::create(['code' => 'OTHER-SAVED-GRAPH', 'name' => 'Other saved graph condition']);
        $otherMedicine = $this->medicine($otherDisease, 'Other association medicine');
        $this->dispense($unit, $colleague, $otherMedicine, '2026-09-02', 999, 'Assigned graph village');

        $page = $this->get(route('disease_reports.index', ['disease_id' => $disease->id, 'consumption_scope' => 'area']))->assertOk()
            ->assertViewHas('consumptionChartUsesSavedHistory', true)
            ->assertViewHas('consumptionChartAnalysis', fn ($analysis) => $analysis['scope'] === 'area'
                && $analysis['location'] === 'Assigned graph village' && $analysis['total_transactions'] === 1);
        $payload = $this->payload($page);
        $this->assertSame(['from' => '2026-09-29', 'to' => '2026-09-29'], $payload['window']);
        $this->assertSame([$disease->name], $payload['medicines'][0]['diseases']);
        $this->assertSame(11, $payload['medicines'][0]['total_quantity']);
    }

    public function test_long_saved_history_respects_the_existing_ten_year_analysis_limit(): void
    {
        [$unit, $user, , $medicine] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2010-01-01', 99);
        $this->dispense($unit, $user, $medicine, '2024-02-29', 7);
        $page = $this->get(route('disease_reports.index'))->assertOk()
            ->assertViewHas('consumptionChartUsesSavedHistory', true);
        $payload = $this->payload($page);
        $this->assertSame(['from' => '2014-03-01', 'to' => '2024-02-29'], $payload['window']);
        $this->assertSame(7, $payload['medicines'][0]['total_quantity']);
    }
}
