<?php

namespace Tests\Unit;

use App\Services\CycleSummaryService;
use PHPUnit\Framework\TestCase;

class NonSystemSummaryArithmeticTest extends TestCase
{
    public function test_non_system_item_uses_zero_opening_and_final_physical_as_variance(): void
    {
        $values = (new CycleSummaryService())->calculateValues(0, 40, 0, null);

        $this->assertSame('40.0000', $values['final_physical_qty']);
        $this->assertSame('40.0000', $values['variance']);
        $this->assertSame('0.0000', $values['movement_qty']);
    }

    public function test_non_system_override_replaces_scan_quantity(): void
    {
        $values = (new CycleSummaryService())->calculateValues(0, 40, 0, 36);

        $this->assertSame('36.0000', $values['final_physical_qty']);
        $this->assertSame('36.0000', $values['variance']);
        $this->assertTrue($values['has_override']);
    }
}
