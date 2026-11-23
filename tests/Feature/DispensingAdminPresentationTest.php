<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\MidwifeBarangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DispensingAdminPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'admin'): array
    {
        $unit = HealthUnit::create(['name' => 'Presentation RHU', 'facility_type' => 'Rural Health Unit']);
        $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Pagination medicine', 'category' => 'medicine', 'requires_prescription' => false]);
        $inventory = Inventory::create(['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'available_stock' => 100, 'allocated_stock' => 0, 'expiration_date' => today()->addMonth()]);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $medicine, $inventory];
    }

    public function test_admin_and_staff_forms_preserve_history_and_facility_views_show_barangay_context(): void
    {
        [$unit, $user, $medicine, $inventory] = $this->fixture();
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $unit->id]);
        MidwifeBarangay::create(['user_id' => $midwife->id, 'barangay' => 'Only assigned area']);
        $payload = ['inventory_id' => $inventory->id, 'quantity_dispensed' => 5, 'date_dispensed' => today()->toDateString()];

        foreach (['admin', 'staff'] as $role) {
            $user->update(['role' => $role]);
            $this->get(route('dispensing.create'))->assertOk()->assertDontSee('Barangay served')->assertDontSee('name="barangay"', false);
            $this->post(route('dispensing.store'), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
            $record = DispensingRecord::latest('id')->firstOrFail();
            $this->assertNull($record->barangay);
            $record->update(['barangay' => 'Preserved historical area']);
            $this->get(route('dispensing.edit', $record->id))->assertOk()->assertDontSee('Barangay served')->assertDontSee('name="barangay"', false);
            $this->put(route('dispensing.update', $record->id), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
            $this->assertSame('Preserved historical area', $record->fresh()->barangay);
            $this->get(route('dispensing.index'))->assertOk()->assertSee('Dispensing by barangay')
                ->assertSee('Barangay served')->assertSee('Preserved historical area');
            $csv = $this->get(route('dispensing.export'))->assertOk()->streamedContent();
            $this->assertStringContainsString('Barangay served', $csv);
            $this->assertStringContainsString('Preserved historical area', $csv);
        }
    }

    public function test_midwife_keeps_barangay_entry_history_and_export_context(): void
    {
        [$unit, $user, $medicine, $inventory] = $this->fixture('midwife');
        MidwifeBarangay::create(['user_id' => $user->id, 'barangay' => 'Midwife area']);
        $this->get(route('dispensing.create'))->assertOk()->assertSee('Barangay served')->assertSee('name="barangay"', false);
        $this->post(route('dispensing.store'), ['inventory_id' => $inventory->id, 'quantity_dispensed' => 5,
            'date_dispensed' => today()->toDateString()])->assertSessionHasNoErrors()->assertSessionHas('success');
        $record = DispensingRecord::sole();
        $this->get(route('dispensing.edit', $record->id))->assertOk()->assertSee('Barangay served');
        $this->get(route('dispensing.index'))->assertOk()->assertSee('Barangay served')->assertSee('Midwife area');
        $csv = $this->get(route('dispensing.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Barangay served', $csv);
        $this->assertStringContainsString('Midwife area', $csv);
    }

    public function test_barangay_filters_precede_grouped_totals_and_missing_records_have_one_group(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        $liquid = Medicine::create(['medicine_name' => 'Oral solution', 'unit' => 'bottle']);
        $record = ['health_unit_id' => $unit->id, 'dispensed_by' => $user->id, 'date_dispensed' => today(), 'quantity_dispensed' => 4];
        DispensingRecord::create($record + ['medicine_id' => $medicine->id, 'barangay' => null]);
        DispensingRecord::create($record + ['medicine_id' => $liquid->id, 'barangay' => null]);
        DispensingRecord::create($record + ['medicine_id' => $medicine->id, 'barangay' => 'Not recorded']);

        foreach (['dispensing.index', 'reports.dispensing'] as $route) {
            $page = $this->get(route($route))->assertOk()
                ->assertSeeInOrder(['Filter by barangay', 'Dispensing by barangay', 'No barangay recorded'])
                ->assertDontSee('Apply barangay filter')->assertDontSee('Show records without a barangay');
            $document = new \DOMDocument;
            $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $group = '//section[@aria-labelledby="dispensing-area-heading"]//details[summary[contains(., "No barangay recorded")]]';
            $this->assertCount(1, $xpath->query($group));
            $this->assertCount(0, $xpath->query($group.'[@open]'));
            $this->assertCount(2, $xpath->query($group.'//tbody/tr'));
            $this->assertStringContainsString('2 records', $xpath->evaluate('string('.$group.'/summary)'));
            $this->assertCount(1, $xpath->query('//section[@aria-labelledby="dispensing-area-heading"]//details[summary[contains(., "Not recorded")]]'));

            $filtered = $this->get(route($route, ['barangay_missing' => 1]))->assertOk();
            $document->loadHTML($filtered->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $this->assertCount(1, (new \DOMXPath($document))->query($group.'[@open]'));
        }
    }

    public function test_barangay_dropdown_is_native_beside_import_and_preserves_other_filters(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $user->id, 'date_dispensed' => today(),
            'medicine_id' => $medicine->id, 'quantity_dispensed' => 4, 'barangay' => 'Not recorded']);
        $filters = ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString(), 'medicine_id' => $medicine->id];

        foreach (['dispensing.index', 'reports.dispensing'] as $route) {
            $page = $this->get(route($route, $filters + ['barangay_missing' => 1, 'page' => 2]))->assertOk();
            $document = new \DOMDocument;
            $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $select = '//*[@id="dispensing-barangay-filter"]';

            $this->assertCount(1, $xpath->query($select));
            $this->assertCount(1, $xpath->query($select.'[@data-native-select][@onchange="window.location.assign(this.value)"]/preceding-sibling::*[1][self::a[@class="csv-import-link"]]'));
            $this->assertSame(route($route, $filters), $xpath->evaluate('string('.$select.'/option[normalize-space(.)="All barangays"]/@value)'));
            $this->assertSame(route($route, $filters + ['barangay' => 'Not recorded']), $xpath->evaluate('string('.$select.'/option[normalize-space(.)="Not recorded"]/@value)'));
            $this->assertSame(route($route, $filters + ['barangay_missing' => 1]), $xpath->evaluate('string('.$select.'/option[@selected and normalize-space(.)="No barangay recorded"]/@value)'));
            $this->assertSame(route($route, array_diff_key($filters, ['medicine_id' => true]) + ['barangay_missing' => 1]),
                $xpath->evaluate('string(//a[normalize-space(.)="Clear medicine filter"]/@href)'));
        }
    }

    public function test_dispensing_pagination_shows_a_sliding_window_of_at_most_ten_numbered_pages(): void
    {
        [$unit, $user, $medicine] = $this->fixture();
        config(['pagination.dispensing' => 1]);
        $rows = array_fill(0, 30, ['health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'dispensed_by' => $user->id, 'quantity_dispensed' => 1, 'date_dispensed' => today()->toDateString(),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('dispensing_records')->insert($rows);

        foreach ([1 => range(1, 10), 15 => range(11, 20), 30 => range(21, 30)] as $page => $expected) {
            $response = $this->get(route('dispensing.index', ['page' => $page, 'search' => 'Pagination', 'category' => 'medicine']))->assertOk();
            preg_match_all('/data-page-number="(\d+)"/', $response->getContent(), $numbers);
            $this->assertSame($expected, array_map('intval', $numbers[1]));
            $response->assertSee('Previous')->assertSee('Next')->assertSee('data-page-number="'.$page.'" aria-current="page"', false);
            $this->assertStringContainsString('search=Pagination', $response->getContent());
            $this->assertStringContainsString('category=medicine', $response->getContent());
        }
        config(['pagination.dispensing' => 6]);
        $response = $this->get(route('dispensing.index'))->assertOk();
        preg_match_all('/data-page-number="(\d+)"/', $response->getContent(), $numbers);
        $this->assertSame(range(1, 5), array_map('intval', $numbers[1]));
    }
}
