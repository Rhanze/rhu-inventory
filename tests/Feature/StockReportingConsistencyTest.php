<?php

namespace Tests\Feature;

use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ELMISExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReportingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $unit = HealthUnit::create(['name' => 'Reporting RHU']);
        $user = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $medicine = Medicine::create(['medicine_name' => 'Report fixture', 'category' => 'medicine']);
        $this->withSession(['user_id' => $user->id, 'role' => 'staff', 'health_unit_id' => $unit->id]);
        $rows = collect();
        foreach ([
            ['dispensing', null, 7],
            ['transfer_out', 'Added misleading description', 11],
            ['receipt', 'Released misleading description', 13],
            ['transfer_in', null, 17],
            [null, 'dispensed legacy', 19],
            [null, 'Allocated legacy', 23],
            [null, 'Stock intake', 29],
            ['adjustment', null, 31],
        ] as [$type, $remarks, $quantity]) {
            $rows->push(StockLog::create([
                'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'quantity_added' => $quantity, 'date_added' => today(), 'added_by' => $user->id,
                'movement_type' => $type, 'remarks' => $remarks,
            ]));
        }
        $voided = StockLog::create([
            'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
            'quantity_added' => 999, 'date_added' => today(), 'movement_type' => 'dispensing',
            'voided_at' => now(), 'remarks' => 'Released voided',
        ]);

        return [$unit, $rows, $voided];
    }

    public function test_stock_pages_and_csv_filters_agree_on_direction_and_exclude_voided_movements(): void
    {
        [$unit, $rows] = $this->fixtures();
        $releases = [$rows[0]->id, $rows[1]->id, $rows[4]->id, $rows[5]->id];
        $additions = [$rows[2]->id, $rows[3]->id, $rows[6]->id];
        foreach (['stock_out' => $releases, 'stock_in' => $additions] as $type => $expected) {
            $this->get(route('reports.stock', ['type' => $type]))->assertOk()
                ->assertViewHas('stockLogs', fn ($logs) => $logs->pluck('id')->sort()->values()->all() === $expected);
            $this->get(route('stock_logs.index', ['type' => $type === 'stock_out' ? 'release' : 'addition']))->assertOk()
                ->assertViewHas('stockLogs', fn ($logs) => $logs->pluck('id')->sort()->values()->all() === $expected);
        }
        $csv = $this->get(route('reports.stock.csv', ['type' => 'stock_out']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Stock In', $csv);
        $this->assertSame(4, substr_count($csv, 'Stock Out'));
        $this->get(route('reports.stock'))->assertOk()->assertSee('Unknown');
        $csv = $this->get(route('stock_logs.export'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Released voided', $csv);
    }

    public function test_stock_logs_offer_one_filter_form_and_preserve_saved_filters(): void
    {
        [, $rows] = $this->fixtures();
        $xpath = function ($response): \DOMXPath {
            $document = new \DOMDocument;
            $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

            return new \DOMXPath($document);
        };
        $form = '//form[@method="GET" and @action="'.route('stock_logs.index').'"]';
        $default = $xpath($this->get(route('stock_logs.index'))->assertOk());
        $this->assertSame(1, $default->query($form)->length);
        $this->assertSame(1, $default->query($form.'/descendant::input[@name="search" and not(ancestor::details)]')->length);
        $this->assertSame(1, $default->query($form.'/details[not(@open)]')->length);

        $filters = ['search' => 'Report fixture', 'date_to' => today()->toDateString(), 'status' => 'active',
            'movement_type' => 'receipt', 'medicine_id' => $rows[0]->medicine_id];
        $filtered = $xpath($this->get(route('stock_logs.index', $filters))->assertOk()
            ->assertViewHas('stockLogs', fn ($logs) => $logs->total() === 1 && $logs->first()->id === $rows[2]->id));
        $this->assertSame(1, $filtered->query($form.'/details[@open]')->length);
        $this->assertSame('receipt', $filtered->evaluate('string('.$form.'//input[@name="movement_type"]/@value)'));
        $this->assertSame($filters['date_to'], $filtered->evaluate('string('.$form.'//input[@name="date_to"]/@value)'));

        $movement = '//details[@id="stock-log-'.$rows[2]->id.'" and not(@open)]';
        $this->assertSame(1, $filtered->query($movement)->length);
        $summary = $filtered->evaluate('normalize-space('.$movement.'/summary)');
        $this->assertStringContainsString('Report fixture', $summary);
        $this->assertStringContainsString('Stock received', $summary);
        $this->assertStringContainsString('13 units', $summary);
        $this->assertStringNotContainsString('Released misleading description', $summary);
        $this->assertStringContainsString('Released misleading description', $filtered->evaluate('normalize-space('.$movement.'/dl)'));
    }

    public function test_elmis_uses_explicit_types_and_serializes_plain_dates(): void
    {
        [$unit] = $this->fixtures();
        $report = app(ELMISExportService::class)->generateStockMovementReport($unit->id);
        $byQuantity = collect($report['rows'])->keyBy('quantity');
        foreach ([7 => 'RELEASE', 11 => 'RELEASE', 13 => 'ADDITION', 17 => 'ADDITION',
            19 => 'RELEASE', 23 => 'RELEASE', 29 => 'ADDITION', 31 => 'UNKNOWN'] as $quantity => $direction) {
            $this->assertSame($direction, $byQuantity[$quantity]['transaction_type']);
            $this->assertSame(today()->toDateString(), $byQuantity[$quantity]['transaction_date']);
        }
        $this->assertCount(8, $report['rows']);
    }

    public function test_operational_stock_records_redact_dispensing_remarks_in_views_search_and_csv(): void
    {
        [$unit, $rows] = $this->fixtures();
        $record = DispensingRecord::create(['health_unit_id' => $unit->id, 'dispensed_by' => $rows[0]->added_by,
            'medicine_id' => $rows[0]->medicine_id, 'quantity_dispensed' => 4, 'date_dispensed' => today()]);
        $rows[0]->update(['batch_no' => 'TYPED-BATCH', 'remarks' => 'Typed private fixture']);
        $rows[4]->update(['batch_no' => 'LEGACY-BATCH', 'remarks' => 'Released - Dispensed to Legacy private fixture']);
        $linked = StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $record->medicine_id,
            'added_by' => $record->dispensed_by, 'batch_no' => 'LINKED-BATCH', 'date_added' => today(),
            'quantity_added' => 4, 'dispensing_record_id' => $record->id, 'movement_type' => 'adjustment_out',
            'remarks' => 'Linked private fixture with no dispensing prefix']);
        $pho = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $pho->id]);

        foreach ([[], ['evidence' => 1]] as $filters) {
            $this->get(route('stock_logs.index', $filters))->assertOk()
                ->assertSee('stock-log-'.$linked->id, false)->assertSee('Dispensing #'.$record->id)
                ->assertDontSee('private fixture')->assertSee('Released misleading description');
            $csv = $this->get(route('stock_logs.export', $filters))->assertOk()->streamedContent();
            $this->assertStringNotContainsString('private fixture', $csv);
            $this->assertStringContainsString('Released misleading description', $csv);
            $this->assertStringContainsString('Dispensing #'.$record->id, $csv);
            foreach (['TYPED-BATCH', 'LEGACY-BATCH', 'LINKED-BATCH'] as $batch) {
                $this->assertStringContainsString($batch, $csv);
            }
        }

        $this->get(route('inventory.index', ['view' => 'history']))->assertOk()
            ->assertSee('Dispensing #'.$record->id)->assertDontSee('private fixture');
        $this->get(route('stock_logs.index', ['evidence' => 1, 'search' => 'private fixture']))->assertOk()
            ->assertViewHas('stockLogs', fn ($logs) => $logs->total() === 0);
        $this->get(route('stock_logs.index', ['evidence' => 1, 'search' => 'dispensing #'.$record->id]))->assertOk()
            ->assertViewHas('stockLogs', fn ($logs) => $logs->total() === 1 && $logs->first()->event_id === $linked->id);
    }

    public function test_elmis_allows_an_end_date_without_a_start_and_rejects_reversed_ranges(): void
    {
        $this->fixtures();
        $this->getJson(route('elmis.export.movement', ['date_to' => today()->toDateString()]))->assertOk();
        $this->getJson(route('elmis.export.movement', [
            'date_from' => today()->toDateString(), 'date_to' => today()->subDay()->toDateString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_dashboard_totals_use_the_same_typed_stock_directions(): void
    {
        [$unit] = $this->fixtures();
        $movement = app(DashboardService::class)->getDashboardData(false, $unit->id)['stockMovement'];
        $this->assertSame(3, array_sum($movement['additions']));
        $this->assertSame(4, array_sum($movement['releases']));
    }

    public function test_active_stock_logs_and_evidence_exclude_a_cancelled_transfer_even_if_its_legacy_log_was_not_voided(): void
    {
        [$unit, $rows] = $this->fixtures();
        $destination = HealthUnit::create(['name' => 'Cancelled transfer destination']);
        $transfer = TransferRequest::create(['from_health_unit_id' => $unit->id,
            'to_health_unit_id' => $destination->id, 'medicine_id' => $rows[0]->medicine_id,
            'quantity' => 15, 'status' => 'cancelled', 'requested_by' => session('user_id')]);
        $cancelled = StockLog::create(['health_unit_id' => $unit->id, 'medicine_id' => $rows[0]->medicine_id,
            'quantity_added' => 15, 'date_added' => today(), 'movement_type' => 'transfer_out',
            'transfer_id' => $transfer->id, 'remarks' => 'Legacy cancelled transfer']);

        foreach ([[], ['status' => 'active', 'evidence' => 1]] as $filters) {
            $this->get(route('stock_logs.index', $filters))->assertOk()->assertDontSee('Legacy cancelled transfer')
                ->assertViewHas('stockLogs', fn ($logs) => $logs->total() === 8);
        }
        $this->get(route('stock_logs.index', ['status' => 'cancelled']))->assertOk()
            ->assertViewHas('stockLogs', fn ($logs) => $logs->total() === 1 && $logs->first()->event_id === $cancelled->id)
            ->assertSee('Legacy cancelled transfer')->assertSee('Cancelled');
        $this->assertStringNotContainsString('Legacy cancelled transfer',
            $this->get(route('stock_logs.export'))->assertOk()->streamedContent());
    }
}
