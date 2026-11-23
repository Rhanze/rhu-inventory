<?php

namespace App\Support;

final class SpreadsheetCsv
{
    /** Write a report row with potentially formula-like text escaped at the cell boundary. */
    public static function writeRow($stream, array $fields): void
    {
        $fields = array_map(function ($field) {
            if (is_string($field) && preg_match('/\A(?:[\t\r\n]|[\s\p{Z}\p{Cf}]*[=+\-@＝＋－＠])/u', $field)) {
                return "'".$field;
            }

            return $field;
        }, $fields);

        fputcsv($stream, $fields, escape: '');
    }
}
