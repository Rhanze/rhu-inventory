<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DiseaseReport;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiseaseConsumptionGraphTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Graph RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $disease = Disease::create(['code' => 'GRAPH-ONE', 'name' => 'Graph condition']);
        $medicine = Medicine::create(['medicine_name' => 'Graph medicine', 'dosage' => '10 mg',
            'dosage_form' => 'tablet', 'unit' => 'tablet', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $disease, $medicine];
    }

    private function dispense(HealthUnit $unit, User $user, Medicine $medicine, string $date, int $quantity, string $area = 'Graph Village'): void
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

    public function test_current_graph_payload_follows_disease_and_area_filters_using_only_completed_consumption(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        $otherDisease = Disease::create(['code' => 'GRAPH-TWO', 'name' => 'Other graph condition']);
        DiseaseMedicineMap::create(['disease_id' => $otherDisease->id, 'medicine_id' => $medicine->id]);
        $otherMedicine = Medicine::create(['medicine_name' => 'Other graph liquid', 'unit' => 'mL', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['disease_id' => $otherDisease->id, 'medicine_id' => $otherMedicine->id]);
        foreach (['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'] as $date) {
            $this->dispense($unit, $user, $medicine, $date, 10);
        }
        $this->dispense($unit, $user, $medicine, '2026-10-26', 20);
        $this->dispense($unit, $user, $medicine, '2026-10-26', 999, 'Other Village');
        $this->dispense($unit, $user, $medicine, '2026-11-02', 888);
        $this->dispense($unit, $user, $otherMedicine, '2026-10-26', 777);
        DiseaseReport::create(['health_unit_id' => $unit->id, 'disease_id' => $disease->id,
            'report_date' => '2026-10-26', 'period_type' => 'weekly', 'case_count' => 12345, 'is_complete' => true]);

        $page = $this->get(route('disease_reports.index', ['disease_id' => $disease->id, 'barangay' => 'Graph Village']))
            ->assertOk()->assertSee('id="diseaseConsumptionHistoryChart"', false)
            ->assertSee('id="diseaseConsumptionChangeChart"', false);
        $payload = $this->payload($page);
        $this->assertFalse($payload['historical']);
        $this->assertSame('weekly', $payload['period_type']);
        $this->assertSame(50, $payload['threshold_percent']);
        $this->assertCount(1, $payload['medicines']);
        $series = $payload['medicines'][0];
        $this->assertSame($medicine->id, $series['id']);
        $this->assertSame([$disease->name], $series['diseases']);
        $this->assertSame('tablet', $series['unit']);
        $this->assertSame('10 mg', $series['dosage']);
        $this->assertSame([10, 10, 10, 10, 20], array_column($series['history'], 'quantity'));
        $this->assertEquals(10, $series['baseline_average']);
        $this->assertEquals(100, $series['change_percent']);
        $this->assertArrayNotHasKey('case_count', $series);
        $this->assertArrayNotHasKey('probability', $series);
    }

    public function test_historical_graph_payload_uses_exact_boundary_dates_and_omits_the_current_change_graph(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        foreach (['2024-01-14' => 99, '2024-01-15' => 5, '2024-01-16' => 999] as $date => $quantity) {
            $this->dispense($unit, $user, $medicine, $date, $quantity);
        }
        $page = $this->get(route('disease_reports.index', ['disease_id' => $disease->id,
            'period_type' => 'monthly', 'date_from' => '2024-01-15', 'date_to' => '2024-01-15']))
            ->assertOk()->assertSee('id="diseaseConsumptionHistoryChart"', false)
            ->assertDontSee('id="diseaseConsumptionChangeChart"', false);
        $payload = $this->payload($page);
        $this->assertTrue($payload['historical']);
        $this->assertSame('monthly', $payload['period_type']);
        $series = $payload['medicines'][0];
        $this->assertSame(5, $series['total_quantity']);
        $this->assertNull($series['baseline_average']);
        $this->assertNull($series['change_percent']);
        $this->assertCount(1, $series['history']);
        $this->assertSame('2024-01-15', $series['history'][0]['included_start']);
        $this->assertSame('2024-01-15', $series['history'][0]['included_end']);
        $this->assertSame(5, $series['history'][0]['quantity']);
    }
}
