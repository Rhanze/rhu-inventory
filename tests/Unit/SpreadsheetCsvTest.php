<?php

namespace Tests\Unit;

use App\Support\SpreadsheetCsv;
use PHPUnit\Framework\TestCase;

class SpreadsheetCsvTest extends TestCase
{
    public function test_formula_like_text_and_field_boundaries_are_safe_on_export(): void
    {
        $stream = fopen('php://temp', 'w+');
        SpreadsheetCsv::writeRow($stream, ['=Reference', '  @Reference', '＋Reference', "\tReference", 'Quoted, "text"', -12]);
        rewind($stream);

        $this->assertSame(["'=Reference", "'  @Reference", "'＋Reference", "'\tReference", 'Quoted, "text"', '-12'],
            fgetcsv($stream, escape: ''));
        $this->assertFalse(fgetcsv($stream, escape: ''));
        fclose($stream);
    }
}
