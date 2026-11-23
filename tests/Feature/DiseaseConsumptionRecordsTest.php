<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\DiseaseMedicineMap;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\StockLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiseaseConsumptionRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Consumption records RHU']);
        $user = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Recorded medicine', 'dosage' => '10 mg',
            'dosage_form' => 'Capsule', 'unit' => 'capsule', 'is_dispensable' => true]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $medicine];
    }

    private function record(HealthUnit $unit, User $user, Medicine $medicine, array $extra = []): DispensingRecord
    {
        return DispensingRecord::create(array_replace(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id,
            'medicine_id' => $medicine->id, 'date_dispensed' => '2026-10-10', 'quantity_dispensed' => 7,
            'patient_name' => 'PATIENT-IDENTITY-NOT-FOR-ANALYSIS'], $extra));
    }

    private function dom(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }

    public function test_saved_unlinked_consumption_is_rendered_without_other_users_or_private_patient_fields(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $own = $this->record($unit, $user, $medicine, ['stock_source' => 'allocation']);
        $colleague = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        $this->record($unit, $colleague, $medicine);
        $otherUnit = HealthUnit::create(['name' => 'Other consumption records RHU']);
        $this->record($otherUnit, $user, $medicine);
        $this->record($unit, $user, $medicine, ['date_dispensed' => '2026-11-18']);
        $this->record($unit, $user, $medicine)->delete();
        $vaccine = Medicine::create(['medicine_name' => 'Recorded vaccine', 'category' => 'vaccine']);
        $this->record($unit, $user, $vaccine);

        $page = $this->get(route('disease_reports.index'))->assertOk()->assertSee('Recorded medicine consumption')
            ->assertSee('Received supplies')->assertSee('2026-10-10')->assertSee('Not recorded')
            ->assertDontSee('PATIENT-IDENTITY-NOT-FOR-ANALYSIS');
        $records = $page->viewData('consumptionRecords');
        $this->assertSame(1, $records->total());
        $this->assertSame([$own->id], $records->pluck('id')->all());
        $this->assertArrayNotHasKey('patient_name', $records->first()->getAttributes());
        $this->assertSame(0, $page->viewData('diseaseHistoryCount'));
        $this->assertSame(1, $this->dom($page->getContent())->query('//*[@id="recorded-consumption"]//tbody/tr')->length);
    }

    public function test_voided_and_reversed_records_are_not_shown_as_saved_consumption(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $valid = $this->record($unit, $user, $medicine);
        $voided = $this->record($unit, $user, $medicine);
        StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensing_record_id' => $voided->id, 'quantity_added' => 7,
            'movement_type' => 'dispensing', 'date_added' => '2026-10-10', 'voided_at' => now()]);
        $reversed = $this->record($unit, $user, $medicine);
        $batch = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'available_stock' => 0]);
        DB::table('stock_movements')->insert(['owner_type' => 'dispensing_records', 'owner_id' => $reversed->id,
            'inventory_id' => $batch->id, 'available_delta' => -7, 'allocated_delta' => 0, 'reversed_at' => now(), 'created_at' => now()]);

        $this->get(route('disease_reports.index'))->assertOk()->assertViewHas('consumptionRecords',
            fn ($records) => $records->total() === 1 && $records->first()->id === $valid->id);
    }

    public function test_search_matches_strength_and_form_while_dates_and_disease_filter_remain_exact(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $disease = Disease::create(['name' => 'Recorded association', 'code' => 'REC-ASSOC']);
        DiseaseMedicineMap::create(['disease_id' => $disease->id, 'medicine_id' => $medicine->id]);
        $match = $this->record($unit, $user, $medicine, ['date_dispensed' => '2026-10-02']);
        $this->record($unit, $user, $medicine, ['date_dispensed' => '2026-10-03']);
        $unlinked = Medicine::create(['medicine_name' => 'Recorded medicine', 'dosage' => '10 mg',
            'dosage_form' => 'Capsule', 'unit' => 'capsule', 'is_dispensable' => true]);
        $this->record($unit, $user, $unlinked, ['date_dispensed' => '2026-10-02']);
        $medicine->delete();

        $filters = ['consumption_search' => 'Recorded 10 mg Capsule', 'disease_id' => $disease->id,
            'date_from' => '2026-10-02', 'date_to' => '2026-10-02', 'period_type' => 'monthly', 'periods' => 8];
        $page = $this->get(route('disease_reports.index', $filters))->assertOk()->assertViewHas('consumptionRecords',
            fn ($records) => $records->total() === 1 && $records->first()->id === $match->id);
        $dom = $this->dom($page->getContent());
        $form = $dom->query('//*[@id="consumption-record-search"]/ancestor::form')->item(0);
        foreach (['disease_id', 'date_from', 'date_to', 'period_type', 'periods'] as $field) {
            $this->assertSame((string) $filters[$field], $dom->evaluate('string(.//input[@name="'.$field.'"]/@value)', $form));
        }
        $clear = $dom->evaluate('string(//*[@id="recorded-consumption"]//a[normalize-space(.)="Clear search"]/@href)');
        parse_str(parse_url($clear, PHP_URL_QUERY), $clearFilters);
        $this->assertArrayNotHasKey('consumption_search', $clearFilters);
        $this->assertSame('2026-10-02', $clearFilters['date_from']);
        $this->assertSame((string) $disease->id, $clearFilters['disease_id']);
    }

    public function test_assigned_area_records_include_colleagues_in_that_area_and_no_other_area(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        MidwifeBarangay::create(['user_id' => $user->id, 'barangay' => 'Assigned records village', 'is_primary' => true]);
        $colleague = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $inArea = $this->record($unit, $colleague, $medicine, ['barangay' => 'Assigned records village']);
        $this->record($unit, $user, $medicine, ['barangay' => 'Other records village']);
        $this->get(route('disease_reports.index', ['consumption_scope' => 'area']))->assertOk()->assertViewHas('consumptionRecords',
            fn ($records) => $records->total() === 1 && $records->first()->id === $inArea->id);
        $this->get(route('disease_reports.index', ['barangay' => 'Other records village']))->assertForbidden();
    }

    public function test_record_pagination_is_fifteen_rows_and_at_most_ten_numbers_with_filter_links_preserved(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        for ($index = 0; $index < 166; $index++) {
            $this->record($unit, $user, $medicine);
        }
        $filters = ['consumption_search' => 'Recorded', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31',
            'period_type' => 'monthly', 'periods' => 8, 'page' => 6];
        $page = $this->get(route('disease_reports.index', $filters))->assertOk();
        $records = $page->viewData('consumptionRecords');
        $this->assertSame(166, $records->total());
        $this->assertCount(15, $records);
        $this->assertSame(6, $records->currentPage());
        $dom = $this->dom($page->getContent());
        $nav = $dom->query('//nav[@aria-label="Consumption record pages"]')->item(0);
        $numbers = $dom->query('.//*[self::a or self::span][string(number(normalize-space(.))) != "NaN"]', $nav);
        $this->assertSame(10, $numbers->length);
        $this->assertSame('6', $dom->evaluate('string(.//*[@aria-current="page"])', $nav));
        $next = $dom->evaluate('string(.//a[@rel="next"]/@href)', $nav);
        parse_str(parse_url($next, PHP_URL_QUERY), $nextFilters);
        foreach (['consumption_search', 'date_from', 'date_to', 'period_type', 'periods'] as $field) {
            $this->assertSame((string) $filters[$field], $nextFilters[$field]);
        }
        $this->assertSame('7', $nextFilters['page']);
        $this->assertSame('recorded-consumption', parse_url($next, PHP_URL_FRAGMENT));
        $this->get(route('disease_reports.index', ['consumption_search' => str_repeat('x', 161)]))
            ->assertSessionHasErrors('consumption_search');
    }
}
