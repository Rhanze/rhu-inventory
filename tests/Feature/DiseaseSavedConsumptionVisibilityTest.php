<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiseaseSavedConsumptionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Saved consumption RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Unlinked saved medicine', 'is_dispensable' => true, 'unit' => 'tablet']);
        $this->withSession(['user_id' => $midwife->id]);

        return [$unit, $midwife, $medicine];
    }

    private function record(HealthUnit $unit, User $user, Medicine $medicine, string $date, int $quantity = 6, ?string $area = null): DispensingRecord
    {
        return DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
            'medicine_id' => $medicine->id, 'date_dispensed' => $date, 'quantity_dispensed' => $quantity, 'barangay' => $area]);
    }

    private function dom(TestResponse $response): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());

        return new \DOMXPath($document);
    }

    public function test_unlinked_personal_records_remain_visible_without_opening_other_accounts_or_rhus(): void
    {
        [$unit, $midwife, $medicine] = $this->fixture();
        $this->record($unit, $midwife, $medicine, '2026-09-23', 7);
        $this->record($unit, $midwife, $medicine, '2026-10-12', 5);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->record($unit, $colleague, $medicine, '2026-08-01', 1000);
        $otherUnit = HealthUnit::create(['name' => 'Other saved consumption RHU']);
        $this->record($otherUnit, $midwife, $medicine, '2026-07-01', 1000);
        $this->record($unit, $midwife, $medicine, '2026-11-18', 1000);
        $this->record($unit, $midwife, $medicine, '2026-08-02', 1000)->delete();

        $page = $this->get(route('disease_reports.index'))->assertOk()
            ->assertViewHas('savedConsumptionCount', 2)->assertViewHas('savedConsumptionFrom', '2026-09-23')
            ->assertViewHas('savedConsumptionTo', '2026-10-12')->assertViewHas('diseaseHistoryCount', 0)
            ->assertViewHas('showUsageDetails', true)->assertViewHas('usage', fn ($usage) => $usage['total_quantity'] === 12)
            ->assertSee('Saved dispensing:')->assertSee('View saved history')
            ->assertSee('Disease signals require a configured medicine association matching these filters.')
            ->assertSee('Current completed-period comparison');
        $dom = $this->dom($page);
        $this->assertSame('Current completed-period comparison', $dom->evaluate('string(//*[@id="disease-history-dates"]/summary/span)'));
        $this->assertSame('2', $dom->evaluate('string(//*[@data-empty-result="history"]/@data-empty-count)'));
        $this->assertSame(1, $dom->query('//*[@id="medicine-usage-details"][@open]')->length);
        $this->assertSame(0, $dom->query('//*[@id="demand-barangay"]/option[normalize-space(.)="Entire RHU"]')->length);
        $this->get(route('disease_reports.index', ['usage_details' => 0]))->assertOk()->assertViewHas('showUsageDetails', false);
    }

    public function test_saved_history_link_keeps_analysis_choices_and_uses_exact_saved_dates(): void
    {
        [$unit, $midwife, $medicine] = $this->fixture();
        $this->record($unit, $midwife, $medicine, '2026-09-23', 7);
        $this->record($unit, $midwife, $medicine, '2026-10-12', 5);
        $page = $this->get(route('disease_reports.index', ['period_type' => 'monthly', 'periods' => 8,
            'medicine_id' => $medicine->id]))->assertOk();
        $link = $this->dom($page)->evaluate('string(//*[@id="saved-consumption-coverage"]/a/@href)');
        parse_str(parse_url($link, PHP_URL_QUERY), $parameters);
        $this->assertSame('2026-09-23', $parameters['date_from']);
        $this->assertSame('2026-10-12', $parameters['date_to']);
        $this->assertSame('monthly', $parameters['period_type']);
        $this->assertSame('8', $parameters['periods']);
        $this->assertSame((string) $medicine->id, $parameters['medicine_id']);
        $this->assertSame('', $parameters['barangay']);
        $this->assertSame('medicine-usage-details', parse_url($link, PHP_URL_FRAGMENT));
        $this->get(explode('#', $link, 2)[0])->assertOk()->assertViewHas('savedConsumptionCount', 2)
            ->assertViewHas('usage', fn ($usage) => $usage['historical'] && $usage['total_quantity'] === 12)
            ->assertSee('2026-09-23')->assertSee('2026-10-12');
        $this->get(route('disease_reports.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
            ->assertOk()->assertViewHas('savedConsumptionCount', 1)->assertViewHas('savedConsumptionFrom', '2026-10-12')
            ->assertViewHas('savedConsumptionTo', '2026-10-12');
    }

    public function test_saved_history_links_work_for_dispensing_dates_with_a_stored_time(): void
    {
        [$unit, , $medicine] = $this->fixture();
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->record($unit, $staff, $medicine, '2026-11-17 08:30:00', 7);
        $this->withSession(['user_id' => $staff->id]);

        $page = $this->get(route('disease_reports.index'))->assertOk();
        $dom = $this->dom($page);
        foreach (['View saved history', 'View saved consumption history'] as $label) {
            $link = $dom->evaluate('string(//a[normalize-space(.)="'.$label.'"]/@href)');
            $this->assertNotEmpty($link);
            parse_str(parse_url($link, PHP_URL_QUERY), $parameters);
            $this->assertSame('2026-11-17', $parameters['date_from']);
            $this->assertSame('2026-11-17', $parameters['date_to']);
            $this->get(explode('#', $link, 2)[0])->assertOk()
                ->assertViewHas('usage', fn ($usage) => $usage['total_quantity'] === 7);
        }
    }

    public function test_assigned_area_coverage_remains_limited_to_the_selected_assigned_barangay(): void
    {
        [$unit, $midwife, $medicine] = $this->fixture();
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Assigned Village', 'is_primary' => true]);
        $colleague = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->record($unit, $colleague, $medicine, '2026-09-23', 7, 'Assigned Village');
        $this->record($unit, $midwife, $medicine, '2026-10-12', 5, 'Unassigned Village');
        $this->get(route('disease_reports.index', ['consumption_scope' => 'area']))->assertOk()->assertViewHas('savedConsumptionCount', 1)
            ->assertViewHas('savedConsumptionFrom', '2026-09-23')->assertViewHas('savedConsumptionTo', '2026-09-23')
            ->assertViewHas('usage', fn ($usage) => $usage['scope'] === 'area' && $usage['total_quantity'] === 7);
        $this->get(route('disease_reports.index', ['barangay' => 'Unassigned Village']))->assertForbidden();
    }

    public function test_empty_personal_scope_does_not_claim_records_or_expand_to_rhu_history(): void
    {
        [$unit, , $medicine] = $this->fixture();
        $colleague = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->record($unit, $colleague, $medicine, '2026-10-12');
        $page = $this->get(route('disease_reports.index'))->assertOk()->assertViewHas('savedConsumptionCount', 0)
            ->assertViewHas('savedConsumptionFrom', null)->assertViewHas('savedConsumptionTo', null)
            ->assertViewHas('showUsageDetails', false)->assertDontSee('View saved history');
        $this->assertSame('0', $this->dom($page)->evaluate('string(//*[@data-empty-result="history"]/@data-empty-count)'));
    }
}
