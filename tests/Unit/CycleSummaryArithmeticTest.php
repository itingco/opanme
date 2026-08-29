<?php

namespace Tests\Unit;

use App\Services\CycleSummaryService;
use PHPUnit\Framework\TestCase;

class CycleSummaryArithmeticTest extends TestCase
{
    public function test_it_calculates_variance_and_net_movement(): void
    {
        $service = new CycleSummaryService();
        $summary = $service->calculateValues(100, 95, 90);

        $this->assertSame('95.0000', $summary['final_physical_qty']);
        $this->assertFalse($summary['has_override']);
        $this->assertSame('-5.0000', $summary['variance']);
        $this->assertSame('-10.0000', $summary['movement_qty']);
        $this->assertTrue($summary['has_movement']);
    }

    public function test_override_becomes_final_physical_without_changing_scan_qty(): void
    {
        $service = new CycleSummaryService();
        $summary = $service->calculateValues(100, 95, 90, 98);

        $this->assertSame('95.0000', $summary['physical_qty']);
        $this->assertSame('98.0000', $summary['final_physical_qty']);
        $this->assertTrue($summary['has_override']);
        $this->assertSame('-2.0000', $summary['variance']);
        $this->assertSame('-10.0000', $summary['movement_qty']);
    }

    public function test_movement_is_unknown_without_closing_snapshot(): void
    {
        $service = new CycleSummaryService();
        $summary = $service->calculateValues(100, 100, null);

        $this->assertNull($summary['movement_qty']);
        $this->assertFalse($summary['has_movement']);
    }
}
