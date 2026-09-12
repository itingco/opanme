<?php

namespace Tests\Unit;

use App\Label\Support\QrPayload;
use PHPUnit\Framework\TestCase;

class LabelQrPayloadTest extends TestCase
{
    public function test_it_combines_item_code_and_qty_with_a_dash(): void
    {
        self::assertSame('ABC001-25', QrPayload::make('ABC001', 25));
    }

    public function test_it_trims_surrounding_item_code_whitespace(): void
    {
        self::assertSame('ABC001-3', QrPayload::make('  ABC001  ', 3));
    }
}
