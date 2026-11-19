<?php
namespace App\Services;

use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\UomRatio;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/** Adaptasi khusus Gerai: input qty UOM barcode, DB tetap menyimpan satuan terkecil. */
class GeraiCycleCheckingService extends SampleCheckingService
{
    public function lookup(User $user, SampleCycle $cycle, string $barcode): array
    {
        $data = parent::lookup($user, $cycle, $barcode);
        $itemCode = (string) $data['item_code'];
        $uom = (string) $data['uom_code'];
        $catalog = app(ErpCatalogService::class);
        $base = $catalog->findItemByCode($cycle->source_database, $itemCode);
        $smallest = (string) ($base['uom_code'] ?? $uom);
        $scanItem = $catalog->findItemByCodeAndUom($cycle->source_database, $itemCode, $uom);
        $level = (int) ($scanItem['uom_level'] ?? 1);
        $ratio = 1.0;
        if ($level > 1) {
            $ratioRow = UomRatio::query()->where('item_code', $itemCode)->where('uom_code', $uom)->first();
            if (!$ratioRow || !is_numeric($ratioRow->ratio) || !is_finite((float)$ratioRow->ratio) || (float)$ratioRow->ratio <= 0) {
                throw ValidationException::withMessages(['barcode' => "Ratio barcode {$itemCode} / {$uom} tidak tersedia pada Master Ratio."]);
            }
            $ratio = (float) $ratioRow->ratio;
        }
        $token = json_decode(Crypt::decryptString($data['token']), true, 512, JSON_THROW_ON_ERROR);
        $token['input_ratio'] = $ratio;
        $token['input_uom'] = $uom;
        $token['base_uom'] = $smallest;
        $data['token'] = Crypt::encryptString(json_encode($token, JSON_THROW_ON_ERROR));
        $data['ratio'] = $ratio;
        $data['smallest_uom_code'] = $smallest;
        $data['initial_qty'] = ($data['qr_qty'] !== null && is_numeric($data['qr_qty'])) ? (float)$data['qr_qty'] : 1;
        return $data;
    }

    public function confirm(User $user, SampleCycle $cycle, string $token, string $result, mixed $physicalQty = null): SampleCheck
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['token' => 'Token scan tidak valid. Scan ulang barcode.']);
        }
        $ratio = $payload['input_ratio'] ?? null;
        if (!is_numeric($ratio) || !is_finite((float)$ratio) || (float)$ratio <= 0) {
            throw ValidationException::withMessages(['token' => 'Ratio scan tidak valid. Scan ulang barcode.']);
        }
        if ($physicalQty === null || $physicalQty === '' || !is_numeric($physicalQty) || !is_finite((float)$physicalQty) || (float)$physicalQty < 0) {
            throw ValidationException::withMessages(['physical_qty' => 'Masukkan qty angka 0 atau lebih.']);
        }
        $converted = round((float)$physicalQty * (float)$ratio, 4);
        if (!is_finite($converted) || $converted > 1000000000000) {
            throw ValidationException::withMessages(['physical_qty' => 'Qty terlalu besar.']);
        }
        $systemQty = round((float) ($payload['system_qty'] ?? 0), 4);
        $status = abs($converted - $systemQty) <= 0.00005 ? SampleCheck::RESULT_MATCH : SampleCheck::RESULT_MISMATCH;
        // Jangan percaya MATCH/MISMATCH dari browser; hasil berasal dari qty server.
        return parent::confirm($user, $cycle, $token, $status, $converted);
    }
}
