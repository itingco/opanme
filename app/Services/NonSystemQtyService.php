<?php

namespace App\Services;

use InvalidArgumentException;

class NonSystemQtyService
{
    public function toSmallest(float|int|string $qty, float|int|string $ratio): string
    {
        $qtyF = (float) $qty;
        $ratioF = (float) $ratio;

        if ($qtyF < 0) {
            throw new InvalidArgumentException('Qty tidak boleh negatif.');
        }
        if ($ratioF <= 0) {
            throw new InvalidArgumentException('Ratio konversi harus lebih besar dari 0.');
        }

        return number_format($qtyF * $ratioF, 4, '.', '');
    }
}
