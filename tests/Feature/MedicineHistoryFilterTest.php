<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\DispensingRecord;
use App\Models\HealthUnit;
use App\Models\Medicine;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $unit = HealthUnit::create(['name' => 'Historical RHU']);
        $other = HealthUnit::create(['name' => 'Other historical RHU']);
        $staff = User::factory()->create(['health_unit_id' => $unit->id, 'role' => 'staff']);
        $this->withSession(['user_id' => $staff->id]);
        $records = [];
        foreach ([
            'before' => '2022-12-31', 'first' => '2023-01-01', 'middle' => '2023-06-15',
            'last' => '2023-12-31', 'after' => '2024-01-01',
        ] as $label => $date) {
            $medicine = Medicine::create(['medicine_name' => 'History '.$label, 'category' => 'medicine']);
            $records[$label] = $this->transactions($unit, $staff, $medicine, $date);
            $records[$label]['medicine'] = $medicine;
        }
        $medicine = Medicine::create(['medicine_name' => 'Outside history', 'category' => 'medicine']);
        $records['outside'] = $this->transactions($other, $staff, $medicine, '2023-06-15');
        $medicine = Medicine::create(['medicine_name' => 'Cancelled history', 'category' => 'medicine']);
        $cancelled = $this->transactions($unit, $staff, $medicine, '2023-06-15');
        $cancelled['records']->delete();
        $cancelled['allocations']->delete();
        $cancelled['stockLogs']->update(['voided_at' => now()]);

        return [$unit, $other, $staff, $records];
    }

    private function transactions(HealthUnit $unit, User $staff, Medicine $medicine, string $date): array
    {
        return [
            'records' => DispensingRecord::create([
                'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'quantity_dispensed' => 3, 'date_dispensed' => $date, 'dispensed_by' => $staff->id,
                'patient_name' => 'Fictional history patient',
            ]),
            'allocations' => Allocation::create([
                'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'quantity_allocated' => 5, 'date_allocated' => $date, 'allocated_by' => $staff->id,
                'destination' => 'Historical outreach', 'status' => 'approved',
            ]),
            'stockLogs' => StockLog::create([
                'health_unit_id' => $unit->id, 'medicine_id' => $medicine->id,
                'quantity_added' => 7, 'date_added' => $date, 'added_by' => $staff->id,
                'movement_type' => 'addition', 'remarks' => 'History fixture',
            ]),
        ];
    }

    private function modules(): array
    {
        return [
            ['records', 'dispensing.index', 'dispensing.export', 'reports.dispensing', 'reports.dispensing.csv'],
            ['allocations', 'allocation.index', 'allocation.export', 'reports.allocation', 'reports.allocation.csv'],
            ['stockLogs', 'stock_logs.index', 'stock_logs.export', 'reports.stock', 'reports.stock.csv'],
        ];
    }

    private function csvIds(string $content): array
    {
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($content)));

        return array_map('intval', array_column(array_slice($rows, 1), 0));
    }

    private function assertModuleMatches(array $module, array $filters, array $expected): void
    {
        [$variable, $list, $csv, $report, $reportCsv] = $module;
        foreach ([$list, $report] as $route) {
            $response = $this->get(route($route, $filters))->assertOk()
                ->assertViewHas($variable, fn ($rows) => $rows->pluck('id')->all() === $expected);
            if ($route === 'stock_logs.index') {
                $response->assertSee('aria-label="Stock log filters"', false)
                    ->assertSee('value="active" selected', false);

                continue;
            }

            $rule = match ($route) {
                'reports.allocation' => 'All saved statuses are included.',
                default => 'Cancelled or reversed transactions are excluded.',
            };
            $response->assertSee('Historical records')->assertSee($rule);
        }
        foreach ([$csv, $reportCsv] as $route) {
            $response = $this->get(route($route, $filters))->assertOk();
            $this->assertSame($expected, $this->csvIds($response->streamedContent()), $route);
        }
    }

    public function test_year_filters_include_both_boundaries_and_match_lists_reports_and_csv(): void
    {
        [, , , $records] = $this->fixtures();
        foreach ($this->modules() as $module) {
            $expected = array_map(fn ($label) => $records[$label][$module[0]]->id, ['last', 'middle', 'first']);
            $this->assertModuleMatches($module, ['year' => 2023], $expected);
        }
    }

    public function test_single_day_history_keeps_rhu_scope_and_excludes_cancelled_transactions(): void
    {
        [, $other, , $records] = $this->fixtures();
        foreach ($this->modules() as $module) {
            $this->assertModuleMatches($module, [
                'date_from' => '2023-06-15', 'date_to' => '2023-06-15', 'rhu_filter' => $other->id,
            ], [$records['middle'][$module[0]]->id]);
        }
    }

    public function test_ranges_can_cross_years_be_open_ended_or_intersect_a_selected_year(): void
    {
        [, , , $records] = $this->fixtures();
        $modules = $this->modules();
        $this->assertModuleMatches($modules[0], [
            'date_from' => '2023-12-31', 'date_to' => '2024-01-01',
        ], [$records['after']['records']->id, $records['last']['records']->id]);
        $this->assertModuleMatches($modules[1], ['date_to' => '2023-01-01'], [
            $records['first']['allocations']->id, $records['before']['allocations']->id,
        ]);
        $this->assertModuleMatches($modules[2], [
            'year' => 2023, 'date_from' => '2023-06-15', 'date_to' => '2024-01-01',
        ], [$records['last']['stockLogs']->id, $records['middle']['stockLogs']->id]);
    }

    public function test_invalid_or_nonoverlapping_dates_are_rejected_on_every_view_and_export(): void
    {
        $this->fixtures();
        foreach ($this->modules() as $module) {
            foreach (array_slice($module, 1) as $route) {
                $this->getJson(route($route, ['date_from' => '2023-12-31', 'date_to' => '2023-01-01']))
                    ->assertUnprocessable()->assertJsonValidationErrors('date_to');
                $this->getJson(route($route, ['year' => 2023, 'date_from' => '2024-01-01']))
                    ->assertUnprocessable()->assertJsonValidationErrors('year');
            }
        }
        $this->getJson(route('dispensing.index', ['date_from' => '2023-02-30']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_from');
    }

    public function test_retired_medicine_names_remain_available_in_filtered_history_and_exports(): void
    {
        [, , , $records] = $this->fixtures();
        $records['middle']['medicine']->delete();
        foreach ($this->modules() as $module) {
            $filters = ['date_from' => '2023-06-15', 'date_to' => '2023-06-15', 'category' => 'medicine'];
            $this->assertModuleMatches($module, $filters, [$records['middle'][$module[0]]->id]);
            $this->get(route($module[1], $filters))->assertOk()->assertSee('History middle');
            $csv = $this->get(route($module[2], $filters))->assertOk()->streamedContent();
            $this->assertStringContainsString('History middle', $csv);
        }
    }

    public function test_pagination_and_filter_forms_retain_the_selected_historical_period(): void
    {
        [$unit, , $staff, $records] = $this->fixtures();
        config(['pagination.dispensing' => 1, 'pagination.inventory' => 1]);
        for ($index = 0; $index < 20; $index++) {
            StockLog::create([
                'health_unit_id' => $unit->id, 'medicine_id' => $records['middle']['medicine']->id,
                'quantity_added' => 1, 'date_added' => '2023-06-15', 'added_by' => $staff->id,
                'movement_type' => 'addition',
            ]);
        }
        foreach ($this->modules() as $module) {
            $response = $this->get(route($module[1], ['year' => 2023, 'category' => 'medicine']))->assertOk();
            $response->assertViewHas($module[0], function ($rows) {
                parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);

                return ($query['year'] ?? null) === '2023' && ($query['category'] ?? null) === 'medicine';
            });
        }
    }

    public function test_search_links_open_the_correct_page_for_recently_entered_backdated_records(): void
    {
        [$unit, , $staff, $records] = $this->fixtures();
        for ($index = 0; $index < 20; $index++) {
            $this->transactions($unit, $staff, $records['after']['medicine'], '2025-01-01');
        }
        $medicine = Medicine::create(['medicine_name' => 'Backdated target', 'category' => 'medicine']);
        // These receive the newest IDs but belong on the last page by transaction date.
        $target = $this->transactions($unit, $staff, $medicine, '2021-01-01');
        foreach ([['records', 'dispensing'], ['allocations', 'allocation'], ['stockLogs', 'stock_logs']] as [$variable, $scope]) {
            $resultVariable = ['records' => 'dispensingResults', 'allocations' => 'allocationResults', 'stockLogs' => 'stockLogResults'][$variable];
            $this->get(route('search.index', ['scope' => $scope, 'q' => 'Backdated target']))->assertOk()
                ->assertViewHas($resultVariable, fn ($rows) => $rows->count() === 1 && $rows->first()->search_page === 2);
            $suggestions = $this->getJson(route('search.autocomplete', ['scope' => $scope, 'q' => 'Backdated target']))
                ->assertOk()->assertJsonCount(1);
            $this->assertStringContainsString('page=2', $suggestions->json('0.url'));
            $this->get($suggestions->json('0.url'))->assertOk()
                ->assertViewHas($variable, fn ($rows) => $rows->contains('id', $target[$variable]->id));
        }
    }

    public function test_operator_name_and_username_search_match_between_dated_lists_and_csv(): void
    {
        [, , $staff, $records] = $this->fixtures();
        $staff->update(['name' => 'Historical operator', 'username' => 'history_operator']);
        foreach ([['records', 'dispensing.index', 'dispensing.export'], ['stockLogs', 'stock_logs.index', 'stock_logs.export']] as [$variable, $list, $csv]) {
            foreach (['Historical operator', 'history_operator'] as $search) {
                $filters = ['search' => $search, 'date_from' => '2023-06-15', 'date_to' => '2023-06-15'];
                $expected = [$records['middle'][$variable]->id];
                $this->get(route($list, $filters))->assertOk()
                    ->assertViewHas($variable, fn ($rows) => $rows->pluck('id')->all() === $expected);
                $response = $this->get(route($csv, $filters))->assertOk();
                $this->assertSame($expected, $this->csvIds($response->streamedContent()));
            }
        }
    }
}
