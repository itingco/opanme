<?php

namespace App\Label\Support;

final class QrPayload
{
    public static function make(string $itemCode, int $qty): string
    {
        return trim($itemCode).'-'.$qty;
    }
}
