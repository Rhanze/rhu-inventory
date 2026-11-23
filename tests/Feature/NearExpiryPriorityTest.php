<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearExpiryPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_priority_report_and_csv_agree_on_the_calendar_cutoff_and_facility(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 15:00', 'Asia/Manila'));
        $rhu = HealthUnit::create(['name' => 'Expiry RHU', 'facility_type' => 'Rural Health Unit']);
        $other = HealthUnit::create(['name' => 'Other RHU', 'facility_type' => 'Rural Health Unit']);
        $pho = HealthUnit::create(['name' => 'Provincial Health Office', 'facility_type' => 'Provincial Health Office']);
        $medicine = Medicine::create(['medicine_name' => 'Expiry test medicine', 'unit' => 'tablet']);
        $removed = Medicine::create(['medicine_name' => 'Archived medicine']);

        $batch = fn (HealthUnit $facility, string $number, ?string $expiry, array $attributes = []) => Inventory::create($attributes + [
            'health_unit_id' => $facility->id,
            'medicine_id' => $medicine->id,
            'batch_no' => $number,
            'expiration_date' => $expiry,
            'available_stock' => 10,
        ]);

        $batch($rhu, 'BATCH-CUTOFF', '2026-12-18');
        $batch($rhu, 'BATCH-TODAY', '2026-11-18');
        $batch($other, 'BATCH-OTHER-RHU', '2026-11-21');
        $batch($pho, 'BATCH-PHO', '2026-12-04');
        $batch($rhu, 'BATCH-NEXT-DAY', '2026-12-19');
        foreach ([1, 6, 14] as $day) {
            $batch($rhu, 'BATCH-JANUARY-'.$day, sprintf('2027-01-%02d', $day));
        }
        $batch($rhu, 'BATCH-EXPIRED', '2026-11-17');
        $batch($rhu, 'BATCH-NO-EXPIRY', null);
        $batch($rhu, 'BATCH-RESERVED', '2026-12-02', ['available_stock' => 0, 'allocated_stock' => 10]);
        $batch($rhu, 'BATCH-DELETED', '2026-11-26')->delete();
        $batch($rhu, 'BATCH-ARCHIVED-MEDICINE', '2026-11-27', ['medicine_id' => $removed->id]);
        $removed->delete();

        foreach ([null, $rhu->id] as $facilityId) {
            $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => $facilityId]);
            $this->withSession(['user_id' => $viewer->id]);

            $expected = $facilityId === null
                ? ['BATCH-TODAY', 'BATCH-OTHER-RHU', 'BATCH-PHO', 'BATCH-CUTOFF']
                : ['BATCH-TODAY', 'BATCH-CUTOFF'];
            $dashboard = $this->get(route('dashboard'))->assertOk()
                ->assertSee('Nov 18, 2026 through Dec 18, 2026')
                ->assertSee('View Near Expiry report');
            $this->assertSame(count($expected), $dashboard->viewData('stats')['near_expiry_count']);
            $this->assertSame($expected, $dashboard->viewData('nearExpiryItems')->pluck('batch_no')->all());
            $this->assertSame(count($expected), json_decode($dashboard->viewData('chartDataJson'), true)['expiration']['data'][0]);

            // An RHU viewer's own facility always takes priority over a supplied filter.
            $filters = $facilityId === null ? [] : ['rhu_filter' => $other->id];
            $report = $this->get(route('reports.near_expiry', $filters))->assertOk()
                ->assertSee('Nov 18, 2026 through Dec 18, 2026')
                ->assertSee('Expires today');
            $this->assertSame($expected, $report->viewData('inventories')->pluck('batch_no')->all());

            $csv = $this->get(route('reports.near_expiry.csv', $filters))->assertOk();
            $lines = preg_split('/\R/', trim($csv->streamedContent()));
            $rows = array_map(fn ($line) => str_getcsv($line, escape: ''), array_slice($lines, 1));
            $this->assertSame($expected, array_column($rows, 1));
            $this->assertSame('2026-11-18', $rows[0][4]);
            $this->assertSame('Expires today', $rows[0][5]);

            $inventoryFilters = ['expiry_filter' => 'near_expiry', 'rhu_filter' => $facilityId === null ? 'all' : $other->id];
            $inventory = $this->get(route('inventory.index', $inventoryFilters))->assertOk();
            $this->assertSame($expected, $inventory->viewData('inventories')->pluck('batch_no')->all());
            $export = $this->get(route('inventory.export', $inventoryFilters))->assertOk();
            $lines = preg_split('/\R/', trim($export->streamedContent()));
            $rows = array_map(fn ($line) => str_getcsv($line, escape: ''), array_slice($lines, 1));
            $this->assertSame($expected, array_column($rows, 2));

            $generalReportFilters = $filters + ['expiry_filter' => 'near_expiry'];
            $generalReport = $this->get(route('reports.inventory', $generalReportFilters))->assertOk();
            $generalReport->assertSee('Near Expiry (0–30 days)')->assertDontSee('≤180 days');
            $this->assertSame($expected, $generalReport->viewData('inventories')->pluck('batch_no')->all());
        }
    }
}
