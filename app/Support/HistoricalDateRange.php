<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Inclusive business-date filters shared by history screens and their exports. */
final class HistoricalDateRange
{
    private function __construct(
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?int $year,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $values = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,9999'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01'],
        ]);
        $year = isset($values['year']) && $values['year'] !== '' ? (int) $values['year'] : null;
        $from = ($values['date_from'] ?? null) ?: null;
        $to = ($values['date_to'] ?? null) ?: null;
        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages(['date_to' => 'The end date must be on or after the start date.']);
        }
        if ($year !== null) {
            $yearFrom = sprintf('%04d-01-01', $year);
            $yearTo = sprintf('%04d-12-31', $year);
            $from = $from === null ? $yearFrom : max($from, $yearFrom);
            $to = $to === null ? $yearTo : min($to, $yearTo);
            if ($from > $to) {
                throw ValidationException::withMessages(['year' => 'The dates must overlap the selected year. Clear the year to search across years.']);
            }
        }

        return new self($from, $to, $year);
    }

    public function apply($query, string $dateColumn)
    {
        if ($this->from !== null) {
            $query->whereDate($dateColumn, '>=', $this->from);
        }
        if ($this->to !== null) {
            $query->whereDate($dateColumn, '<=', $this->to);
        }

        return $query;
    }

    public function isFiltered(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    public function label(): string
    {
        if ($this->year !== null && $this->from === sprintf('%04d-01-01', $this->year)
            && $this->to === sprintf('%04d-12-31', $this->year)) {
            return (string) $this->year;
        }
        if ($this->from !== null && $this->from === $this->to) {
            return $this->from;
        }
        if ($this->from !== null && $this->to !== null) {
            return $this->from.' to '.$this->to;
        }

        return $this->from !== null ? 'From '.$this->from : ($this->to !== null ? 'Through '.$this->to : 'All saved dates');
    }
}
