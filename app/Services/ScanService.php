<?php

namespace App\Services;

use App\Models\CheckerAssignment;
use App\Models\DiscoveredScanTransaction;
use App\Models\ItemBarcode;
use App\Models\ScanSession;
use App\Models\ScanTransaction;
use App\Models\StockOpnameCycle;
use App\Models\StockOpnameDiscoveredItem;
use App\Models\UomRatio;
use App\Models\User;
use App\Support\ScanCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use UnexpectedValueException;

class ScanService
{
    public function __construct(
        private readonly ErpCatalogService $catalog,
        private readonly NonSystemQtyService $nonSystemQty
    ) {
    }

    public function scan(User $checker, ScanSession $session, string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Barcode wajib diisi.']);
        }

        $this->assertActiveAssignedSession($checker, $session);

        // Exact master barcode selalu diprioritaskan. Jika tidak ada, format
        // KODEBARANG~QTY diproses sebagai label qty sederhana.
        $mapping = ItemBarcode::query()
            ->where('barcode', $barcode)
            ->first();

        if (! $mapping) {
            try {
                $qr = ScanCode::parseQrPayload($barcode);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['barcode' => $e->getMessage()]);
            }
            if ($qr !== null) {
                return $this->scanQrPayload($checker, $session, $barcode, $qr);
            }
        }

        if (! $mapping) {
            $discovered = StockOpnameDiscoveredItem::query()
                ->where('cycle_id', $session->cycle_id)
                ->where('alias_code', $barcode)
                ->first();

            // Barang Non-System yang sudah ditemukan dalam cycle ini diproses
            // otomatis sebagai scan tambahan (1 UOM per scan). Tidak membuka form lagi.
            if ($discovered !== null) {
                return $this->scanKnownNonSystem($checker, $session, $discovered);
            }

            return [
                'ok' => false,
                'requires_non_system' => true,
                'known_non_system' => false,
                'barcode' => $barcode,
                'item_name' => $discovered?->item_name,
                'uom_code' => $discovered?->default_uom_code ?? 'PCS',
                'smallest_uom_code' => $discovered?->smallest_uom_code ?? 'PCS',
                'ratio_to_smallest' => $discovered
                    ? number_format((float) $discovered->default_ratio_to_smallest, 4, '.', '')
                    : '1.0000',
            ];
        }

        // Data barang berasal dari snapshot yang sudah tersimpan untuk cycle ini.
        // Tidak ada query ulang ke AS_INGCO / AS_SMI pada setiap scan barcode.
        $item = $this->resolveCycleSnapshotItem($session, (string) $mapping->item_code, (string) $mapping->uom_code);
        $ratio = UomRatio::query()
            ->where('item_code', strtoupper(trim((string) $mapping->item_code)))
            ->where('uom_code', strtoupper(trim((string) $mapping->uom_code)))
            ->first();

        if (! $ratio) {
            throw ValidationException::withMessages([
                'barcode' => "Master ratio belum tersedia untuk {$mapping->item_code} / {$mapping->uom_code}.",
            ]);
        }

        $transaction = DB::transaction(function () use ($checker, $session, $barcode, $item, $ratio): ScanTransaction {
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
                'alias_code' => $barcode,
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

    /**
     * Scan ulang barcode Non-System yang sudah terdaftar untuk cycle aktif.
     * Tiap scan menambah 1 default UOM; qty dalam smallest UOM memakai ratio tersimpan.
     * Tidak mengambil data barang yang ditemukan di cycle lain.
     */
    private function scanKnownNonSystem(
        User $checker,
        ScanSession $session,
        StockOpnameDiscoveredItem $known
    ): array {
        return DB::transaction(function () use ($checker, $session, $known): array {
            $cycle = StockOpnameCycle::query()->sharedLock()->findOrFail($session->cycle_id);
            if ($cycle->status !== StockOpnameCycle::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'barcode' => 'Cycle stock opname sudah ditutup.',
                ]);
            }

            // Periksa ulang di dalam transaksi agar tidak ada kebocoran antar cycle.
            $discovered = StockOpnameDiscoveredItem::query()
                ->where('cycle_id', $cycle->id)
                ->whereKey($known->id)
                ->firstOrFail();

            $uom = $this->uom((string) $discovered->default_uom_code);
            $smallest = $this->uom((string) $discovered->smallest_uom_code);
            $ratio = $uom === $smallest ? 1.0 : (float) $discovered->default_ratio_to_smallest;
            if (! is_finite($ratio) || $ratio <= 0) {
                throw ValidationException::withMessages([
                    'barcode' => 'Ratio Non-System tidak valid. Hubungi admin.',
                ]);
            }

            $physicalQty = $this->nonSystemQty->toSmallest(1, $ratio);
            $scan = DiscoveredScanTransaction::create([
                'cycle_id' => $cycle->id,
                'discovered_item_id' => $discovered->id,
                'scan_session_id' => $session->id,
                'warehouse_id' => $session->warehouse_id,
                'checker_id' => $checker->id,
                'location' => $session->location,
                'input_qty' => 1,
                'input_uom_code' => $uom,
                'smallest_uom_code' => $smallest,
                'ratio_used' => $ratio,
                'physical_qty' => $physicalQty,
                'scanned_at' => now(),
            ]);

            return [
                'ok' => true,
                'non_system' => true,
                'auto_non_system' => true,
                'barcode' => $discovered->alias_code,
                // Alias barcode menjadi kode barang lokal untuk cycle ini.
                'item_code' => $discovered->alias_code,
                'item_name' => $discovered->item_name,
                'uom_code' => $uom,
                'qty' => '1.0000',
                'smallest_uom_code' => $smallest,
                'physical_qty' => $physicalQty,
                'scanned_at' => $scan->scanned_at?->format('H:i:s'),
            ];
        });
    }

    public function storeNonSystemScan(User $checker, ScanSession $session, array $data): array
    {
        $barcode = trim((string) ($data['barcode'] ?? ''));
        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Barcode wajib diisi.']);
        }

        $this->assertActiveAssignedSession($checker, $session);

        try {
            $qr = ScanCode::parseQrPayload($barcode);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['barcode' => $e->getMessage()]);
        }

        if ($qr !== null) {
            throw ValidationException::withMessages([
                'barcode' => 'Nilai dengan tanda "~" adalah label KODEBARANG~QTY dan harus diproses melalui scan normal, bukan Non-System.',
            ]);
        }

        $mapping = ItemBarcode::query()->where('barcode', $barcode)->first();
        if ($mapping) {
            throw ValidationException::withMessages([
                'barcode' => "Barcode ini sudah terdaftar di Master Barcode sebagai {$mapping->item_code} / {$mapping->uom_code}. Tutup form Non-System lalu scan ulang secara normal.",
            ]);
        }

        $uomCode = $this->uom((string) ($data['uom_code'] ?? ''));
        $smallestUomCode = $this->uom((string) ($data['smallest_uom_code'] ?? ''));
        $ratio = $uomCode === $smallestUomCode ? 1.0 : (float) ($data['ratio_to_smallest'] ?? 0);
        $physicalQty = $this->nonSystemQty->toSmallest($data['qty'] ?? 0, $ratio);

        $stored = DB::transaction(function () use (
            $checker,
            $session,
            $barcode,
            $data,
            $uomCode,
            $smallestUomCode,
            $ratio,
            $physicalQty
        ): array {
            $cycle = StockOpnameCycle::query()->lockForUpdate()->findOrFail($session->cycle_id);
            if ($cycle->status !== StockOpnameCycle::STATUS_OPEN) {
                throw ValidationException::withMessages(['barcode' => 'Cycle stock opname sudah ditutup.']);
            }

            $discovered = StockOpnameDiscoveredItem::query()
                ->where('cycle_id', $cycle->id)
                ->where('alias_code', $barcode)
                ->lockForUpdate()
                ->first();

            if (! $discovered) {
                $itemName = trim((string) ($data['item_name'] ?? ''));
                if ($itemName === '') {
                    throw ValidationException::withMessages([
                        'item_name' => 'Nama barang wajib diisi saat barcode Non-System pertama kali ditemukan.',
                    ]);
                }

                $discovered = StockOpnameDiscoveredItem::create([
                    'cycle_id' => $cycle->id,
                    'alias_code' => $barcode,
                    'item_name' => $itemName,
                    'default_uom_code' => $uomCode,
                    'smallest_uom_code' => $smallestUomCode,
                    'default_ratio_to_smallest' => $ratio,
                    'created_by' => $checker->id,
                ]);
            } else {
                $discovered->update([
                    'default_uom_code' => $uomCode,
                    'smallest_uom_code' => $smallestUomCode,
                    'default_ratio_to_smallest' => $ratio,
                ]);
            }

            $scan = DiscoveredScanTransaction::create([
                'cycle_id' => $cycle->id,
                'discovered_item_id' => $discovered->id,
                'scan_session_id' => $session->id,
                'warehouse_id' => $session->warehouse_id,
                'checker_id' => $checker->id,
                'location' => $session->location,
                'input_qty' => $data['qty'],
                'input_uom_code' => $uomCode,
                'smallest_uom_code' => $smallestUomCode,
                'ratio_used' => $ratio,
                'physical_qty' => $physicalQty,
                'scanned_at' => now(),
            ]);

            return [$discovered, $scan];
        });

        /** @var StockOpnameDiscoveredItem $discovered */
        [$discovered, $scan] = $stored;

        return [
            'ok' => true,
            'non_system' => true,
            'barcode' => $discovered->alias_code,
            // Tampilkan barcode temuan sebagai kode barang lokal (khusus cycle ini).
            'item_code' => $discovered->alias_code,
            'item_name' => $discovered->item_name,
            'uom_code' => $scan->input_uom_code,
            'qty' => number_format((float) $scan->input_qty, 4, '.', ''),
            'smallest_uom_code' => $scan->smallest_uom_code,
            'physical_qty' => number_format((float) $scan->physical_qty, 4, '.', ''),
            'scanned_at' => $scan->scanned_at?->format('H:i:s'),
        ];
    }

    /**
     * @param array{raw:string,item_code:string,qty:int} $qr
     */
    private function scanQrPayload(User $checker, ScanSession $session, string $rawValue, array $qr): array
    {
        // QR KODE~QTY adalah qty dalam smallest UOM; ambil identitas barang
        // dari snapshot cycle, bukan koneksi langsung ke ERP.
        $item = $this->resolveCycleSnapshotItem($session, (string) $qr['item_code'], null);
        $qty = (float) $qr['qty'];
        $transaction = DB::transaction(function () use ($checker, $session, $rawValue, $item, $qty): ScanTransaction {
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
                'alias_code' => $rawValue,
                'item_id' => $item['item_id'],
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'],
                'uom_level' => $item['uom_level'],
                'uom_code' => $item['uom_code'],
                'ratio_used' => 1,
                'physical_qty' => $qty,
                'scanned_at' => now(),
            ]);
        });

        return [
            'ok' => true,
            'qr_payload' => true,
            'qty' => number_format($qty, 4, '.', ''),
            'item_code' => $transaction->item_code,
            'item_name' => $transaction->item_name,
            'uom_code' => $transaction->uom_code,
            'scanned_at' => $transaction->scanned_at?->format('H:i:s'),
        ];
    }

    /**
     * Ambil identitas item dari snapshot cycle, atau riwayat scan cycle yang sama.
     * Tidak terhubung ke AS_INGCO / AS_SMI. Ratio diambil dari ItemRatio lokal.
     */
    private function resolveCycleSnapshotItem(ScanSession $session, string $itemCode, ?string $uomCode): array
    {
        $itemCode = strtoupper(trim($itemCode));
        $item = DB::table('stock_snapshots')
            ->where('cycle_id', $session->cycle_id)
            ->whereRaw('UPPER(item_code) = ?', [$itemCode])
            ->orderByRaw('CASE WHEN warehouse_id = ? THEN 0 ELSE 1 END', [$session->warehouse_id])
            ->first(['item_id', 'item_code', 'item_name']);

        if (! $item) {
            $item = DB::table('scan_transactions')
                ->where('cycle_id', $session->cycle_id)
                ->whereRaw('UPPER(item_code) = ?', [$itemCode])
                ->orderByDesc('id')
                ->first(['item_id', 'item_code', 'item_name']);
        }

        if (! $item) {
            throw ValidationException::withMessages([
                'barcode' => "Item {$itemCode} tidak ditemukan pada snapshot cycle ini. Pastikan master/snapshot cycle sudah lengkap.",
            ]);
        }

        return [
            'item_id' => (int) $item->item_id,
            'item_code' => (string) $item->item_code,
            'item_name' => (string) $item->item_name,
            'uom_level' => 1,
            'uom_code' => $uomCode === null ? 'PCS' : strtoupper(trim($uomCode)),
        ];
    }
    private function assertActiveAssignedSession(User $checker, ScanSession $session): void
    {
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
    }

    private function uom(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            throw ValidationException::withMessages(['uom_code' => 'UOM wajib diisi.']);
        }

        return substr($value, 0, 50);
    }
}
