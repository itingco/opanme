<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SummaryOverrideVisibilityTest extends TestCase
{
    public function test_override_audit_action_is_positioned_after_item_before_numeric_columns(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/cycles/summary.blade.php');

        $item = strpos($blade, '<th>Item</th>');
        $audit = strpos($blade, '<th class="summary-action-col">Override / Audit</th>');
        $opening = strpos($blade, '<th class="num">Opening</th>');

        $this->assertNotFalse($item);
        $this->assertNotFalse($audit);
        $this->assertNotFalse($opening);
        $this->assertTrue($item < $audit && $audit < $opening);
        $this->assertStringContainsString('Override / Audit', $blade);
        $this->assertStringContainsString('Simpan Override', $blade);
    }
}
