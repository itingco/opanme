<?php
/**
 * Patch Checker Cycle Legacy v4.
 * Jalankan: php APPLY_PATCH.php dari root project Laravel.
 * Transaction-like install: validate all matching anchors before writing any file.
 */

declare(strict_types=1);

$root = __DIR__;
if (!is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: File ini harus berada di root project Laravel (sejajar artisan).\n");
    exit(1);
}

$replacements = [
    'app/Services/ScanService.php' => [
        [
            <<<'OLD'
            return [
                'ok' => false,
                'requires_non_system' => true,
                'known_non_system' => $discovered !== null,
OLD,
            <<<'NEW'
            // Barang Non-System yang sudah ditemukan dalam cycle ini diproses
            // otomatis sebagai scan tambahan (1 UOM per scan). Tidak membuka form lagi.
            if ($discovered !== null) {
                return $this->scanKnownNonSystem($checker, $session, $discovered);
            }

            return [
                'ok' => false,
                'requires_non_system' => true,
                'known_non_system' => false,
NEW,
        ],
        [
            <<<'OLD'
    public function storeNonSystemScan(User $checker, ScanSession $session, array $data): array
OLD,
            <<<'NEW'
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
NEW,
        ],
        [
            <<<'OLD'
            'item_code' => 'NON-SYSTEM',
OLD,
            <<<'NEW'
            // Tampilkan barcode temuan sebagai kode barang lokal (khusus cycle ini).
            'item_code' => $discovered->alias_code,
NEW,
        ],
    ],
    'public/assets/js/app.js' => [
        [
            <<<'OLD'
    let lastAcceptedAt = 0;
    let controls = null;
OLD,
            <<<'NEW'
    let lastAcceptedAt = 0;
    let controls = null;
    // Feedback sukses hanya sesaat; lalu kembali ke kondisi siap scan.
    let successFeedbackTimer = null;

    function showBriefSuccess(title, message) {
        if (successFeedbackTimer !== null) clearTimeout(successFeedbackTimer);
        feedback('success', title, message);
        successFeedbackTimer = setTimeout(() => {
            feedback('idle', 'Siap scan', 'Arahkan kamera ke barcode barang berikutnya.');
            successFeedbackTimer = null;
        }, 1100);
    }
OLD,
        ],
        [
            <<<'OLD'
        feedback('idle','Memproses...', barcode);
OLD,
            <<<'NEW'
        if (successFeedbackTimer !== null) {
            clearTimeout(successFeedbackTimer);
            successFeedbackTimer = null;
        }
        feedback('idle','Memproses...', barcode);
NEW,
        ],
        [
            <<<'OLD'
            feedback('success', `${d.item_code} · ${d.uom_code}`, d.item_name || 'Scan berhasil');
OLD,
            <<<'NEW'
            showBriefSuccess(`${d.item_code} · ${d.uom_code}`, d.item_name || 'Scan berhasil');
NEW,
        ],
        [
            <<<'OLD'
            feedback('success', `NON-SYSTEM · ${d.uom_code}`, `${d.item_name} · Qty ${d.qty}`);
OLD,
            <<<'NEW'
            showBriefSuccess(`${d.item_code || d.barcode} · ${d.uom_code}`, `${d.item_name} · Qty ${d.qty}`);
NEW,
        ],
        [
            <<<'OLD'
        if (barcode === lastCode && now - lastAcceptedAt < 1200) return;
OLD,
            <<<'NEW'
        // Hindari pengulangan karena kamera masih melihat barcode yang sama.
        if (barcode === lastCode && now - lastAcceptedAt < 2500) return;
NEW,
        ],
    ],
];

function replaceOnce(string $content, string $old, string $new, string $path): string
{
    $count = substr_count($content, $old);
    if ($count !== 1) {
        throw new RuntimeException("Tidak diterapkan: {$path}, anchor ditemukan {$count} kali (harus tepat 1). Versi file mungkin berbeda.");
    }
    return str_replace($old, $new, $content);
}

$updated = [];
try {
    foreach ($replacements as $relative => $pairs) {
        $target = $root.'/'.$relative;
        if (! is_file($target)) {
            throw new RuntimeException("File tidak ditemukan: {$relative}");
        }
        $original = file_get_contents($target);
        if ($original === false) {
            throw new RuntimeException("Gagal membaca: {$relative}");
        }
        $content = $original;
        foreach ($pairs as [$before, $after]) {
            $content = replaceOnce($content, $before, $after, $relative);
        }
        $updated[$relative] = [$original, $content];
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: ".$e->getMessage()."\nTidak ada file yang diubah.\n");
    exit(1);
}

$stamp = date('Ymd_His');
foreach ($updated as $relative => [$original, $content]) {
    $target = $root.'/'.$relative;
    $backup = $target.'.backup_legacy_v4_'.$stamp;
    if (file_put_contents($backup, $original) === false) {
        fwrite(STDERR, "ERROR membuat backup: {$backup}\n");
        exit(1);
    }
    if (file_put_contents($target, $content) === false) {
        file_put_contents($target, $original);
        fwrite(STDERR, "ERROR menulis {$relative}, konten asli dipulihkan.\n");
        exit(1);
    }
    echo "OK: {$relative}\nBackup: {$backup}\n";
}
echo "Patch selesai. Jalankan: php artisan optimize:clear\n";
