<?php

namespace App\Support;

/** Batch lookup accepts exact labels and the numeric suffixes used by legacy logs. */
final class StockLogBatchFilter
{
    public static function apply($query, string $column, ?string $input): void
    {
        $candidates = self::candidates($input);
        if ($candidates === []) {
            return;
        }

        $query->where(function ($batch) use ($column, $candidates) {
            foreach ($candidates as $value) {
                $batch->orWhere($column, $value)
                    ->orWhere($column, 'like', '%-'.$value)
                    ->orWhere($column, 'like', '%-0'.$value)
                    ->orWhere($column, 'like', '%-00'.$value);
            }
        });
    }

    private static function candidates(?string $input): array
    {
        $candidates = [];
        foreach (explode(',', (string) $input) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $range)) {
                $start = min((int) $range[1], (int) $range[2]);
                $end = max((int) $range[1], (int) $range[2]);
                for ($offset = 0; $offset <= min(1000, $end - $start); $offset++) {
                    $candidates[] = (string) ($start + $offset);
                }
            } else {
                $candidates[] = $part;
            }
        }

        return array_values(array_unique($candidates));
    }
}
