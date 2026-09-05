<?php

namespace Tests\Feature;

use App\Models\UomRatio;
use App\Services\RatioExportService;
use App\Services\SimpleSpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatioExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ratio_export_contains_item_uom_and_ratio(): void
    {
        UomRatio::create([
            'item_code' => 'ITEM-A',
            'uom_code' => 'KTK',
            'ratio' => 20,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'ratio-export-test-').'.xlsx';

        try {
            $this->app->make(RatioExportService::class)->export($path);
            $rows = $this->app->make(SimpleSpreadsheetReader::class)->read($path, 'xlsx');

            $this->assertSame(['ItemCode', 'UOM', 'Ratio'], $rows[0]);
            $this->assertSame(['ITEM-A', 'KTK', '20'], $rows[1]);
        } finally {
            @unlink($path);
        }
    }
}
