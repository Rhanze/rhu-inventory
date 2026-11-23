<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\HealthUnit;
use App\Models\Inventory;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationReportScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocations_report_includes_each_record_for_either_facility_once(): void
    {
        $pho = HealthUnit::create(['name' => 'Supply PHO', 'facility_type' => 'Provincial Health Office']);
        $rhu = HealthUnit::create(['name' => 'Receiving RHU', 'facility_type' => 'Rural Health Unit']);
        $unrelated = HealthUnit::create(['name' => 'Other RHU', 'facility_type' => 'Rural Health Unit']);
        $phoAdmin = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $rhuStaff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $rhu->id]);
        $medicine = Medicine::create(['medicine_name' => 'Report fixture stock']);
        Inventory::create(['medicine_id' => $medicine->id, 'health_unit_id' => $pho->id, 'available_stock' => 100]);

        $date = today()->toDateString();
        $this->withSession(['user_id' => $phoAdmin->id])->post(route('allocation.province.store'), [
            'medicine_id' => $medicine->id, 'destination_health_unit_id' => $rhu->id,
            'quantity_allocated' => 15, 'date_allocated' => $date,
        ])->assertRedirect(route('allocation.index'))->assertSessionHas('success');
        $incoming = Allocation::sole();
        $incoming->update(['destination' => 'Obsolete free-text destination']);
        $this->assertSame(15, (int) Inventory::where('health_unit_id', $rhu->id)->sum('available_stock'));
        $record = ['medicine_id' => $medicine->id, 'quantity_allocated' => 5, 'date_allocated' => $date];
        $outgoing = Allocation::create($record + ['health_unit_id' => $rhu->id, 'status' => 'pending']);
        $bothSides = Allocation::create($record + ['health_unit_id' => $rhu->id, 'destination_health_unit_id' => $rhu->id, 'status' => 'received']);
        Allocation::create($record + ['health_unit_id' => $unrelated->id, 'status' => 'completed']);
        Allocation::create($record + ['health_unit_id' => $rhu->id, 'status' => 'completed'])->delete();
        Allocation::create(array_replace($record, ['date_allocated' => today()->subDay()->toDateString()])
            + ['health_unit_id' => $pho->id, 'destination_health_unit_id' => $rhu->id, 'status' => 'completed']);

        $dates = ['date_from' => $date, 'date_to' => $date];
        $rhuFilters = $dates + ['rhu_filter' => $unrelated->id];
        $expected = [$bothSides->id => 5, $outgoing->id => 5, $incoming->id => 15];
        $this->withSession(['user_id' => $rhuStaff->id]);
        $this->assertAllocationReportRows($rhuFilters, $expected, $rhu->name);
        $this->assertAllocationReportRows($rhuFilters + ['status' => 'completed'], [$incoming->id => 15], $rhu->name);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['allocations_count'] === 3);

        $this->withSession(['user_id' => $phoAdmin->id]);
        $this->assertAllocationReportRows($dates + ['rhu_filter' => $rhu->id], $expected, $rhu->name);
        $this->assertAllocationReportRows($dates + ['rhu_filter' => $rhu->id, 'status' => 'completed'], [$incoming->id => 15], $rhu->name);

        $unrelatedStaff = User::factory()->create(['role' => 'staff', 'health_unit_id' => $unrelated->id]);
        $unrelatedAllocation = Allocation::where('health_unit_id', $unrelated->id)->sole();
        $this->withSession(['user_id' => $unrelatedStaff->id]);
        $this->assertAllocationReportRows($dates + ['rhu_filter' => $rhu->id, 'status' => 'completed'], [$unrelatedAllocation->id => 5], $unrelated->name);
    }

    private function assertAllocationReportRows(array $filters, array $expected, string $facilityScope): void
    {
        $report = $this->get(route('reports.allocation', $filters))->assertOk();
        $records = $report->viewData('allocations');
        $this->assertSame($expected, $records->pluck('quantity_allocated', 'id')->all());
        $report->assertSee(count($expected).' saved allocation records')
            ->assertSee('Records include '.$facilityScope.' as a source or destination.')
            ->assertDontSee('Obsolete free-text destination');
        if (isset($filters['status'])) {
            $report->assertSee('<option value="'.$filters['status'].'" selected>', false);
        }
        $csv = $this->get(route('reports.allocation.csv', $filters))->assertOk()->streamedContent();
        $lines = array_map(fn ($line) => str_getcsv($line, escape: ''), preg_split('/\R/', trim($csv)));
        $header = array_shift($lines);
        $rows = array_map(fn ($row) => array_combine($header, $row), $lines);
        $this->assertSame($expected, array_map('intval', array_column($rows, 'Quantity', 'ID')));
        $this->assertSame($records->pluck('destination_display')->all(), array_column($rows, 'Destination'));
        $this->assertSame($records->pluck('status_label')->all(), array_column($rows, 'Status'));
        $this->assertSame($records->pluck('healthUnit.name')->all(), array_column($rows, 'Source Facility'));
        $this->assertSame(array_fill(0, count($expected), $facilityScope), array_column($rows, 'Facility Scope'));
    }
}
