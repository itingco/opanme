<?php

namespace Tests\Unit;

use App\Services\SimpleSpreadsheetReader;
use PHPUnit\Framework\TestCase;

class SimpleSpreadsheetReaderTest extends TestCase
{
    public function test_it_reads_first_xlsx_sheet_and_preserves_barcode_as_text(): void
    {
        $rows = (new SimpleSpreadsheetReader())->read(
            dirname(__DIR__).'/Fixtures/ratio-import-sample.xlsx'
        );

        $this->assertSame(['Database', 'Barcode ERP', 'Ratio'], $rows[0]);
        $this->assertSame('AS_INGCO', $rows[1][0]);
        $this->assertSame('0899123456789', $rows[1][1]);
        $this->assertSame('20', $rows[1][2]);
    }
}
