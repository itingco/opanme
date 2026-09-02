<?php

namespace App\Services;

use App\Models\CheckerAssignment;
use App\Models\DiscoveredScanTransaction;
use App\Models\ScanSession;
use App\Models\ScanTransaction;
use App\Models\StockOpnameCycle;
use App\Models\StockOpnameDiscoveredItem;
use App\Models\UomRatio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        try {
            $item = $this->catalog->findBarcode($session->cycle->source_database, $barcode);
        } catch (UnexpectedValueException $e) {
            throw ValidationException::withMessages(['barcode' => $e->getMessage()]);
        }

        if (! $item) {
            $discovered = StockOpnameDiscoveredItem::query()
                ->where('cycle_id', $session->cycle_id)
                ->where('alias_code', $barcode)
                ->first();

            return [
                'ok' => false,
                'requires_non_system' => true,
                'known_non_system' => $discovered !== null,
                'barcode' => $barcode,
                'item_name' => $discovered?->item_name,
                'uom_code' => $discovered?->default_uom_code ?? 'PCS',
                'smallest_uom_code' => $discovered?->smallest_uom_code ?? 'PCS',
                'ratio_to_smallest' => $discovered
                    ? number_format((float) $discovered->default_ratio_to_smallest, 4, '.', '')
                    : '1.0000',
            ];
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

    public function storeNonSystemScan(User $checker, ScanSession $session, array $data): array
    {
        $barcode = trim((string) ($data['barcode'] ?? ''));
        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Barcode wajib diisi.']);
        }

        $this->assertActiveAssignedSession($checker, $session);

        try {
            $erpItem = $this->catalog->findBarcode($session->cycle->source_database, $barcode);
        } catch (UnexpectedValueException $e) {
            throw ValidationException::withMessages(['barcode' => $e->getMessage()]);
        }
        if ($erpItem) {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode ini sudah ditemukan di ERP. Tutup form Non-System lalu scan ulang secara normal.',
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
            'item_code' => 'NON-SYSTEM',
            'item_name' => $discovered->item_name,
            'uom_code' => $scan->input_uom_code,
            'qty' => number_format((float) $scan->input_qty, 4, '.', ''),
            'smallest_uom_code' => $scan->smallest_uom_code,
            'physical_qty' => number_format((float) $scan->physical_qty, 4, '.', ''),
            'scanned_at' => $scan->scanned_at?->format('H:i:s'),
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
