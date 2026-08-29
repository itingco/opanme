<?php

namespace Tests\Feature;

use App\Models\StockOpnameCycle;
use App\Services\CycleSummaryExcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleSummaryExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_xlsx_with_summary_and_override_audit_columns(): void
    {
        $cycle = StockOpnameCycle::factory()->create([
            'status' => StockOpnameCycle::STATUS_CLOSED,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $row = (object) [
            'warehouse_code' => 'G-GATSU',
            'warehouse_name' => 'Gudang Gatsu',
            'item_code' => 'AAC1408',
            'item_name' => 'KOMPRESOR MINI',
            'opening_system_qty' => '10.0000',
            'physical_qty' => '9.0000',
            'override_qty' => '8.0000',
            'final_physical_qty' => '8.0000',
            'variance' => '-2.0000',
            'closing_system_qty' => '9.0000',
            'movement_qty' => '-1.0000',
            'scan_count' => 9,
            'override_comment' => 'Verifikasi fisik admin.',
            'override_by_name' => 'Admin',
            'override_updated_at' => now(),
        ];

        $path = storage_path('framework/testing/summary-export-test.xlsx');
        @unlink($path);

        app(CycleSummaryExcelService::class)->export($path, $cycle, [$row], null, false);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);
        $this->assertStringStartsWith('PK', $contents);
        $this->assertStringContainsString('xl/worksheets/sheet1.xml', $contents);
        $this->assertStringContainsString('xl/styles.xml', $contents);

        $autoFilterPosition = strpos($contents, '<autoFilter ');
        $mergeCellsPosition = strpos($contents, '<mergeCells ');

        $this->assertNotFalse($autoFilterPosition, 'Worksheet harus memiliki autoFilter.');
        $this->assertNotFalse($mergeCellsPosition, 'Worksheet harus memiliki mergeCells.');
        $this->assertLessThan(
            $mergeCellsPosition,
            $autoFilterPosition,
            'SpreadsheetML mewajibkan autoFilter berada sebelum mergeCells agar Excel tidak melakukan repair.'
        );

        @unlink($path);
    }
}
