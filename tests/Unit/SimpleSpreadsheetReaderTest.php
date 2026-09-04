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

    public function test_it_reads_uploaded_xlsx_from_php_temp_path_without_extension(): void
    {
        $source = dirname(__DIR__).'/Fixtures/ratio-import-sample.xlsx';
        $temporaryUpload = tempnam(sys_get_temp_dir(), 'php-upload-');
        $this->assertNotFalse($temporaryUpload);
        $this->assertTrue(copy($source, $temporaryUpload));

        try {
            $rows = (new SimpleSpreadsheetReader())->read($temporaryUpload, 'xlsx');

            $this->assertSame(['Database', 'Barcode ERP', 'Ratio'], $rows[0]);
            $this->assertSame('0899123456789', $rows[1][1]);
        } finally {
            @unlink($temporaryUpload);
        }
    }
}
