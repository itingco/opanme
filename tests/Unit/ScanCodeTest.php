<?php

namespace Tests\Unit;

use App\Support\ScanCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ScanCodeTest extends TestCase
{
    public function test_value_without_dash_is_normal_barcode(): void
    {
        $this->assertNull(ScanCode::parseQrPayload('8991234567890'));
    }

    public function test_qr_payload_is_split_using_last_dash(): void
    {
        $this->assertSame([
            'raw' => 'ABC-DEF-12',
            'item_code' => 'ABC-DEF',
            'qty' => 12,
        ], ScanCode::parseQrPayload('ABC-DEF-12'));
    }

    public function test_invalid_qty_after_dash_is_rejected_when_master_lookup_failed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ScanCode::parseQrPayload('ABC-XYZ');
    }
}
