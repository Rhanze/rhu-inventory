<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHistoryPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'Asia/Manila'));
    }

    private function fixture(): array
    {
        $unit = HealthUnit::create(['name' => 'Period history RHU', 'facility_type' => 'RHU']);
        $user = User::factory()->create(['role' => 'admin', 'health_unit_id' => $unit->id]);
        $medicine = Medicine::create(['medicine_name' => 'Period tablets', 'unit' => 'tablet', 'is_dispensable' => true]);
        $batch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'available_stock' => 100, 'allocated_stock' => 0, 'batch_no' => 'PERIOD-OPENING',
            'expiration_date' => '2028-01-01']);
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $unit->id,
            'inventory_id' => $batch->id, 'batch_no' => $batch->batch_no, 'movement_type' => 'receipt',
            'quantity_added' => 100, 'date_added' => '2026-09-01']);
        $this->withSession(['user_id' => $user->id]);

        return [$unit, $user, $medicine, $batch];
    }

    private function dispense(Inventory $batch, string $date, int $quantity): void
    {
        $this->post(route('dispensing.store'), ['inventory_id' => $batch->id, 'quantity_dispensed' => $quantity,
            'date_dispensed' => $date, 'patient_name' => 'Fictional period patient'])->assertSessionHas('success');
    }

    public function test_last_month_shows_its_closing_stock_and_period_totals_with_dated_searchable_rows(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $this->dispense($batch, '2026-10-15', 7);
        $this->post(route('allocation.store'), ['inventory_id' => $batch->id, 'quantity_allocated' => 10,
            'date_allocated' => '2026-10-16', 'destination' => 'October materials'])->assertSessionHas('success');
        $this->dispense($batch, '2026-11-02', 3);

        $response = $this->get(route('inventory.index', ['view' => 'history', 'history_period' => 'last_month']))
            ->assertOk()->assertSee('Individual movements')->assertSee('Stock balances and recorded activity')
            ->assertSeeInOrder(['Stock on closing date', 'Oct 31, 2026', 'Recorded activity dates', 'Oct 1, 2026 to Oct 31, 2026'])
            ->assertDontSee('Totals by facility')
            ->assertDontSee('<th>What</th>', false)->assertDontSee('<th>Who</th>', false)
            ->assertDontSee('<th>When</th>', false)->assertDontSee('<th>Where</th>', false)
            ->assertSee('Oct 16, 2026')->assertDontSee('Nov 02, 2026');
        $this->assertSame('2026-10-01', $response->viewData('historyRange')->from);
        $this->assertSame('2026-10-31', $response->viewData('historyRange')->to);
        $row = $response->viewData('stockSummary')->first();
        $this->assertTrue($row->balance_complete);
        $this->assertSame(83, $row->closing_available);
        $this->assertSame(10, $row->closing_reserved);
        $this->assertSame(7, $row->dispensed_units);
        $this->assertSame(1, $row->dispensing_records);
        $this->assertSame(10, $row->allocated_units);

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = new \DOMXPath($document);
        $this->assertSame(1.0, $links->evaluate('count(//details[@class="inventory-history-card history-movement-preview" and not(@open)])'));
        $this->assertSame(1.0, $links->evaluate('count(//details[@class="history-preview-filters" and not(@open)])'));
        $movementUrl = $links->evaluate('string(//a[text()="View these movements in Stock Logs"]/@href)');
        $this->assertStringContainsString('status=all', $movementUrl);
        $this->get($movementUrl)->assertOk()->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 2);
        $balanceUrl = $links->evaluate('string(//a[@aria-label="83 available. View stock movements for Period tablets."]/@href)');
        $this->assertStringNotContainsString('date_from', $balanceUrl);
        $this->get($balanceUrl)->assertOk()->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 3
            && $rows->getCollection()->sum('quantity_added') === 117);
        $dispensingUrl = $links->evaluate('string(//a[@aria-label="7 dispensed. View dispensing movements for Period tablets."]/@href)');
        $this->get($dispensingUrl)->assertOk()->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 1
            && $rows->first()->quantity_added === 7 && $rows->first()->actor_name === $user->name);
        $allocationUrl = $links->evaluate('string(//a[@aria-label="10 allocated. View allocation movements for Period tablets."]/@href)');
        $this->get($allocationUrl)->assertOk()->assertViewHas('stockLogs', fn ($rows) => $rows->total() === 1
            && $rows->first()->quantity_added === 10 && $rows->first()->facility_name === $unit->name);

        $allocation = Allocation::firstOrFail();
        $filtered = $this->get(route('inventory.index', ['view' => 'history', 'history_period' => 'last_month',
            'search' => 'Allocation #'.$allocation->id, 'action' => 'allocation', 'medicine_id' => $medicine->id]))
            ->assertOk()->assertSee('1 entry')->assertSee('Allocation #'.$allocation->id)
            ->assertSee('October materials')->assertSee($user->name)->assertSee($batch->batch_no)
            ->assertViewHas('movements', fn ($rows) => $rows->total() === 1);
        $this->assertSame(7, $filtered->viewData('stockSummary')->first()->dispensed_units);
        $document->loadHTML($filtered->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = new \DOMXPath($document);
        $this->assertSame(1.0, $links->evaluate('count(//details[@class="inventory-history-card history-movement-preview" and @open])'));
        $this->assertSame(1.0, $links->evaluate('count(//details[@class="history-preview-filters" and @open])'));
        $this->assertSame(1.0, $links->evaluate('count(//details[contains(concat(" ", normalize-space(@class), " "), " stock-movement-details ") and not(@open)])'));
    }

    public function test_one_date_counts_dispensing_once_and_excludes_other_dates(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $this->dispense($batch, '2026-10-15', 7);
        $this->dispense($batch, '2026-10-16', 11);
        $response = $this->get(route('inventory.index', ['view' => 'history', 'history_period' => 'day',
            'on_date' => '2026-10-15', 'action' => 'dispensing']))->assertOk()
            ->assertViewHas('movements', fn ($rows) => $rows->total() === 1);
        $this->assertSame('2026-10-15', $response->viewData('historyRange')->label());
        $row = $response->viewData('stockSummary')->first();
        $this->assertSame(7, $row->dispensed_units);
        $this->assertSame(1, $row->dispensing_records);
        $this->assertSame(93, $row->closing_available);
    }

    public function test_last_month_handles_leap_years_and_single_date_rejects_future_dates(): void
    {
        $this->fixture();
        $this->travelTo(Carbon::parse('2024-03-31 12:00:00', 'Asia/Manila'));
        $this->get(route('inventory.index', ['view' => 'history', 'history_period' => 'last_month']))->assertOk()
            ->assertViewHas('historyRange', fn ($range) => $range->from === '2024-02-01' && $range->to === '2024-02-29');
        $this->getJson(route('inventory.index', ['view' => 'history', 'history_period' => 'day',
            'on_date' => '2024-04-01']))->assertUnprocessable()->assertJsonValidationErrors('on_date');
    }

    public function test_incomplete_history_is_explicit_and_invalid_summary_page_does_not_crash(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $batch->update(['available_stock' => 200]);
        $this->get(route('inventory.index', ['view' => 'history', 'history_period' => 'last_month']))->assertOk()
            ->assertSee('Balance unavailable.')->assertSee('The saved records are insufficient to verify the stock balance.')
            ->assertDontSee('View stock movements for Period tablets.')->assertSee('0 dispensed.')
            ->assertViewHas('stockSummary', fn ($rows) => !$rows->first()->balance_complete);
        $this->getJson(route('inventory.index', ['view' => 'history', 'summary_page' => '999999999999999999999']))
            ->assertUnprocessable()->assertJsonValidationErrors('summary_page');
    }

    public function test_province_history_preserves_totals_and_links_each_facility_to_its_own_period_movements(): void
    {
        [$unit, $user, $medicine, $batch] = $this->fixture();
        $this->dispense($batch, '2026-10-15', 7);
        $otherUnit = HealthUnit::create(['name' => 'Second period RHU', 'facility_type' => 'RHU']);
        $otherUser = User::factory()->create(['role' => 'admin', 'health_unit_id' => $otherUnit->id]);
        $otherBatch = Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $otherUnit->id,
            'available_stock' => 50, 'allocated_stock' => 0, 'batch_no' => 'PERIOD-OPENING', 'expiration_date' => '2028-01-01']);
        StockLog::create(['medicine_id' => $medicine->id, 'health_unit_id' => $otherUnit->id,
            'inventory_id' => $otherBatch->id, 'batch_no' => $otherBatch->batch_no, 'movement_type' => 'receipt',
            'quantity_added' => 50, 'date_added' => '2026-09-01']);
        $this->withSession(['user_id' => $otherUser->id]);
        $this->dispense($otherBatch, '2026-10-16', 3);
        $this->dispense($otherBatch, '2026-11-02', 5);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $pho->id]);

        $response = $this->get(route('inventory.index', ['view' => 'history', 'history_period' => 'last_month']))
            ->assertOk()->assertSee('Combined totals across all facilities')->assertSee('Totals by facility (2)')
            ->assertSee($unit->name)->assertSee($otherUnit->name)->assertSee('Movement status');
        $row = $response->viewData('stockSummary')->sole();
        $this->assertSame(140, $row->closing_available);
        $this->assertSame(10, $row->dispensed_units);
        $facilities = collect($row->facilities)->keyBy('health_unit_id');
        $this->assertSame(93, $facilities[$unit->id]['closing_available']);
        $this->assertSame(47, $facilities[$otherUnit->id]['closing_available']);
        $this->assertSame(3, $facilities[$otherUnit->id]['dispensed_units']);

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = new \DOMXPath($document);
        $this->assertSame(1.0, $links->evaluate('count(//details[@class="history-facilities" and not(@open)])'));
        $url = $links->evaluate('string(//a[@aria-label="3 dispensed. View dispensing movements for Period tablets at Second period RHU."]/@href)');
        $this->get($url)->assertOk()->assertViewHas('stockLogs', fn ($logs) => $logs->total() === 1
            && $logs->first()->quantity_added === 3 && $logs->first()->health_unit_id === $otherUnit->id);
        $balanceUrl = $links->evaluate('string(//a[@aria-label="47 available. View stock movements for Period tablets at Second period RHU."]/@href)');
        $this->assertStringNotContainsString('date_from', $balanceUrl);
        $this->get($balanceUrl)->assertOk()->assertViewHas('stockLogs', fn ($logs) => $logs->total() === 2
            && $logs->every(fn ($log) => $log->health_unit_id === $otherUnit->id));

        $filtered = $this->get(route('inventory.index', ['view' => 'history', 'history_period' => 'last_month', 'status' => 'reversal']))
            ->assertOk()->assertSee('Totals exclude cancelled and reversed activity.')
            ->assertViewHas('movements', fn ($logs) => $logs->isEmpty());
        $this->assertSame(10, $filtered->viewData('stockSummary')->sole()->dispensed_units);
    }
}
