<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_history_link_opens_only_that_inventory_rows_movements_and_exports(): void
    {
        $unit = HealthUnit::create(['name' => 'Batch History RHU', 'facility_type' => 'Rural Health Unit']);
        $outside = HealthUnit::create(['name' => 'Unrelated RHU', 'facility_type' => 'Rural Health Unit']);
        $medicine = Medicine::create(['medicine_name' => 'Lot history inventory item']);
        [$first, $second] = collect(['Shelf A', 'Shelf B'])->map(fn ($shelf) => Inventory::create([
            'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'batch_no' => 'LOT-1',
            'available_stock' => 5, 'storage_location' => $shelf,
        ]))->all();
        foreach ([$first, $second] as $batch) {
            StockLog::create([
                'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'inventory_id' => $batch->id,
                'batch_no' => $batch->batch_no, 'quantity_added' => 5, 'date_added' => today(),
                'movement_type' => 'receipt', 'remarks' => 'Receipt at '.$batch->storage_location,
            ]);
        }
        $staff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unit->id]);
        $allocation = Allocation::create([
            'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'inventory_id' => $first->id,
            'quantity_allocated' => 2, 'date_allocated' => today(), 'allocated_by' => $staff->id,
            'destination' => 'Test clinic', 'status' => 'completed',
        ]);
        DB::table('stock_movements')->insert([
            'owner_type' => 'allocations', 'owner_id' => $allocation->id, 'inventory_id' => $first->id,
            'available_delta' => -2, 'created_at' => now(), 'reason' => 'Allocation ledger movement',
        ]);
        StockLog::create([
            'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id, 'allocation_id' => $allocation->id,
            'batch_no' => $first->batch_no, 'quantity_added' => 2, 'date_added' => today(), 'movement_type' => 'allocation',
        ]);
        $page = $this->withSession(['user_id' => $staff->id])
            ->get(route('inventory.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $url = $xpath->evaluate('string(//*[@id="inventory-'.$first->id.'"]//a[@title="Movement history"]/@href)');
        $this->assertNotEmpty($url);
        parse_str(parse_url($url, PHP_URL_QUERY), $filters);
        $this->get($url)->assertOk()->assertSee('Receipt at Shelf A')->assertDontSee('Receipt at Shelf B')
            ->assertSee('2 movements')->assertSee('Allocation ledger movement');
        $csv = $this->get(route('stock_logs.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('Receipt at Shelf A', $csv);
        $this->assertStringContainsString('Allocation ledger movement', $csv);
        $this->assertStringNotContainsString('Receipt at Shelf B', $csv);
        $this->withSession(['user_id' => User::factory()->create(['role' => 'staff', 'health_unit_id' => $outside->id])->id])
            ->get($url)->assertOk()->assertDontSee('Receipt at Shelf A');
    }

    public function test_all_inventory_pages_keep_the_three_sections_and_selected_facility_dates(): void
    {
        $unit = HealthUnit::create(['name' => 'Navigation RHU', 'facility_type' => 'RHU']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $viewer->id]);
        $filters = ['rhu_filter' => (string) $unit->id, 'year' => '2024',
            'date_from' => '2024-01-01', 'date_to' => '2024-01-31'];

        foreach ([['inventory.index', [], 'Inventory'], ['stock_logs.index', [], 'Stock logs'],
            ['inventory.index', ['view' => 'history'], 'Inventory history']] as [$route, $extra, $active]) {
            $response = $this->get(route($route, $filters + $extra))->assertOk();
            $previous = libxml_use_internal_errors(true);
            $dom = new \DOMDocument;
            $dom->loadHTML($response->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($dom);
            $links = $xpath->query('//nav[@aria-label="Inventory sections"]/a');
            $this->assertCount(3, $links);
            $this->assertSame(['Inventory', 'Stock logs', 'Inventory history'],
                array_map(fn ($link) => trim($link->textContent), iterator_to_array($links)));
            $selected = $xpath->query('//nav[@aria-label="Inventory sections"]/a[@aria-current="page"]');
            $this->assertCount(1, $selected);
            $this->assertSame($active, trim($selected->item(0)->textContent));
            foreach ($links as $link) {
                parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY) ?? '', $query);
                foreach ($filters as $key => $value) {
                    $this->assertSame($value, $query[$key] ?? null);
                }
                if (trim($link->textContent) === 'Inventory history') {
                    $this->assertSame('history', $query['view'] ?? null);
                }
            }
        }
    }
}
