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

class DiseaseEmptyHistoryFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Manila'));
    }

    private function fixture(string $role = 'staff'): array
    {
        $unit = HealthUnit::create(['name' => 'Disease feedback RHU']);
        $other = HealthUnit::create(['name' => 'Other disease feedback RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => $role]);
        $disease = Disease::create(['code' => 'EMPTY-HISTORY', 'name' => 'Feedback condition']);
        $medicine = Medicine::create(['medicine_name' => 'Feedback medicine', 'is_dispensable' => true, 'unit' => 'tablet']);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $other, $user, $disease, $medicine];
    }

    private function assertHistoryCount(TestResponse $response, int $count, string $key = 'history'): \DOMXPath
    {
        $response->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $dom = new \DOMXPath($document);
        $markers = $dom->query('//*[@data-empty-result="'.$key.'"]');
        $this->assertSame(1, $markers->length);
        $this->assertSame((string) $count, $markers->item(0)->getAttribute('data-empty-count'));

        return $dom;
    }

    public function test_manual_case_rows_do_not_populate_consumption_history_or_change_empty_alerts(): void
    {
        [$unit, $other, , $disease] = $this->fixture();
        foreach ([[$unit, 0, true], [$unit, 8, false], [$other, 999, true]] as [$rhu, $cases, $complete]) {
            DiseaseReport::create(['health_unit_id' => $rhu->id, 'disease_id' => $disease->id,
                'report_date' => '2026-11-09', 'period_type' => 'weekly', 'case_count' => $cases, 'is_complete' => $complete]);
        }
        $response = $this->get(route('disease_reports.index', ['view' => 'reports', 'disease_id' => $disease->id, 'year' => 2026]))
            ->assertViewHas('diseaseHistoryCount', 0)->assertViewHas('usageHistoryCount', 0);
        $dom = $this->assertHistoryCount($response, 0);
        $this->assertSame(1, $dom->query('//section[@aria-label="Historical record dates"]/form[@data-empty-action="history"]')->length);
        $this->assertSame('No existing history yet.', $dom->evaluate('string(//*[@data-empty-result="history"]/@data-empty-message)'));
        $this->assertSame(0, $dom->query('//*[@id="disease-report-dialog"]|//input[@name="case_count"]')->length);
    }

    public function test_recorded_consumption_is_history_when_the_latest_completed_period_has_zero_quantity(): void
    {
        [$unit, , $user, $disease, $medicine] = $this->fixture();
        DispensingRecord::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensed_by' => $user->id, 'date_dispensed' => '2026-10-12', 'quantity_dispensed' => 7]);
        $parameters = ['disease_id' => $disease->id];
        $response = $this->get(route('disease_reports.index', $parameters))
            ->assertViewHas('diseaseTrends', fn ($result) => $result['signals'][0]['medicines'][0]['current_quantity'] === 0);
        $this->assertHistoryCount($response, 1);
        $this->assertHistoryCount($this->get(route('disease_reports.index', $parameters + [
            'date_from' => '2026-10-12', 'date_to' => '2026-10-12',
        ])), 1);
        $this->assertHistoryCount($this->get(route('disease_reports.index', $parameters + ['year' => 2025])), 0);
    }

    public function test_current_period_saved_consumption_is_existing_personal_history_before_any_completed_period_exists(): void
    {
        [$unit, $other, $midwife, , $medicine] = $this->fixture('midwife');
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        foreach ([[$unit, $midwife, today()], [$unit, $colleague, today()],
            [$other, $midwife, today()], [$unit, $midwife, today()->addDay()]] as [$rhu, $user, $date]) {
            DispensingRecord::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
                'dispensed_by' => $user->id, 'date_dispensed' => $date, 'quantity_dispensed' => 2]);
        }
        $response = $this->get(route('disease_reports.index', ['medicine_id' => $medicine->id]))
            ->assertViewHas('usageHistoryCount', 1)->assertViewHas('diseaseHistoryCount', 1)
            ->assertViewHas('usage', fn ($usage) => $usage['scope'] === 'personal' && $usage['total_transactions'] === 0);
        $dom = $this->assertHistoryCount($response, 1);
        $this->assertHistoryCount($response, 1, 'medicine-history');
        $this->assertSame(0, $dom->query('//*[@id="medicine-usage-details"]/summary/@data-empty-guard')->length);
        $this->assertSame('medicine-history', $dom->evaluate('string(//*[@id="medicine-usage-details"]//form/@data-empty-action)'));
    }

    public function test_assigned_area_history_uses_recorded_area_including_colleagues_with_exact_date_and_rhu_scope(): void
    {
        [$unit, $other, $midwife, , $medicine] = $this->fixture('midwife');
        $colleague = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Feedback Village A', 'is_primary' => true]);
        foreach ([[$unit, $colleague, 'Feedback Village A', '2024-08-02'],
            [$unit, $midwife, 'Feedback Village B', '2024-08-02'], [$other, $midwife, 'Feedback Village A', '2024-08-03']] as [$rhu, $user, $area, $date]) {
            DispensingRecord::create(['health_unit_id' => $rhu->id, 'medicine_id' => $medicine->id,
                'dispensed_by' => $user->id, 'barangay' => $area, 'date_dispensed' => $date, 'quantity_dispensed' => 2]);
        }
        $parameters = ['medicine_id' => $medicine->id, 'barangay' => 'Feedback Village A',
            'date_from' => '2024-08-02', 'date_to' => '2024-08-02'];
        $this->assertHistoryCount($this->get(route('disease_reports.index', $parameters))
            ->assertViewHas('usageHistoryCount', 1)->assertViewHas('diseaseTrends', fn ($result) => $result['scope'] === 'area'), 1);
        $this->assertHistoryCount($this->get(route('disease_reports.index', array_replace($parameters, [
            'date_from' => '2024-08-03', 'date_to' => '2024-08-03',
        ])))->assertViewHas('usageHistoryCount', 0), 0);
    }
}
