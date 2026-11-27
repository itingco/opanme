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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/** Shared read-only barcode preview and atomic, quantity-aware scan recording. */
class CycleBarcodeScanService
{
    public function __construct(
        private readonly ErpCatalogService $catalog,
        private readonly CombinedCycleCatalogService $combinedCatalog,
        private readonly CombinedCycleSnapshotService $combinedSnapshots,
    ) {}

    public function preview(User $checker, ScanSession $session, string $barcode, bool $manual = false): array
    {
        $cycle = StockOpnameCycle::query()->findOrFail($session->cycle_id);
        $this->assertSession($checker, $session, $cycle);
        $result = $this->resolve($session, $cycle, $barcode, false, $manual);
        // Never expose ERP stock, variance, internal ERP IDs or discovered IDs to checkers.
        unset($result['item_id'], $result['discovered_id'], $result['uom_level'], $result['master_fallback']);
        return $result;
    }

    public function scan(User $checker, ScanSession $session, string $barcode, mixed $qty = null, ?string $photo = null, bool $manual = false): array
    {
        // Decode before taking database locks; evidence is optional for ERP barcode scans.
        $evidence = $photo === null || $photo === '' ? null : $this->decodePhoto($photo);
        $path = null;
        if ($manual && $evidence === null) $this->fail('photo', 'Foto barang wajib untuk input kode manual.');
        try {
            return DB::transaction(function () use ($checker, $session, $barcode, $qty, $evidence, $manual, &$path) {
                $cycle = StockOpnameCycle::query()->lockForUpdate()->findOrFail($session->cycle_id);
                $session = ScanSession::query()->lockForUpdate()->findOrFail($session->id);
                $this->assertSession($checker, $session, $cycle);
                $info = $this->resolve($session, $cycle, $barcode, true, $manual);
                if (! $info['ok']) {
                    if ($evidence !== null) $this->fail('photo', 'Foto barcode hanya dapat disimpan setelah barang ERP dikenali.');
                    return $info;
                }
                $input = $this->quantity($qty ?? $info['qty']);
                $physical = round($input * (float) $info['ratio'], 4);
                if (! is_finite($physical) || $physical < 0.0001 || $physical > 999999999999) {
                    $this->fail('qty', 'Qty terkecil di luar batas yang diperbolehkan.');
                }
                $common = [
                    'cycle_id' => $cycle->id,
                    'scan_session_id' => $session->id,
                    'warehouse_id' => $session->warehouse_id,
                    'checker_id' => $checker->id,
                    'location' => $session->location,
                    'ratio_used' => $info['ratio'],
                    'physical_qty' => number_format($physical, 4, '.', ''),
                    'scanned_at' => now(),
                ];
                if (isset($info['discovered_id'])) {
                    if ($evidence !== null) $this->fail('photo', 'Foto untuk barang Non-System belum didukung.');
                    $scan = DiscoveredScanTransaction::create($common + [
                        'discovered_item_id' => $info['discovered_id'],
                        'input_qty' => $input,
                        'input_uom_code' => $info['uom_code'],
                        'smallest_uom_code' => $info['smallest_uom_code'],
                    ]);
                } else {
                    $scan = ScanTransaction::create($common + [
                        'alias_code' => $manual ? 'MANUAL-PHOTO:'.trim($barcode) : trim($barcode),
                        'item_id' => $info['item_id'],
                        'item_code' => $info['item_code'],
                        'item_name' => $info['item_name'],
                        'uom_level' => $info['uom_level'],
                        'uom_code' => $info['uom_code'],
                    ]);
                    if ($evidence !== null) {
                        $path = 'opname/manual-photos/'.$cycle->id.'/'.$scan->id.'-'.bin2hex(random_bytes(8)).'.'.$evidence['ext'];
                        if (! Storage::disk('local')->put($path, $evidence['bytes'])) throw new RuntimeException('Foto bukti gagal disimpan.');
                        DB::table('opname_manual_scan_photos')->insert([
                            'scan_transaction_id' => $scan->id, 'cycle_id' => $cycle->id,
                            'warehouse_id' => $session->warehouse_id, 'checker_id' => $checker->id,
                            'photo_path' => $path, 'created_at' => now(),
                        ]);
                    }
                }
                $response = [
                    'ok' => true, 'barcode' => trim($barcode), 'item_code' => $info['item_code'],
                    'item_name' => $info['item_name'], 'uom_code' => $info['uom_code'],
                    'qty' => number_format($input, 4, '.', ''),
                    'physical_qty' => number_format($physical, 4, '.', ''),
                    'scanned_at' => $scan->scanned_at?->format('H:i:s'),
                    'photo_saved' => $evidence !== null,
                ];
                if ($qty === null && !isset($info['discovered_id']) && $info['kind'] === 'BARCODE MASTER') {
                    unset($response['qty'], $response['physical_qty']);
                }
                if (isset($info['discovered_id'])) $response += ['non_system' => true, 'auto_non_system' => true, 'smallest_uom_code' => $info['smallest_uom_code']];
                if ($info['kind'] === 'QR KODE~QTY') $response['qr_payload'] = true;
                return $response;
            });
        } catch (\Throwable $e) {
            if ($path !== null) Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    private function resolve(ScanSession $session, StockOpnameCycle $cycle, string $barcode, bool $persist, bool $manual = false): array
    {
        $barcode = trim($barcode);
        if ($barcode === '' || preg_match('~^https?://~i', $barcode)) $this->fail('barcode', 'Barcode kosong atau QR website tidak dapat diproses.');
        $mapping = $manual ? null : ItemBarcode::query()->where('barcode', $barcode)->first();
        $qr = $manual ? ['item_code' => $barcode, 'qty' => 1] : null;
        if (! $mapping && ! $manual) {
            try { $qr = ScanCode::parseQrPayload($barcode); }
            catch (InvalidArgumentException $e) { $this->fail('barcode', $e->getMessage()); }
        }
        if (! $mapping && $qr === null) {
            $known = StockOpnameDiscoveredItem::query()->where('cycle_id', $cycle->id)->where('alias_code', $barcode)->first();
            if (! $known) return [
                'ok' => false, 'requires_non_system' => true, 'known_non_system' => false,
                'barcode' => $barcode, 'item_name' => null, 'uom_code' => 'PCS',
                'smallest_uom_code' => 'PCS', 'ratio_to_smallest' => '1.0000',
            ];
            $uom = strtoupper(trim((string) $known->default_uom_code));
            $smallest = strtoupper(trim((string) $known->smallest_uom_code));
            $ratio = $uom === $smallest ? 1.0 : (float) $known->default_ratio_to_smallest;
            $this->assertRatio($ratio);
            return [
                'ok' => true, 'barcode' => $barcode, 'item_code' => $known->alias_code,
                'item_name' => $known->item_name, 'uom_code' => $uom, 'smallest_uom_code' => $smallest,
                'ratio' => $ratio, 'qty' => 1, 'physical_qty' => $ratio,
                'kind' => 'NON-SYSTEM CYCLE', 'source_database' => $cycle->source_database,
                'discovered_id' => $known->id, 'photo_allowed' => false,
            ];
        }

        $code = strtoupper(trim((string) ($mapping ? $mapping->item_code : $qr['item_code'])));
        $uom = $mapping ? strtoupper(trim((string) $mapping->uom_code)) : 'PCS';
        $ratio = 1.0;
        if ($mapping) {
            $row = UomRatio::query()->where('item_code', $code)->where('uom_code', $uom)->first();
            if (! $row || ! is_numeric($row->ratio)) $this->fail('barcode', "Master ratio belum tersedia untuk {$code} / {$uom}.");
            $ratio = (float) $row->ratio;
            $this->assertRatio($ratio);
        }
        $defaultQty = $mapping ? 1 : $this->quantity($qr['qty']);

        // Snapshot + history across this cycle only. Different IDs for one code must not be guessed.
        $item = $this->snapshotItem($cycle, $code);
        $combined = $cycle->source_database === CombinedCycleSnapshotService::MODE;
        $uomLevel = 1;
        $missing = $item === null;
        if ($combined) {
            $combinedItem = DB::table('cycle_combined_items')->where('cycle_id', $cycle->id)->where('item_code', $code)->first();
            if (! $combinedItem) {
                // Existing ensureCodes creates a combined identity and zero-opening rows,
                // with source order/eligibility handled by the existing allocation service.
                if ($persist) {
                    $this->combinedMaster($cycle, $code); // clear validation before materialization
                    $combinedItem = $this->combinedSnapshots->ensureCodes($cycle, [$code])[$code] ?? null;
                    if (! $combinedItem) $this->fail('barcode', "Item {$code} tidak ditemukan pada master database sumber.");
                } else {
                    $master = $this->combinedMaster($cycle, $code);
                    $combinedItem = (object) ['id' => null, 'item_code' => $code, 'item_name' => $master['item_name'], 'uom_code' => $master['uom_code']];
                }
            }
            if ($item !== null && (int) $item->item_id !== (int) $combinedItem->id) $this->fail('barcode', 'Identitas barang gabungan tidak konsisten. Hubungi admin.');
            $item = (object) ['item_id' => $combinedItem->id, 'item_code' => $combinedItem->item_code, 'item_name' => $combinedItem->item_name];
            if (! $mapping) $uom = $combinedItem->uom_code;
        } elseif ($item === null) {
            // Only an ERP master lookup is allowed as fallback; never synthesize an ItemID.
            if ($mapping) {
                $master = $this->catalog->findItemByCodeAndUom($cycle->source_database, $code, $uom);
            } else {
                $masters = $this->combinedCatalog->findItemsByCodes($cycle->source_database, [$code]);
                if (count($masters) > 1) $this->fail('barcode', "Item {$code} ambigu pada master ERP.");
                $master = $masters[0] ?? null;
                if ($master) $uom = $master['uom_code'];
            }
            if (! $master || trim($uom) === '') $this->fail('barcode', "Item {$code} tidak ditemukan pada master ERP cycle ini. Hubungi admin.");
            $item = (object) $master;
            $uomLevel = (int) ($master['uom_level'] ?? 1);
            // For a normal cycle, the assigned warehouse is the physical counting area.
            // No historical snapshot is fabricated: summary already includes scan-only keys with opening 0.
        }
        return [
            'ok' => true, 'barcode' => $barcode, 'item_id' => $item->item_id,
            'item_code' => $item->item_code, 'item_name' => $item->item_name,
            'uom_code' => $uom, 'uom_level' => $uomLevel, 'ratio' => $ratio,
            'qty' => $defaultQty, 'physical_qty' => $defaultQty * $ratio,
            'kind' => $manual ? 'MANUAL ITEM' : ($mapping ? 'BARCODE MASTER' : 'QR KODE~QTY'),
            'source_database' => $cycle->source_database, 'photo_allowed' => true,
            // Not a system balance; indicates that the barcode was resolved via the master fallback.
            'master_fallback' => $missing,
        ];
    }

    private function snapshotItem(StockOpnameCycle $cycle, string $code): ?object
    {
        $matches = collect();
        foreach (['stock_snapshots', 'scan_transactions'] as $table) {
            $rows = DB::table($table)->where('cycle_id', $cycle->id)
                ->whereRaw('UPPER(TRIM(item_code)) = ?', [$code])
                ->selectRaw('item_id, MAX(item_code) AS item_code, MAX(item_name) AS item_name')
                ->groupBy('item_id')->limit(2)->get();
            $matches = $matches->concat($rows);
        }
        $matches = $matches->unique('item_id')->values();
        if ($matches->count() > 1) $this->fail('barcode', "Item {$code} ambigu pada cycle ini. Hubungi admin.");
        return $matches->first();
    }

    private function combinedMaster(StockOpnameCycle $cycle, string $code): array
    {
        $databases = DB::table('cycle_combined_sources')->where('cycle_id', $cycle->id)->orderBy('id')->pluck('source_database')->unique();
        $matches = [];
        foreach ($databases as $database) {
            $items = $this->combinedCatalog->findItemsByCodes($database, [$code]);
            if (count($items) > 1) $this->fail('barcode', "Item {$code} ambigu pada {$database}.");
            if ($items) $matches[] = $items[0];
        }
        if (! $matches) $this->fail('barcode', "Item {$code} tidak ditemukan pada master database sumber.");
        $uoms = array_unique(array_column($matches, 'uom_code'));
        if (count($uoms) !== 1 || trim((string) reset($uoms)) === '') $this->fail('barcode', "Smallest UOM {$code} berbeda atau tidak valid antar database.");
        return $matches[0];
    }

    private function assertSession(User $checker, ScanSession $session, StockOpnameCycle $cycle): void
    {
        abort_unless((int) $session->checker_id === (int) $checker->id, 403);
        if ($session->ended_at !== null || $cycle->status !== StockOpnameCycle::STATUS_OPEN) {
            $this->fail('barcode', 'Sesi checker tidak aktif atau cycle sudah ditutup.');
        }
        if (! CheckerAssignment::query()->where('cycle_id', $cycle->id)->where('warehouse_id', $session->warehouse_id)->where('checker_id', $checker->id)->exists()) {
            abort(403, 'Anda tidak ditugaskan pada warehouse ini.');
        }
    }

    private function quantity(mixed $value): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0.0001 || (float) $value > 999999999 || abs((float) $value - round((float) $value, 4)) > 0.00000001) {
            $this->fail('qty', 'Qty harus positif, maksimal 999999999 dan maksimal 4 angka desimal.');
        }
        return round((float) $value, 4);
    }

    private function assertRatio(float $ratio): void
    {
        if (! is_finite($ratio) || $ratio <= 0) $this->fail('barcode', 'Ratio UOM tidak valid. Hubungi admin.');
    }

    private function decodePhoto(string $dataUri): array
    {
        if (! preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#D', $dataUri, $m)) $this->fail('photo', 'Format foto tidak valid.');
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) < 1000 || strlen($bytes) > 6*1024*1024) $this->fail('photo', 'Ukuran foto tidak valid (maksimal 6 MB).');
        $info = @getimagesizefromstring($bytes);
        $types = ['jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP];
        if (! $info || $info[2] !== $types[$m[1]] || $info[0] < 200 || $info[1] < 200) $this->fail('photo', 'Foto tidak valid atau resolusi terlalu kecil.');
        return ['bytes' => $bytes, 'ext' => $m[1] === 'jpeg' ? 'jpg' : $m[1]];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
