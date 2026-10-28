<?php

namespace App\Support;

use InvalidArgumentException;

final class ScanCode
{
    /**
     * Parse QR label payload in the form ITEM-CODE-QTY.
     *
     * Call this parser ONLY after an exact lookup in the barcode master fails.
     * A registered barcode containing "-" must always be treated as master barcode.
     *
     * The last dash is used as separator, so item codes may also contain dashes.
     * Example: ABC-DEF-12 => item_code ABC-DEF, qty 12.
     *
     * @return array{raw:string,item_code:string,qty:int}|null
     */
    public static function parseQrPayload(string $value): ?array
    {
        $value = trim($value);

        if ($value === '' || ! str_contains($value, '-')) {
            return null;
        }

        $separator = strrpos($value, '-');
        $itemCode = trim(substr($value, 0, $separator));
        $qtyRaw = trim(substr($value, $separator + 1));

        if ($itemCode === '' || strlen($itemCode) > 100) {
            throw new InvalidArgumentException(
                'Format QR tidak valid. Gunakan KODEBARANG-QTY, contoh: ABC123-10.'
            );
        }

        // QR label generator only creates positive whole-number quantities.
        // Invalid QR fallback is rejected once no barcode-master record exists.
        if (! preg_match('/^[1-9][0-9]{0,11}$/', $qtyRaw)) {
            throw new InvalidArgumentException(
                'Format QR tidak valid. Bagian setelah tanda "-" harus QTY bilangan bulat lebih dari 0.'
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
