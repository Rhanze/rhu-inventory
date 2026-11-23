<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailableMedicineCountTest extends TestCase
{
    use RefreshDatabase;

    private function batch(Medicine $medicine, HealthUnit $unit, int $quantity, ?string $expiration = null): Inventory
    {
        return Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'available_stock' => $quantity, 'allocated_stock' => 0, 'expiration_date' => $expiration]);
    }

    public function test_pho_available_count_is_distinct_and_excludes_expired_zero_and_deleted_stock(): void
    {
        $unit = HealthUnit::create(['name' => 'Counting RHU', 'facility_type' => 'Rural Health Unit']);
        $other = HealthUnit::create(['name' => 'Other counting RHU', 'facility_type' => 'Rural Health Unit']);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $shared = Medicine::create(['medicine_name' => 'Counting shared item', 'category' => 'medicine']);
        $this->batch($shared, $unit, 20, today()->addMonth()->toDateString());
        $this->batch($shared, $unit, 40, today()->addMonths(2)->toDateString());
        $this->batch($shared, $other, 50, today()->addMonth()->toDateString());
        $vaccine = Medicine::create(['medicine_name' => 'Counting vaccine', 'category' => 'vaccine']);
        $this->batch($vaccine, $unit, 7);
        $todayExpiry = Medicine::create(['medicine_name' => 'Counting today expiry', 'category' => 'medicine']);
        $this->batch($todayExpiry, $unit, 2, today()->toDateString());
        $remote = Medicine::create(['medicine_name' => 'Remote-only item', 'category' => 'medicine']);
        $this->batch($remote, $other, 9);
        $expired = Medicine::create(['medicine_name' => 'Expired-only item', 'category' => 'medicine']);
        $this->batch($expired, $unit, 100, today()->subDay()->toDateString());
        $zero = Medicine::create(['medicine_name' => 'Zero-only item', 'category' => 'medicine']);
        $this->batch($zero, $unit, 0);
        $removedBatch = Medicine::create(['medicine_name' => 'Deleted batch item', 'category' => 'medicine']);
        $this->batch($removedBatch, $unit, 200)->delete();
        $removedMedicine = Medicine::create(['medicine_name' => 'Deleted catalog item', 'category' => 'medicine']);
        $this->batch($removedMedicine, $unit, 300);
        $removedMedicine->delete();
        $this->withSession(['user_id' => $pho->id]);

        $this->get(route('medicine.stock_on_hand'))->assertOk()->assertViewHas('availableMedicineCount', 4);
        $this->get(route('medicine.stock_on_hand', ['rhu_filter' => $unit->id]))->assertOk()->assertViewHas('availableMedicineCount', 3);
        $this->get(route('medicine.stock_on_hand', ['rhu_filter' => $other->id]))->assertOk()->assertViewHas('availableMedicineCount', 2);
        $this->get(route('medicine.stock_on_hand', ['category' => 'vaccine']))->assertOk()->assertViewHas('availableMedicineCount', 1);
        $this->get(route('medicine.stock_on_hand', ['category' => 'medicine', 'rhu_filter' => $unit->id]))->assertOk()->assertViewHas('availableMedicineCount', 2);
        $this->get(route('medicine.stock_on_hand', ['search' => 'Counting shared']))->assertOk()->assertViewHas('availableMedicineCount', 1);
        $this->get(route('medicine.stock_on_hand', ['availability' => 'available']))->assertOk()->assertViewHas('availableMedicineCount', 4);
        $this->get(route('medicine.stock_on_hand', ['availability' => 'unavailable']))->assertOk()->assertViewHas('availableMedicineCount', 0);

        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $staff->id])->get(route('medicine.stock_on_hand', ['rhu_filter' => $other->id]))
            ->assertOk()->assertViewHas('availableMedicineCount', 3);
    }

    public function test_available_medicine_count_covers_all_filtered_pages(): void
    {
        $unit = HealthUnit::create(['name' => 'Paged counting RHU']);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        for ($index = 1; $index <= 22; $index++) {
            $medicine = Medicine::create(['medicine_name' => 'Paged medicine '.$index, 'category' => 'medicine']);
            $this->batch($medicine, $unit, 10);
        }
        $this->withSession(['user_id' => $pho->id]);

        $this->get(route('medicine.stock_on_hand', ['search' => 'Paged medicine', 'page' => 1]))->assertOk()
            ->assertViewHas('availableMedicineCount', 22)->assertViewHas('items', fn ($items) => $items->count() === 20 && $items->total() === 22);
        $this->get(route('medicine.stock_on_hand', ['search' => 'Paged medicine', 'page' => 2, 'print' => 1]))->assertOk()
            ->assertViewHas('availableMedicineCount', 22)->assertViewHas('items', fn ($items) => $items->count() === 2 && $items->total() === 22)
            ->assertSee('Items 21&ndash;22 of 22', false)
            ->assertSee('Print includes one page. CSV includes all matching items.')
            ->assertSee('aria-label="Search stock by item name"', false);
    }

    public function test_stock_on_hand_page_and_export_use_the_same_expiry_and_dosage_rules(): void
    {
        $unit = HealthUnit::create(['name' => 'Local RHU', 'facility_type' => 'Rural Health Unit']);
        $other = HealthUnit::create(['name' => 'Other RHU', 'facility_type' => 'RHU']);
        $province = HealthUnit::create(['name' => 'Provincial Health Office', 'facility_type' => 'Provincial Health Office']);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $shared = Medicine::create(['medicine_name' => 'Shared tablet', 'category' => 'medicine', 'dosage_form' => 'Tablet']);
        $expired = Medicine::create(['medicine_name' => 'Expired tablet', 'category' => 'medicine', 'dosage_form' => 'Tablet']);
        $syrup = Medicine::create(['medicine_name' => 'Usable syrup', 'category' => 'medicine', 'dosage_form' => 'Syrup']);
        $this->batch($shared, $unit, 5, today()->toDateString());
        $this->batch($shared, $unit, 7);
        $this->batch($shared, $unit, 80, today()->subDay()->toDateString())->update(['allocated_stock' => 4]);
        $this->batch($shared, $unit, 500)->delete();
        $this->batch($shared, $other, 11);
        $this->batch($shared, $province, 3);
        $this->batch($expired, $unit, 90, today()->subDay()->toDateString());
        $this->batch($syrup, $unit, 8);

        $filters = ['availability' => 'available', 'dosage_form' => 'Tablet'];
        $parseCsvRow = static fn (string $row): array => str_getcsv($row, escape: '');
        $this->withSession(['user_id' => $pho->id])
            ->get(route('medicine.stock_on_hand', $filters))->assertOk()
            ->assertViewHas('availableMedicineCount', 1)
            ->assertViewHas('grandAvailable', 26)
            ->assertViewHas('grandAllocated', 4)
            ->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()->id === $shared->id)
            ->assertSee('aria-labelledby="stock-export-title"', false);
        $csv = $this->get(route('medicine.stock_on_hand.csv', $filters))->assertOk()->streamedContent();
        [$header, $row] = array_map($parseCsvRow, preg_split('/\R/', trim($csv)));
        $this->assertSame(['RHUs w/ Stock', 'Available', 'Allocated'], array_slice($header, -3));
        $this->assertSame(['2', '26', '4'], array_slice($row, -3));

        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $this->withSession(['user_id' => $staff->id])
            ->get(route('medicine.stock_on_hand', $filters + ['rhu_filter' => $other->id]))->assertOk()
            ->assertViewHas('grandAvailable', 12)
            ->assertSee('Low stock');

        $filters['availability'] = 'unavailable';
        $this->get(route('medicine.stock_on_hand', $filters))->assertOk()
            ->assertViewHas('grandAvailable', 0)
            ->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()->id === $expired->id);
        $csv = $this->get(route('medicine.stock_on_hand.csv', $filters))->assertOk()->streamedContent();
        [, $row] = array_map($parseCsvRow, preg_split('/\R/', trim($csv)));
        $this->assertSame('Expired tablet', $row[0]);
        $this->assertSame(['0', '0', '0'], array_slice($row, -3));
    }

    public function test_stock_on_hand_toolbar_filters_stay_native_and_preserve_active_filters(): void
    {
        $unit = HealthUnit::create(['name' => 'Filter RHU', 'facility_type' => 'Rural Health Unit']);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $filters = ['rhu_filter' => (string) $unit->id, 'category' => 'medicine', 'availability' => 'available'];

        $response = $this->withSession(['user_id' => $pho->id])
            ->get(route('medicine.stock_on_hand', $filters))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        foreach ($filters as $name => $value) {
            $select = '//select[@name="'.$name.'"][ancestor::form[@method="GET"]][ancestor-or-self::*[@data-native-select]]';
            $this->assertSame(1, (int) $xpath->evaluate('count('.$select.')'), "$name should stay a native dropdown.");
            $this->assertSame($value, $xpath->evaluate('string('.$select.'/option[@selected]/@value)'));
            foreach (array_diff_key($filters, [$name => $value]) as $otherName => $otherValue) {
                $this->assertSame($otherValue, $xpath->evaluate('string('.$select.'/ancestor::form/input[@type="hidden"][@name="'.$otherName.'"]/@value)'));
            }
        }
    }

    public function test_stock_suggestions_use_catalog_names_and_respect_assigned_rhu_and_filters(): void
    {
        $local = HealthUnit::create(['name' => 'Assigned RHU']);
        $other = HealthUnit::create(['name' => 'Another RHU']);
        $midwife = User::factory()->create(['role' => 'midwife', 'health_unit_id' => $local->id]);
        $tablet = Medicine::create(['medicine_name' => 'Local test stock', 'dosage' => '250 mg', 'dosage_form' => 'Tablet']);
        $syrup = Medicine::create(['medicine_name' => 'Liquid test stock', 'dosage_form' => 'Syrup']);
        $remote = Medicine::create(['medicine_name' => 'Other test stock', 'dosage_form' => 'Tablet']);
        $this->batch($tablet, $local, 5);
        $this->batch($syrup, $local, 5);
        $this->batch($remote, $other, 5);

        $filters = ['search' => 'test stock', 'availability' => 'available', 'dosage_form' => 'Tablet', 'rhu_filter' => $other->id];
        $this->withSession(['user_id' => $midwife->id])
            ->getJson(route('medicine.stock_on_hand.autocomplete', $filters))
            ->assertOk()->assertExactJson(['Local test stock']);

        $this->get(route('medicine.stock_on_hand', ['search' => 'Local test stock'] + $filters))
            ->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()->id === $tablet->id);
    }
}
