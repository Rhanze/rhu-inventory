<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DiseaseReport;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use App\Services\ForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastDiseaseConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Manila'));
    }

    private function unit(string $name): array
    {
        $unit = HealthUnit::create(['name' => $name, 'facility_type' => 'Rural Health Unit']);
        $user = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);

        return [$unit, $user];
    }

    private function linkedMedicine(Disease $disease, string $name = 'Consumption tablet', string $unit = 'tablet'): Medicine
    {
        $medicine = Medicine::create(['medicine_name' => $name, 'category' => 'medicine',
            'dosage' => '10 mg', 'unit' => $unit, 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['medicine_id' => $medicine->id, 'disease_id' => $disease->id]);

        return $medicine;
    }

    private function consume(HealthUnit $unit, User $user, Medicine $medicine, int $baseline = 10, int $latest = 20): void
    {
        foreach ([5, 4, 3, 2, 1] as $weeksAgo) {
            DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
                'medicine_id' => $medicine->id, 'date_dispensed' => today()->startOfWeek()->subWeeks($weeksAgo),
                'quantity_dispensed' => $weeksAgo === 1 ? $latest : $baseline]);
        }
    }

    public function test_manual_case_rows_alone_do_not_create_forecast_disease_signals(): void
    {
        [$unit, $user] = $this->unit('Consumption-only RHU');
        $disease = Disease::create(['name' => 'Manual legacy condition', 'code' => 'LEGACY', 'is_active' => true]);
        $this->linkedMedicine($disease);
        DiseaseReport::create(['health_unit_id' => $unit->id, 'reported_by' => $user->id, 'disease_id' => $disease->id,
            'report_date' => '2026-11-09', 'period_type' => 'weekly', 'period_start' => '2026-11-09',
            'period_end' => '2026-11-15', 'is_complete' => true, 'case_count' => 9999]);

        $service = app(ForecastService::class);
        $this->assertSame([], $service->getUnplannedForecast($unit->id)['items']);
        $this->assertSame([], $service->getTrendingDiseases($unit->id)['datasets']);
        $this->withSession(['user_id' => $user->id])->get(route('forecast.index'))->assertOk()
            ->assertSee('No mapped medicine consumption yet')->assertDontSee('Manual legacy condition')
            ->assertDontSee('Confirmed reports')->assertDontSee('case count')->assertDontSee('confirmed weekly reports');
        $this->assertDatabaseCount('disease_reports', 1);
    }

    public function test_mapped_consumption_produces_unit_aware_evidence_without_case_metrics(): void
    {
        [$unit, $user] = $this->unit('Observed consumption RHU');
        $disease = Disease::create(['name' => 'Consumption-linked condition', 'code' => 'LINKED', 'is_active' => true]);
        $medicine = $this->linkedMedicine($disease);
        $this->consume($unit, $user, $medicine);
        $service = app(ForecastService::class);
        $result = $service->getUnplannedForecast($unit->id);
        $this->assertSame('medicine_consumption', $result['source']);
        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertSame('elevated', $item->status);
        $this->assertSame($unit->id, $item->health_unit_id);
        $this->assertSame(20, $item->medicines[0]->current_quantity);
        $this->assertEquals(10, $item->medicines[0]->baseline_average);
        $this->assertSame('tablet', $item->medicines[0]->unit);
        $this->assertEquals(100, $item->medicines[0]->change_percent);
        $this->assertArrayNotHasKey('total_cases', get_object_vars($item));
        $this->assertArrayNotHasKey('probability', get_object_vars($item));
        $chart = $service->getTrendingDiseases($unit->id, 35);
        $this->assertSame([10, 10, 10, 10, 20], $chart['datasets'][0]['data']);
        $this->assertSame('tablet', $chart['datasets'][0]['unit']);
        $this->assertArrayNotHasKey('total_cases', $chart['datasets'][0]);
        $this->withSession(['user_id' => $user->id])->get(route('forecast.index'))->assertOk()
            ->assertSee('Possible Disease Trends from Medicine Consumption')->assertSee('Consumption-linked condition')
            ->assertSee('Elevated consumption signal')->assertSee('20 tablet')->assertSee('+100.0% consumption change');
        $this->assertDatabaseCount('disease_reports', 0);
    }

    public function test_provincial_forecast_keeps_different_rhus_and_medicine_units_separate(): void
    {
        [$first, $firstUser] = $this->unit('First consumption RHU');
        [$second, $secondUser] = $this->unit('Second consumption RHU');
        $disease = Disease::create(['name' => 'Shared associated condition', 'code' => 'SHARED', 'is_active' => true]);
        $tablet = $this->linkedMedicine($disease);
        $solution = $this->linkedMedicine($disease, 'Consumption solution', 'mL');
        $this->consume($first, $firstUser, $tablet, 10, 20);
        $this->consume($second, $secondUser, $solution, 30, 60);
        $result = app(ForecastService::class)->getUnplannedForecast();
        $this->assertCount(2, $result['items']);
        $items = collect($result['items'])->keyBy('health_unit_id');
        $this->assertCount(1, $items[$first->id]->medicines);
        $this->assertSame(20, $items[$first->id]->medicines[0]->current_quantity);
        $this->assertSame('tablet', $items[$first->id]->medicines[0]->unit);
        $this->assertCount(1, $items[$second->id]->medicines);
        $this->assertSame(60, $items[$second->id]->medicines[0]->current_quantity);
        $this->assertSame('mL', $items[$second->id]->medicines[0]->unit);
        $this->assertArrayNotHasKey('total_quantity', get_object_vars($items[$first->id]));
        $this->assertCount(1, app(ForecastService::class)->getUnplannedForecast(null, 1)['items']);
    }
}
