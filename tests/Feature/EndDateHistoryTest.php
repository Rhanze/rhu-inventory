<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DiseaseReport;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use App\Services\DiseasePeriodService;
use App\Services\DiseaseConsumptionTrendService;
use App\Services\MedicineUsageTrendService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EndDateHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'End-date history RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $disease = Disease::create(['code' => 'END-HISTORY', 'name' => 'End-date condition']);
        $medicine = Medicine::create(['medicine_name' => 'End-date medicine', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);
        $this->withSession(['user_id' => $user->id, 'health_unit_id' => $unit->id, 'role' => 'staff']);

        return [$unit, $user, $disease, $medicine];
    }

    private function report(HealthUnit $unit, Disease $disease, string $date, int $cases, string $type = 'monthly', array $extra = []): DiseaseReport
    {
        $bounds = app(DiseasePeriodService::class)->bounds($date, $type);

        return DiseaseReport::create(array_merge([
            'health_unit_id' => $unit->id, 'disease_id' => $disease->id, 'period_type' => $type,
            'report_date' => $date, 'period_start' => $bounds['start'], 'period_end' => $bounds['end'],
            'case_count' => $cases, 'is_complete' => true,
        ], $extra));
    }

    private function dispense(HealthUnit $unit, User $user, Medicine $medicine, string $date, int $quantity, ?string $barangay = null): DispensingRecord
    {
        return DispensingRecord::create([
            'health_unit_id' => $unit->id, 'dispensed_by' => $user->id, 'medicine_id' => $medicine->id,
            'date_dispensed' => $date, 'quantity_dispensed' => $quantity, 'barangay' => $barangay,
            'patient_name' => 'End-date fictional patient',
        ]);
    }

    public function test_end_only_disease_consumption_keeps_scoped_old_records_and_agrees_with_export(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2023-01-15', 5);
        $this->dispense($unit, $user, $medicine, '2024-12-01', 7);
        $this->dispense($unit, $user, $medicine, '2025-01-01', 99);
        $other = HealthUnit::create(['name' => 'Other end-date RHU']);
        $otherDisease = Disease::create(['code' => 'OTHER-END', 'name' => 'Other end-date condition']);
        $otherMedicine = Medicine::create(['medicine_name' => 'Other condition medicine', 'is_dispensable' => true]);
        DiseaseMedicineMap::create(['disease_id' => $otherDisease->id, 'medicine_id' => $otherMedicine->id]);
        $this->dispense($other, $user, $medicine, '2001-01-01', 999);
        $this->dispense($unit, $user, $otherMedicine, '2001-01-01', 999);
        $filters = ['disease_id' => $disease->id, 'period_type' => 'monthly', 'periods' => 4, 'date_to' => '2024-12-31'];

        $this->get(route('disease_reports.index', $filters))->assertOk()
            ->assertViewHas('diseaseTrends', fn ($analysis) => count($analysis['signals']) === 1
                && count($analysis['medicine_signals'][0]['history']) === 24
                && $analysis['window']['from'] === '2023-01-15'
                && $analysis['medicine_signals'][0]['total_quantity'] === 12);
        $this->getJson(route('disease_reports.chart_data', $filters))->assertOk()
            ->assertJsonPath('analysis.window.from', '2023-01-15')
            ->assertJsonPath('analysis.medicine_signals.0.total_quantity', 12);
        $csv = $this->get(route('disease_reports.export', $filters))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
        $this->assertSame(['2024-12-01', '2023-01-15'], array_column(array_slice($rows, 1), 5));
        $this->assertSame(['7', '5'], array_column(array_slice($rows, 1), 6));
    }

    public function test_end_only_weekly_consumption_excludes_transactions_after_the_cutoff_even_in_the_same_week(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2024-06-03', 5);
        $this->dispense($unit, $user, $medicine, '2024-12-31', 17);
        $this->dispense($unit, $user, $medicine, '2025-01-02', 999);
        $filters = ['disease_id' => $disease->id, 'period_type' => 'weekly', 'date_to' => '2024-12-31'];

        $this->get(route('disease_reports.index', $filters))->assertOk()
            ->assertViewHas('diseaseTrends', function ($analysis) {
                $medicine = $analysis['medicine_signals'][0];
                $last = $medicine['history'][count($medicine['history']) - 1];

                return $analysis['window']['from'] === '2024-06-03'
                    && $last['start'] === '2024-12-30' && $last['included_end'] === '2024-12-31'
                    && $last['quantity'] === 17 && $medicine['total_quantity'] === 22;
            });
    }

    public function test_end_only_selection_without_matching_scoped_records_has_no_consumption_history(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        $other = HealthUnit::create(['name' => 'Only outside history RHU']);
        $this->dispense($other, $user, $medicine, '2001-01-01', 99);
        $filters = ['disease_id' => $disease->id, 'period_type' => 'monthly', 'date_to' => '2024-12-31'];

        $this->getJson(route('disease_reports.chart_data', $filters))->assertOk()
            ->assertJsonCount(0, 'analysis.signals')->assertJsonPath('analysis.total_transactions', 0);
        $service = app(MedicineUsageTrendService::class);
        $empty = $service->analyze($unit->id, null, $medicine->id, 'monthly', 4, null, null, '2024-12-31');
        $this->assertSame([], $empty['history']);
        $this->assertSame(0, $empty['total_transactions']);
        $this->assertSame('no_history', $empty['status']);

        $this->dispense($unit, $user, $medicine, '2025-01-01', 5);
        $this->getJson(route('disease_reports.chart_data', $filters))->assertOk()->assertJsonCount(0, 'analysis.signals');
        $this->assertSame([], $service->analyze($unit->id, null, $medicine->id, 'monthly', 4, null, null, '2024-12-31')['history']);
    }

    public function test_legacy_case_reports_do_not_extend_the_consumption_history_window(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        $this->report($unit, $disease, '2001-01-01', 99, extra: ['period_start' => null, 'period_end' => null]);
        $this->report($unit, $disease, '2023-02-01', 88, extra: ['is_complete' => false]);
        $this->dispense($unit, $user, $medicine, '2024-12-01', 5);

        $this->getJson(route('disease_reports.chart_data', [
            'disease_id' => $disease->id, 'period_type' => 'monthly', 'date_to' => '2024-12-31',
        ]))->assertOk()->assertJsonPath('analysis.window.from', '2024-12-01')
            ->assertJsonCount(1, 'analysis.medicine_signals.0.history')
            ->assertJsonPath('analysis.medicine_signals.0.total_quantity', 5);
        $this->assertDatabaseCount('disease_reports', 2);
    }

    public function test_end_only_medicine_usage_includes_old_dispensing_and_preserves_precise_date_boundaries(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2023-01-15', 5);
        $this->dispense($unit, $user, $medicine, '2024-12-20', 7);
        $this->dispense($unit, $user, $medicine, '2024-12-21', 999);
        $this->dispense($unit, $user, $medicine, '2001-01-01', 999)->delete();

        $this->get(route('disease_reports.index', [
            'disease_id' => $disease->id, 'medicine_id' => $medicine->id,
            'period_type' => 'monthly', 'periods' => 4, 'date_to' => '2024-12-20',
        ]))->assertOk()->assertViewHas('usage', fn ($usage) => count($usage['history']) === 24
            && $usage['window']['from'] === '2023-01-15' && $usage['total_quantity'] === 12
            && $usage['total_transactions'] === 2 && $usage['history'][0]['included_start'] === '2023-01-15'
            && $usage['history'][23]['included_end'] === '2024-12-20' && $usage['forecast'] === []);

        $exactDay = app(MedicineUsageTrendService::class)->analyze(
            $unit->id, null, $medicine->id, 'monthly', 4, null, '2024-12-20', '2024-12-20'
        );
        $this->assertSame(7, $exactDay['total_quantity']);
        $this->assertSame('2024-12-20', $exactDay['history'][0]['included_start']);
        $this->assertSame('2024-12-20', $exactDay['history'][0]['included_end']);
    }

    public function test_implicit_medicine_start_obeys_personal_area_facility_and_deleted_record_scopes(): void
    {
        [$unit, $user, , $personalMedicine] = $this->fixture();
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'midwife']);
        $other = HealthUnit::create(['name' => 'Outside medicine history RHU']);
        $areaMedicine = Medicine::create(['medicine_name' => 'Area end-date medicine']);
        $this->dispense($unit, $user, $personalMedicine, '2023-01-15', 5, 'Alpha');
        $this->dispense($unit, $user, $personalMedicine, '2024-12-12', 11, 'Beta');
        $this->dispense($unit, $colleague, $personalMedicine, '2001-01-01', 999, 'Alpha');
        $this->dispense($unit, $user, $personalMedicine, '2001-01-01', 0, 'Alpha');
        $this->dispense($unit, $user, $areaMedicine, '2023-01-15', 5, 'Alpha');
        $this->dispense($unit, $colleague, $areaMedicine, '2024-12-10', 7, 'Alpha');
        $this->dispense($unit, $user, $areaMedicine, '2001-01-01', 999, 'Beta');
        foreach ([$personalMedicine, $areaMedicine] as $medicine) {
            $this->dispense($other, $user, $medicine, '2001-01-01', 999, 'Alpha');
            $this->dispense($unit, $user, $medicine, '2001-01-01', 999, 'Alpha')->delete();
        }

        $service = app(MedicineUsageTrendService::class);
        $personal = $service->analyze($unit->id, $user->id, $personalMedicine->id, 'monthly', 4, null, null, '2024-12-31');
        $area = $service->analyze($unit->id, null, $areaMedicine->id, 'monthly', 4, 'Alpha', null, '2024-12-31');
        $this->assertSame(16, $personal['total_quantity']);
        $this->assertSame(12, $area['total_quantity']);
        foreach ([$personal, $area] as $usage) {
            $this->assertSame('2023-01-15', $usage['window']['from']);
            $this->assertCount(24, $usage['history']);
            $this->assertSame(2, $usage['total_transactions']);
        }
    }

    public function test_end_only_ranges_over_ten_years_require_narrowing_while_saved_csv_records_remain_available(): void
    {
        [$unit, $user, $disease, $medicine] = $this->fixture();
        $this->report($unit, $disease, '2001-01-01', 5, extra: ['remarks' => 'LEGACY-CASE-EXCLUDED']);
        $oldDispensing = $this->dispense($unit, $user, $medicine, '2001-01-01', 5);
        $filters = ['disease_id' => $disease->id, 'period_type' => 'monthly', 'date_to' => '2024-12-31'];

        $this->getJson(route('disease_reports.chart_data', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors('date_from');
        $csv = $this->get(route('disease_reports.export', $filters))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
        $this->assertCount(2, $rows);
        $this->assertSame('2001-01-01', $rows[1][5]);
        $this->assertSame('5', $rows[1][6]);
        $this->assertStringNotContainsString('LEGACY-CASE-EXCLUDED', $csv);
        try {
            app(MedicineUsageTrendService::class)->analyze($unit->id, null, $medicine->id, 'monthly', 4, null, null, '2024-12-31');
            $this->fail('A medicine history chart silently accepted more than ten years.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('date_from', $exception->errors());
        }
        $csv = $this->get(route('dispensing.export', ['date_to' => '2024-12-31']))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
        $this->assertSame([$oldDispensing->id], array_map('intval', array_column(array_slice($rows, 1), 0)));
    }

    public function test_future_end_is_capped_at_today_and_an_explicit_future_start_keeps_charts_empty(): void
    {
        [$unit, $user, , $medicine] = $this->fixture();
        $this->dispense($unit, $user, $medicine, '2026-01-15', 3);
        $this->dispense($unit, $user, $medicine, '2026-11-15', 5);
        $this->dispense($unit, $user, $medicine, '2027-01-01', 999);
        $consumption = app(DiseaseConsumptionTrendService::class);
        $analysis = $consumption->analyze($unit->id, null, 'monthly', null, null, '2027-12-31');
        $usageService = app(MedicineUsageTrendService::class);
        $usage = $usageService->analyze($unit->id, null, $medicine->id, 'monthly', 4, null, null, '2027-12-31');

        $this->assertSame('2026-11-15', $analysis['window']['to']);
        $this->assertCount(11, $analysis['medicine_signals'][0]['history']);
        $this->assertSame(8, $analysis['medicine_signals'][0]['total_quantity']);
        $this->assertSame('2026-11-15', $usage['window']['to']);
        $this->assertSame(8, $usage['total_quantity']);
        $this->assertSame('2026-11-15', $usage['history'][10]['included_end']);
        $this->assertSame([], $consumption->analyze($unit->id, null, 'monthly', null, '2027-01-01', '2027-12-31')['signals']);
        $this->assertSame([], $usageService->analyze($unit->id, null, $medicine->id, 'monthly', 4, null, '2027-01-01', '2027-12-31')['history']);
    }
}
