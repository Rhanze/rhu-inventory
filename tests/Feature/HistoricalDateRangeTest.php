<?php

namespace Tests\Feature;

use App\Support\HistoricalDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HistoricalDateRangeTest extends TestCase
{
    private function range(array $parameters = []): HistoricalDateRange
    {
        return HistoricalDateRange::fromRequest(Request::create('/history', 'GET', $parameters));
    }

    private function assertInvalid(array $parameters, string $field): void
    {
        try {
            $this->range($parameters);
            $this->fail('An invalid history date selection was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    /** Execute against synthetic dates only; no application tables or saved records are touched. */
    private function filteredDates(HistoricalDateRange $range): array
    {
        $dates = [
            '2023-12-31 23:59:59',
            '2024-01-01 00:00:00',
            '2024-02-29 00:00:00',
            '2024-02-29 23:59:59',
            '2024-12-31 23:59:59',
            '2025-01-01 00:00:00',
        ];
        $source = null;
        foreach ($dates as $date) {
            $row = DB::query()->selectRaw('? as occurred_at', [$date]);
            $source = $source === null ? $row : $source->unionAll($row);
        }

        return $range->apply(DB::query()->fromSub($source, 'saved_records'), 'occurred_at')
            ->orderBy('occurred_at')->pluck('occurred_at')->all();
    }

    public function test_no_filter_preserves_all_saved_dates(): void
    {
        $range = $this->range();

        $this->assertNull($range->from);
        $this->assertNull($range->to);
        $this->assertNull($range->year);
        $this->assertFalse($range->isFiltered());
        $this->assertSame('All saved dates', $range->label());
        $this->assertCount(6, $this->filteredDates($range));
    }

    public function test_blank_optional_filters_leave_history_unrestricted(): void
    {
        $range = $this->range(['year' => '', 'date_from' => '', 'date_to' => '']);

        $this->assertFalse($range->isFiltered());
        $this->assertNull($range->year);
        $this->assertCount(6, $this->filteredDates($range));
    }

    public function test_year_filter_includes_the_entire_first_and_last_day_only(): void
    {
        $range = $this->range(['year' => '2024']);

        $this->assertSame(2024, $range->year);
        $this->assertSame('2024-01-01', $range->from);
        $this->assertSame('2024-12-31', $range->to);
        $this->assertTrue($range->isFiltered());
        $this->assertSame('2024', $range->label());
        $this->assertSame([
            '2024-01-01 00:00:00', '2024-02-29 00:00:00',
            '2024-02-29 23:59:59', '2024-12-31 23:59:59',
        ], $this->filteredDates($range));
    }

    public function test_exact_leap_day_includes_both_midnight_and_end_of_day(): void
    {
        $range = $this->range(['date_from' => '2024-02-29', 'date_to' => '2024-02-29']);

        $this->assertSame('2024-02-29', $range->label());
        $this->assertSame(['2024-02-29 00:00:00', '2024-02-29 23:59:59'], $this->filteredDates($range));
    }

    public function test_date_range_can_cross_years_without_a_year_filter(): void
    {
        $range = $this->range(['date_from' => '2023-12-31', 'date_to' => '2025-01-01']);

        $this->assertNull($range->year);
        $this->assertSame('2023-12-31 to 2025-01-01', $range->label());
        $this->assertCount(6, $this->filteredDates($range));
    }

    public function test_year_and_explicit_dates_select_their_intersection(): void
    {
        $range = $this->range(['year' => '2024', 'date_from' => '2023-11-01', 'date_to' => '2024-02-29']);

        $this->assertSame('2024-01-01', $range->from);
        $this->assertSame('2024-02-29', $range->to);
        $this->assertSame('2024-01-01 to 2024-02-29', $range->label());
        $this->assertCount(3, $this->filteredDates($range));

        $wideRange = $this->range(['year' => 2024, 'date_from' => '2023-01-01', 'date_to' => '2025-12-31']);
        $this->assertSame('2024', $wideRange->label());
        $this->assertCount(4, $this->filteredDates($wideRange));
    }

    public function test_open_ended_ranges_keep_their_inclusive_boundary(): void
    {
        $from = $this->range(['date_from' => '2024-02-29']);
        $this->assertNull($from->to);
        $this->assertSame('From 2024-02-29', $from->label());
        $this->assertCount(4, $this->filteredDates($from));

        $through = $this->range(['date_to' => '2024-02-29']);
        $this->assertNull($through->from);
        $this->assertSame('Through 2024-02-29', $through->label());
        $this->assertCount(4, $this->filteredDates($through));
    }

    public function test_supported_year_and_date_boundaries_are_accepted(): void
    {
        foreach ([1900, 9999] as $year) {
            $range = $this->range(['year' => $year]);
            $this->assertSame($year, $range->year);
            $this->assertSame($year.'-01-01', $range->from);
            $this->assertSame($year.'-12-31', $range->to);
        }
        $this->assertSame('1900-01-01', $this->range(['date_from' => '1900-01-01'])->from);
        $this->assertSame('9999-12-31', $this->range(['date_to' => '9999-12-31'])->to);
    }

    public function test_malformed_dates_and_impossible_leap_days_are_rejected(): void
    {
        foreach (['2023-02-29', '2024-02-30', '2024-2-01', '02/29/2024', '2024-02-29T12:00:00', '1899-12-31', 'tomorrow'] as $date) {
            $this->assertInvalid(['date_from' => $date], 'date_from');
            $this->assertInvalid(['date_to' => $date], 'date_to');
        }
    }

    public function test_fractional_out_of_range_and_nonnumeric_years_are_rejected(): void
    {
        foreach (['2024.5', '1899', '10000', '-2024', '2024abc', ['2024']] as $year) {
            $this->assertInvalid(['year' => $year], 'year');
        }
    }

    public function test_reversed_dates_are_rejected_before_year_intersection(): void
    {
        $this->assertInvalid(['date_from' => '2024-03-01', 'date_to' => '2024-02-29'], 'date_to');
        $this->assertInvalid(['year' => 2024, 'date_from' => '2025-01-01', 'date_to' => '2023-12-31'], 'date_to');
    }

    public function test_dates_outside_selected_year_are_rejected_instead_of_returning_an_ambiguous_range(): void
    {
        foreach ([
            ['date_from' => '2025-01-01', 'date_to' => '2025-02-01'],
            ['date_from' => '2023-01-01', 'date_to' => '2023-12-31'],
            ['date_from' => '2025-01-01'],
            ['date_to' => '2023-12-31'],
        ] as $dates) {
            $this->assertInvalid(['year' => 2024] + $dates, 'year');
        }
    }
}
