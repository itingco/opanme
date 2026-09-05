<?php

namespace Tests\Unit;

use App\Services\SimpleSpreadsheetReader;
use PHPUnit\Framework\TestCase;

class PrefixedXlsxReaderTest extends TestCase
{
    public function test_reader_accepts_namespace_prefixed_xlsx_generated_template(): void
    {
        $rows = (new SimpleSpreadsheetReader())->read(
            dirname(__DIR__, 2).'/resources/templates/item-barcode-import-template.xlsx',
            'xlsx'
        );

        $this->assertSame(['ItemCode', 'Barcode', 'UOM'], $rows[0]);
        $this->assertSame(['ITEMA', '899000000001', 'PCS'], $rows[1]);
        $this->assertSame(['ITEMB', '0012345678901', 'PCS'], $rows[4]);
    }
}
