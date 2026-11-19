<?php

namespace App\Support;

use Illuminate\Http\Request;

final class InventoryHistoryPeriod
{
    public static function apply(Request $request): string
    {
        $values = $request->validate([
            'history_period' => ['nullable', 'in:all,day,range,today,this_month,last_month'],
        ]);
        $period = $values['history_period'] ?? ($request->filled('on_date') ? 'day'
            : ($request->filled('year') || $request->filled('date_from') || $request->filled('date_to') ? 'range' : 'all'));
        if ($period === 'range') {
            return $period;
        }
        $today = today();
        [$from, $to] = match ($period) {
            'day' => self::day($request),
            'today' => [$today->toDateString(), $today->toDateString()],
            'this_month' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => [null, null],
        };
        $request->merge(['year' => null, 'date_from' => $from, 'date_to' => $to]);

        return $period;
    }

    private static function day(Request $request): array
    {
        $values = $request->validate([
            'on_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
        ]);

        return [$values['on_date'], $values['on_date']];
    }
}
