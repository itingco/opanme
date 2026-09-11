<?php

namespace App\Services;

use App\Models\ItemBarcode;
use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnexpectedValueException;

class SampleCheckingService
{
    public function __construct(
        private readonly ErpCatalogService $catalog,
        private readonly ErpStockService $stock
    ) {
    }

    public function lookup(User $user, SampleCycle $cycle, string $barcode): array
    {
        $this->assertUsableCycle($user, $cycle);
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Barcode wajib diisi.']);
        }

        $mapping = ItemBarcode::query()->where('barcode', $barcode)->first();
        if (! $mapping) {
            throw ValidationException::withMessages(['barcode' => 'Barcode tidak ditemukan di DB_AppHub.']);
        }

        try {
            $item = $this->catalog->findItemByCodeAndUom(
                $cycle->source_database,
                (string) $mapping->item_code,
                (string) $mapping->uom_code
            );
        } catch (UnexpectedValueException $e) {
            throw ValidationException::withMessages(['barcode' => $e->getMessage()]);
        }

        if (! $item) {
            throw ValidationException::withMessages([
                'barcode' => "Barcode {$barcode} mengarah ke {$mapping->item_code} / {$mapping->uom_code}, tetapi item/UOM tidak ditemukan di ERP {$cycle->source_database}.",
            ]);
        }

        if (SampleCheck::query()->where('sample_cycle_id', $cycle->id)->where('item_id', $item['item_id'])->exists()) {
            throw ValidationException::withMessages([
                'barcode' => "{$item['item_code']} sudah pernah disampling pada cycle ini. Ambil barang lain atau buat cycle baru.",
            ]);
        }

        try {
            $stockRow = $this->stock->findItemInWarehouse(
                $cycle->source_database,
                now(),
                (int) $cycle->erp_warehouse_id,
                (int) $item['item_id']
            );

            $transitRows = $this->stock->findItemInTransitWarehouses(
                $cycle->source_database,
                now(),
                (int) $item['item_id']
            );
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['barcode' => 'Gagal membaca stok ERP: '.$e->getMessage()]);
        }

        $systemQty = (string) ($stockRow['smallest_on_hand'] ?? '0');
        $payload = [
            'v' => 1,
            'user_id' => (int) $user->id,
            'cycle_id' => (int) $cycle->id,
            'source_database' => $cycle->source_database,
            'erp_warehouse_id' => (int) $cycle->erp_warehouse_id,
            'barcode' => $barcode,
            'item_id' => (int) $item['item_id'],
            'item_code' => (string) $item['item_code'],
            'item_name' => (string) $item['item_name'],
            'uom_code' => (string) $item['uom_code'],
            'system_qty' => $systemQty,
            'expires_at' => now()->addMinutes(3)->timestamp,
        ];

        return [
            'ok' => true,
            'token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'barcode' => $barcode,
            'item_id' => $payload['item_id'],
            'item_code' => $payload['item_code'],
            'item_name' => $payload['item_name'],
            'uom_code' => $payload['uom_code'],
            'system_qty' => number_format((float) $systemQty, 4, '.', ''),
            'warehouse_code' => $cycle->warehouse_code,
            'warehouse_name' => $cycle->warehouse_name,
            'location' => $cycle->location,
            'transit_stock' => array_map(static fn (array $row): array => [
                'warehouse_id' => (int) $row['warehouse_id'],
                'warehouse_code' => (string) $row['warehouse_code'],
                'warehouse_name' => (string) $row['warehouse_name'],
                'smallest_on_hand' => number_format((float) $row['smallest_on_hand'], 4, '.', ''),
            ], $transitRows),
        ];
    }

    public function confirm(User $user, SampleCycle $cycle, string $token, string $result, mixed $physicalQty = null): SampleCheck
    {
        $this->assertUsableCycle($user, $cycle);
        $result = strtoupper(trim($result));
        if (! in_array($result, [SampleCheck::RESULT_MATCH, SampleCheck::RESULT_MISMATCH], true)) {
            throw ValidationException::withMessages(['result' => 'Hasil sampling tidak valid.']);
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages(['token' => 'Data scan sudah tidak valid. Silakan scan ulang barcode.']);
        }

        if (
            (int) ($payload['user_id'] ?? 0) !== (int) $user->id
            || (int) ($payload['cycle_id'] ?? 0) !== (int) $cycle->id
            || (int) ($payload['erp_warehouse_id'] ?? 0) !== (int) $cycle->erp_warehouse_id
            || (string) ($payload['source_database'] ?? '') !== (string) $cycle->source_database
        ) {
            throw ValidationException::withMessages(['token' => 'Data scan bukan milik user/cycle/gudang ini. Silakan scan ulang.']);
        }
        if ((int) ($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['token' => 'Data stok hasil scan sudah kedaluwarsa. Silakan scan ulang barcode.']);
        }

        $systemQty = round((float) ($payload['system_qty'] ?? 0), 4);
        if ($result === SampleCheck::RESULT_MATCH) {
            $physical = $systemQty;
        } else {
            if ($physicalQty === null || $physicalQty === '' || ! is_numeric($physicalQty) || (float) $physicalQty < 0) {
                throw ValidationException::withMessages(['physical_qty' => 'Qty fisik wajib diisi untuk stok tidak cocok.']);
            }
            $physical = round((float) $physicalQty, 4);
        }

        return DB::transaction(function () use ($user, $cycle, $payload, $result, $systemQty, $physical): SampleCheck {
            $locked = SampleCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $this->assertUsableCycle($user, $locked);

            if (SampleCheck::query()->where('sample_cycle_id', $locked->id)->where('item_id', (int) $payload['item_id'])->exists()) {
                throw ValidationException::withMessages(['barcode' => 'Item ini sudah tersimpan pada sample cycle yang sama.']);
            }

            return SampleCheck::create([
                'sample_cycle_id' => $locked->id,
                'user_id' => $user->id,
                'source_database' => $locked->source_database,
                'erp_warehouse_id' => $locked->erp_warehouse_id,
                'warehouse_code' => $locked->warehouse_code,
                'warehouse_name' => $locked->warehouse_name,
                'location' => $locked->location,
                'barcode' => (string) $payload['barcode'],
                'item_id' => (int) $payload['item_id'],
                'item_code' => (string) $payload['item_code'],
                'item_name' => (string) $payload['item_name'],
                'uom_code' => (string) $payload['uom_code'],
                'system_qty' => $systemQty,
                'physical_qty' => $physical,
                'result' => $result,
                'scanned_at' => now(),
            ]);
        });
    }

    private function assertUsableCycle(User $user, SampleCycle $cycle): void
    {
        if (! $user->isGerai()) {
            abort(403);
        }
        if ((int) $cycle->created_by !== (int) $user->id) {
            abort(403, 'Sample cycle bukan milik user ini.');
        }
        if (! $cycle->isOpen()) {
            throw ValidationException::withMessages(['cycle' => 'Sample cycle sudah ditutup.']);
        }
        if ($user->hasGeraiWarehouseBinding() && (
            (int) $user->erp_warehouse_id !== (int) $cycle->erp_warehouse_id
            || (string) $user->source_database !== (string) $cycle->source_database
        )) {
            abort(403, 'User Gerai hanya boleh sampling pada gudang yang sudah ditentukan.');
        }
    }
}
