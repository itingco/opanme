<?php

namespace App\Services;

use App\Models\CheckerAssignment;
use App\Models\ScanSession;
use App\Models\ScanTransaction;
use App\Models\StockOpnameCycle;
use App\Models\UomRatio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class ScanService
{
    public function __construct(private readonly ErpCatalogService $catalog)
    {
    }

    public function scan(User $checker, ScanSession $session, string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Barcode wajib diisi.']);
        }

        $session->loadMissing(['cycle', 'warehouse']);
        if ((int) $session->checker_id !== (int) $checker->id || $session->ended_at) {
            throw ValidationException::withMessages(['barcode' => 'Sesi scan tidak aktif.']);
        }

        if ($session->cycle->status !== StockOpnameCycle::STATUS_OPEN) {
            throw ValidationException::withMessages(['barcode' => 'Cycle stock opname sudah tidak terbuka.']);
        }

        $assigned = CheckerAssignment::query()
            ->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)
            ->where('checker_id', $checker->id)
            ->exists();

        if (! $assigned) {
            throw ValidationException::withMessages(['barcode' => 'Anda tidak ditugaskan pada warehouse ini.']);
        }

        try {
            $item = $this->catalog->findBarcode($session->cycle->source_database, $barcode);
        } catch (UnexpectedValueException $e) {
            throw ValidationException::withMessages(['barcode' => $e->getMessage()]);
        }

        if (! $item) {
            throw ValidationException::withMessages(['barcode' => 'Barcode tidak ditemukan pada ERP.']);
        }

        $ratio = UomRatio::query()
            ->where('source_database', $session->cycle->source_database)
            ->where('item_id', $item['item_id'])
            ->where('uom_level', $item['uom_level'])
            ->first();

        if (! $ratio) {
            throw ValidationException::withMessages([
                'barcode' => "Master ratio belum tersedia untuk {$item['item_code']} / {$item['uom_code']}.",
            ]);
        }

        $transaction = DB::transaction(function () use ($checker, $session, $item, $ratio): ScanTransaction {
            $cycle = StockOpnameCycle::query()->sharedLock()->findOrFail($session->cycle_id);
            if ($cycle->status !== StockOpnameCycle::STATUS_OPEN) {
                throw ValidationException::withMessages(['barcode' => 'Cycle stock opname sudah ditutup.']);
            }

            return ScanTransaction::create([
                'cycle_id' => $session->cycle_id,
                'scan_session_id' => $session->id,
                'warehouse_id' => $session->warehouse_id,
                'checker_id' => $checker->id,
                'location' => $session->location,
                'alias_code' => $item['alias_code'],
                'item_id' => $item['item_id'],
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'],
                'uom_level' => $item['uom_level'],
                'uom_code' => $item['uom_code'],
                'ratio_used' => $ratio->ratio,
                'physical_qty' => $ratio->ratio,
                'scanned_at' => now(),
            ]);
        });

        return [
            'ok' => true,
            'item_code' => $transaction->item_code,
            'item_name' => $transaction->item_name,
            'uom_code' => $transaction->uom_code,
            'scanned_at' => $transaction->scanned_at?->format('H:i:s'),
        ];
    }
}
