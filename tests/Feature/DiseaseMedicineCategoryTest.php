<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use App\Services\AreaMedicineDemandService;
use App\Services\DiseaseConsumptionTrendService;
use App\Services\ForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiseaseMedicineCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Category RHU', 'facility_type' => 'Rural Health Unit']);
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Injectable medicine', 'dosage' => '10 mg/mL',
            'dosage_form' => 'Solution for injection', 'category' => 'medicine', 'unit' => 'Vial', 'is_dispensable' => true]);
        $vaccine = Medicine::create(['medicine_name' => 'Preventive vaccine', 'dosage' => '0.5 mL',
            'dosage_form' => 'Suspension for injection', 'category' => 'vaccine', 'unit' => 'Vial', 'is_dispensable' => true]);
        $disease = Disease::create(['code' => 'CATEGORY', 'name' => 'Associated condition', 'is_active' => true]);
        foreach ([$medicine, $vaccine] as $item) {
            DiseaseMedicineMap::create(['medicine_id' => $item->id, 'disease_id' => $disease->id]);
            foreach (['2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'] as $date) {
                DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $item->id,
                    'dispensed_by' => $staff->id, 'barangay' => 'Poblacion', 'date_dispensed' => $date,
                    'quantity_dispensed' => $item->id === $medicine->id ? 10 : 999]);
            }
        }
        Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $vaccine->id, 'available_stock' => 202]);
        $this->withSession(['user_id' => $staff->id]);

        return [$unit, $medicine, $vaccine];
    }

    public function test_disease_page_keeps_vial_medicines_and_rejects_vaccines_without_altering_vaccine_stock(): void
    {
        [$unit, $medicine, $vaccine] = $this->fixture();
        $page = $this->get(route('disease_reports.index', ['medicine_id' => $medicine->id, 'usage_details' => 1]))
            ->assertOk()->assertViewHas('selectedMedicineId', fn ($id) => (int) $id === $medicine->id)
            ->assertViewHas('medicines', fn ($items) => $items->modelKeys() === [$medicine->id])
            ->assertViewHas('usage', fn ($usage) => $usage['total_quantity'] === 50 && $usage['forecast_total'] === 40.0)
            ->assertSee('Injectable medicine 10 mg/mL (Solution for injection)')
            ->assertSee('Projected quantities in Vial for each week.')->assertDontSee('Preventive vaccine');
        $this->get(route('disease_reports.index'))->assertOk()
            ->assertViewHas('selectedMedicineId', fn ($id) => (int) $id === $medicine->id);
        foreach ([[], ['year' => 2026]] as $dates) {
            $this->getJson(route('disease_reports.index', ['medicine_id' => $vaccine->id] + $dates))
                ->assertUnprocessable()->assertJsonValidationErrors('medicine_id');
        }
        $this->assertTrue($vaccine->fresh()->is_dispensable);
        $this->assertSame(202, (int) Inventory::where('health_unit_id', $unit->id)->where('medicine_id', $vaccine->id)->sum('available_stock'));
        $this->assertDatabaseCount('dispensing_records', 10);
    }

    public function test_vaccine_consumption_never_creates_disease_evidence_in_current_history_dashboard_forecast_or_export(): void
    {
        [$unit, $medicine, $vaccine] = $this->fixture();
        $service = app(DiseaseConsumptionTrendService::class);
        foreach ([$service->analyze($unit->id, 'Poblacion'),
            $service->analyze($unit->id, 'Poblacion', 'weekly', null, '2026-09-28', '2026-11-01')] as $analysis) {
            $this->assertSame([$medicine->id], array_column($analysis['medicine_signals'], 'id'));
            $this->assertSame(5, $analysis['total_transactions']);
            $this->assertSame([$medicine->id], array_column($analysis['signals'][0]['medicines'], 'id'));
        }
        $area = app(AreaMedicineDemandService::class)->analyze($unit->id, 'Poblacion');
        $this->assertSame([$medicine->id], array_column($area['signals'], 'id'));
        $forecast = app(ForecastService::class)->getUnplannedForecast($unit->id);
        $this->assertSame([$medicine->id], array_map(fn ($item) => $item->id, $forecast['items'][0]->medicines));
        $csv = $this->get(route('disease_reports.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Injectable medicine', $csv);
        $this->assertStringNotContainsString('Preventive vaccine', $csv);
        $vaccine->delete();
        $this->assertSame([$medicine->id], $service->recordsQuery($unit->id)->distinct()->pluck('medicine_id')->all());
    }
}
