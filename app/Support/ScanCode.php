<?php

namespace App\Support;

use InvalidArgumentException;

final class ScanCode
{
    /**
     * Parse QR label payload dalam bentuk KODE~QTY.
     *
     * Parser ini dipanggil setelah exact lookup ke master barcode gagal.
     * Barcode master tetap selalu diprioritaskan, sehingga kode/barcode biasa
     * yang tidak memiliki tanda "~" tidak berubah perilakunya.
     *
     * @return array{raw:string,item_code:string,qty:int}|null
     */
    public static function parseQrPayload(string $value): ?array
    {
        $value = trim($value);

        if ($value === '' || ! str_contains($value, '~')) {
            return null;
        }

        $separator = strrpos($value, '~');
        $itemCode = trim(substr($value, 0, $separator));
        $qtyRaw = trim(substr($value, $separator + 1));

        if ($itemCode === '' || strlen($itemCode) > 100) {
            throw new InvalidArgumentException(
                'Format QR tidak valid. Gunakan KODEBARANG~QTY, contoh: ABC123~10.'
            );
        }

        if (! preg_match('/^[1-9][0-9]{0,11}$/', $qtyRaw)) {
            throw new InvalidArgumentException(
                'Format QR tidak valid. Bagian setelah tanda "~" harus QTY bilangan bulat lebih dari 0.'
            );
        }

        $qty = (int) $qtyRaw;
        if ($qty <= 0) {
            throw new InvalidArgumentException('QTY pada QR harus lebih dari 0.');
        }

        return [
            'raw' => $value,
            'item_code' => $itemCode,
            'qty' => $qty,
        ];
    }
}
