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
use Tests\TestCase;

class DiseaseHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00', 'Asia/Manila'));
    }

    private function fixture(string $role = 'staff'): array
    {
        $unit = HealthUnit::create(['name' => 'Historical consumption RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => $role]);
        $disease = Disease::create(['code' => 'HIST', 'name' => 'Historical associated condition']);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $disease];
    }

    private function medicine(Disease $disease, string $name = 'Historical consumption tablets', array $extra = []): Medicine
    {
        $medicine = Medicine::create(array_replace(['medicine_name' => $name, 'unit' => 'tablet', 'is_dispensable' => true], $extra));
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);

        return $medicine;
    }

    private function dispense(HealthUnit $unit, User $user, Medicine $medicine, string $date, int $quantity): void
    {
        DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
            'medicine_id' => $medicine->id, 'date_dispensed' => $date, 'quantity_dispensed' => $quantity]);
    }

    private function csv(array $filters): array
    {
        $content = $this->get(route('disease_reports.export', $filters))->assertOk()->streamedContent();

        return array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($content)));
    }

    public function test_year_history_keeps_exact_consumption_dates_at_the_calendar_year_boundary(): void
    {
        [$unit, $user, $disease] = $this->fixture();
        $medicine = $this->medicine($disease);
        $this->dispense($unit, $user, $medicine, '2024-12-31', 17);
        $this->dispense($unit, $user, $medicine, '2025-01-01', 999);
        $other = HealthUnit::create(['name' => 'Other historical RHU']);
        $this->dispense($other, $user, $medicine, '2024-12-30', 9999);
        $filters = ['disease_id' => $disease->id, 'medicine_id' => $medicine->id, 'year' => 2024];

        $this->get(route('disease_reports.index', $filters))->assertOk()
            ->assertViewHas('diseaseTrends', fn ($analysis) => $analysis['historical']
                && count($analysis['medicine_signals'][0]['history']) === 53
                && $analysis['medicine_signals'][0]['total_quantity'] === 17
                && $analysis['medicine_signals'][0]['history'][52]['included_end'] === '2024-12-31')
            ->assertViewHas('usage', fn ($usage) => $usage['total_quantity'] === 17 && $usage['forecast'] === []);
        $rows = $this->csv($filters);
        $this->assertCount(2, $rows);
        $this->assertSame('2024-12-31', $rows[1][5]);
        $this->assertSame('17', $rows[1][6]);
    }

    public function test_single_day_history_and_export_include_only_actual_consumption_on_that_day(): void
    {
        [$unit, $user, $disease] = $this->fixture();
        $medicine = $this->medicine($disease);
        foreach (['2024-05-01' => 999, '2024-05-17' => 11, '2024-05-18' => 99] as $date => $quantity) {
            $this->dispense($unit, $user, $medicine, $date, $quantity);
        }
        $filters = ['disease_id' => $disease->id, 'medicine_id' => $medicine->id, 'period_type' => 'monthly',
            'date_from' => '2024-05-17', 'date_to' => '2024-05-17'];
        $this->get(route('disease_reports.index', $filters))->assertOk()
            ->assertViewHas('diseaseTrends', fn ($analysis) => $analysis['total_transactions'] === 1
                && $analysis['medicine_signals'][0]['total_quantity'] === 11
                && $analysis['medicine_signals'][0]['history'][0]['included_start'] === '2024-05-17'
                && $analysis['medicine_signals'][0]['history'][0]['included_end'] === '2024-05-17')
            ->assertSee('name="date_from" value="2024-05-17"', false);
        $rows = $this->csv($filters);
        $this->assertCount(2, $rows);
        $this->assertSame('2024-05-17', $rows[1][5]);
        $this->assertSame('11', $rows[1][6]);
    }

    public function test_legacy_case_reports_cannot_supply_consumption_history_or_case_data_in_the_chart_api(): void
    {
        [$unit, , $disease] = $this->fixture();
        $this->medicine($disease);
        DiseaseReport::create(['health_unit_id' => $unit->id, 'disease_id' => $disease->id,
            'report_date' => '2024-01-01', 'period_type' => 'monthly', 'case_count' => 999,
            'is_complete' => true, 'remarks' => 'LEGACY-MANUAL-CASE-ONLY']);
        $response = $this->getJson(route('disease_reports.chart_data', ['disease_id' => $disease->id,
            'period_type' => 'monthly', 'year' => 2024]))->assertOk()
            ->assertJsonCount(0, 'analysis.signals')->assertJsonPath('analysis.total_transactions', 0);
        $this->assertStringNotContainsString('case_count', $response->getContent());
        $this->assertStringNotContainsString('LEGACY-MANUAL-CASE-ONLY', $response->getContent());
        $this->assertDatabaseCount('disease_reports', 1);
    }

    public function test_archived_medicine_usage_respects_exact_dates_without_later_transactions_or_current_stock(): void
    {
        [$unit, $user, $disease] = $this->fixture();
        $medicine = $this->medicine($disease, 'Archived historical tablets', ['is_dispensable' => false]);
        foreach (['2024-01-14' => 99, '2024-01-15' => 5, '2024-01-16' => 999, '2025-01-15' => 9999] as $date => $quantity) {
            $this->dispense($unit, $user, $medicine, $date, $quantity);
        }
        $medicine->delete();
        $this->get(route('disease_reports.index', ['disease_id' => $disease->id, 'medicine_id' => $medicine->id,
            'period_type' => 'monthly', 'date_from' => '2024-01-15', 'date_to' => '2024-01-15']))
            ->assertOk()->assertViewHas('usage', fn ($usage) => $usage['total_quantity'] === 5
                && $usage['total_transactions'] === 1 && $usage['history'][0]['included_start'] === '2024-01-15'
                && $usage['history'][0]['included_end'] === '2024-01-15' && $usage['forecast'] === []
                && $usage['suggested_replenishment'] === null)
            ->assertSee('Archived medicine history')->assertDontSee('Usable RHU stock today');
    }

    public function test_midwife_cannot_select_archived_history_recorded_only_by_another_user_or_rhu(): void
    {
        [$unit, , $disease] = $this->fixture('midwife');
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $medicine = $this->medicine($disease, 'Other user historical medicine', ['is_dispensable' => false]);
        $this->dispense($unit, $colleague, $medicine, '2024-01-15', 99);
        $medicine->delete();
        $this->getJson(route('disease_reports.index', ['disease_id' => $disease->id, 'medicine_id' => $medicine->id, 'year' => 2024]))
            ->assertUnprocessable()->assertJsonValidationErrors('medicine_id');
    }

    public function test_future_year_is_empty_and_long_chart_ranges_are_rejected_while_csv_retains_saved_consumption(): void
    {
        [$unit, $user, $disease] = $this->fixture();
        $medicine = $this->medicine($disease);
        $this->dispense($unit, $user, $medicine, '2001-01-01', 5);
        $this->getJson(route('disease_reports.chart_data', ['disease_id' => $disease->id, 'year' => 2027]))
            ->assertOk()->assertJsonCount(0, 'analysis.signals')->assertJsonPath('analysis.total_transactions', 0);
        $this->getJson(route('disease_reports.chart_data', ['disease_id' => $disease->id,
            'date_from' => '2000-01-01', 'date_to' => '2026-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_from');
        $this->getJson(route('disease_reports.chart_data', ['disease_id' => $disease->id, 'date_from' => '2000-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_from');
        $rows = $this->csv(['disease_id' => $disease->id, 'date_from' => '2000-01-01', 'date_to' => '2026-01-01']);
        $this->assertCount(2, $rows);
        $this->assertSame('2001-01-01', $rows[1][5]);
        $this->assertSame('5', $rows[1][6]);
    }
}
