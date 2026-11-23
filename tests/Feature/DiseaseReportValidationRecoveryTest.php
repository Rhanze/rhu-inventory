<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiseaseReportValidationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): Medicine
    {
        $unit = HealthUnit::create(['name' => 'Trend filter recovery RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'Filter recovery medicine', 'is_dispensable' => true]);
        $this->withSession(['user_id' => $user->id]);

        return $medicine;
    }

    public function test_invalid_history_dates_return_to_the_selected_trend_without_a_case_editor(): void
    {
        $medicine = $this->fixture();
        $returnUrl = route('disease_reports.index', ['medicine_id' => $medicine->id, 'period_type' => 'weekly']);
        $this->from($returnUrl)->get(route('disease_reports.index', [
            'medicine_id' => $medicine->id, 'period_type' => 'monthly',
            'date_from' => '2024-12-31', 'date_to' => '2024-01-01',
            'report_id' => 999, 'case_count' => 999,
        ]))->assertRedirect($returnUrl)->assertSessionHasErrors('date_to');

        $this->get($returnUrl)->assertOk()->assertSee('Please review your input.')
            ->assertViewHas('selectedMedicineId', fn ($id) => (int) $id === $medicine->id)
            ->assertViewHas('periodType', 'weekly')
            ->assertDontSee('id="disease-report-dialog"', false)
            ->assertDontSee('name="case_count"', false);
        $this->assertDatabaseCount('disease_reports', 0);
    }

    public function test_invalid_trend_filters_produce_validation_feedback_and_keep_the_last_valid_selection(): void
    {
        $medicine = $this->fixture();
        $returnUrl = route('disease_reports.index', ['medicine_id' => $medicine->id, 'period_type' => 'weekly']);
        $this->from($returnUrl)->get(route('disease_reports.index', [
            'medicine_id' => 999999, 'period_type' => 'daily', 'periods' => 3,
        ]))->assertRedirect($returnUrl)->assertSessionHasErrors(['medicine_id', 'period_type', 'periods']);
        $this->get($returnUrl)->assertOk()->assertSee('Please review your input.')
            ->assertViewHas('selectedMedicineId', fn ($id) => (int) $id === $medicine->id)
            ->assertViewHas('periodType', 'weekly')->assertDontSee('Record case report');
    }

    public function test_old_case_form_session_input_cannot_restore_a_manual_reporting_form(): void
    {
        $medicine = $this->fixture();
        $this->withSession(['disease_report_validation' => ['report_id' => 10],
            '_old_input' => ['case_count' => 77, 'remarks' => 'Old manual case data', 'report_id' => 10]])
            ->get(route('disease_reports.index', ['view' => 'reports', 'medicine_id' => $medicine->id]))
            ->assertOk()->assertDontSee('id="disease-report-dialog"', false)
            ->assertDontSee('name="case_count"', false)->assertDontSee('Old manual case data')
            ->assertDontSee('Record case report');
        $this->assertDatabaseCount('disease_reports', 0);
    }
}
