<?php
/**
 * Patch checker cycle legacy V6. Run in Laravel root: php APPLY_FIX_V6.php
 * Only changes ScanService.php and public/assets/js/app.js and layout cache version.
 */
$base = __DIR__;
if (!is_file($base.'/artisan')) { fwrite(STDERR, "Jalankan dari root Laravel (file artisan harus ada).\n"); exit(1); }
$paths = [
 'app/Services/ScanService.php',
 'public/assets/js/app.js',
 'resources/views/layouts/app.blade.php',
];
foreach ($paths as $p) { if (!is_file($base.'/'.$p)) { fwrite(STDERR,"Tidak ditemukan: $p\n"); exit(1); } }
$original = []; $updated = [];
foreach ($paths as $p) { $original[$p] = file_get_contents($base.'/'.$p); $updated[$p] = $original[$p]; }
function patchBetween(string $data, string $start, string $end, string $replace, string $label): string {
    $a = strpos($data, $start);
    if ($a === false) throw new RuntimeException("Marker awal tidak ditemukan: $label");
    $b = strpos($data, $end, $a + strlen($start));
    if ($b === false) throw new RuntimeException("Marker akhir tidak ditemukan: $label");
    return substr($data,0,$a).$replace.substr($data,$b);
}
try {
  $p='app/Services/ScanService.php'; $s=$updated[$p];
  // Replace the ERP per-barcode lookup with cycle snapshot lookup.
  $a = strpos($s,"        try {\n            \$item = \$this->catalog->findItemByCodeAndUom(");
  $b = $a === false ? false : strpos($s,"        \$ratio = UomRatio::query()",$a);
  if($a === false || $b === false) throw new RuntimeException('Struktur ScanService scan() tidak cocok; tidak menimpa file.');
  $s = substr($s,0,$a).<<<'CODE'
        // Data barang berasal dari snapshot yang sudah tersimpan untuk cycle ini.
        // Tidak ada query ulang ke AS_INGCO / AS_SMI pada setiap scan barcode.
        $item = $this->resolveCycleSnapshotItem($session, (string) $mapping->item_code, (string) $mapping->uom_code);

CODE
  .substr($s,$b);
  // Remove ERP lookup from QR scan path, retaining qty parsed by ScanCode.
  $qrStart = strpos($s,'    private function scanQrPayload(');
  if($qrStart === false) throw new RuntimeException('Method scanQrPayload tidak ditemukan.');
  $a = strpos($s,"        try {\n            \$item = \$this->catalog->findItemByCode(",$qrStart);
  $b = $a === false ? false : strpos($s,"        \$qty = (float) \$qr['qty'];",$a);
  if($a === false || $b === false) throw new RuntimeException('Struktur QR scan tidak cocok.');
  $s = substr($s,0,$a).<<<'CODE'
        // QR KODE~QTY adalah qty dalam smallest UOM; ambil identitas barang
        // dari snapshot cycle, bukan koneksi langsung ke ERP.
        $item = $this->resolveCycleSnapshotItem($session, (string) $qr['item_code'], null);

CODE
  .substr($s,$b);
  $helper=<<<'CODE'
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

CODE;
  $marker='    private function assertActiveAssignedSession(';
  if(strpos($s,'private function resolveCycleSnapshotItem(') === false) {
    if(strpos($s,$marker)===false) throw new RuntimeException('Marker helper session tidak ditemukan.');
    $s=str_replace($marker,$helper.$marker,$s);
  }
  $updated[$p]=$s;

  $p='public/assets/js/app.js'; $s=$updated[$p];
  // A prior partial patch accidentally inserted PHP nowdoc patch definitions into JS.
  $badStart = strpos($s,"OLD,\n        ],\n        [\n            <<<'OLD'");
  if ($badStart !== false) {
      $badEnd = strpos($s,'    function updateConversion()', $badStart);
      if($badEnd === false) throw new RuntimeException('Tidak bisa memperbaiki blok JavaScript rusak.');
      $s=substr($s,0,$badStart).substr($s,$badEnd);
  }
  $start="    startBtn?.addEventListener('click', async () => {";
  $end="    window.addEventListener('beforeunload', () => controls?.stop());";
  $camera=<<<'JS'
    // Camera initialization with explicit diagnostics and recovery.
    startBtn?.addEventListener('click', async () => {
        if (!startBtn || startBtn.disabled) return;
        if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(location.hostname)) {
            feedback('error', 'Kamera diblokir browser', 'Buka aplikasi menggunakan HTTPS. Akses kamera dari HTTP di HP biasanya ditolak.');
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia) {
            feedback('error', 'Kamera tidak tersedia', 'Browser tidak mendukung akses kamera atau izin kamera diblokir. Gunakan Chrome/Safari terbaru.');
            return;
        }
        if (typeof BrowserMultiFormatReader !== 'function') {
            feedback('error', 'Library scanner gagal dimuat', 'Pastikan assets/vendor/zxing-browser.min.js tersedia dan refresh halaman (Ctrl+F5).');
            return;
        }
        startBtn.disabled = true;
        startBtn.textContent = 'Menghubungkan kamera...';
        feedback('idle', 'Meminta izin kamera', 'Izinkan akses kamera ketika browser meminta izin.');
        try {
            const reader = new BrowserMultiFormatReader(undefined, { delayBetweenScanAttempts: 150 });
            // Prefer back camera but do not force 1280x720; some phones reject it.
            try {
                controls = await reader.decodeFromConstraints(
                    { audio: false, video: { facingMode: { ideal: 'environment' } } },
                    video,
                    (result) => { if (result) submitBarcode(result.getText()); }
                );
            } catch (firstError) {
                // Fallback for devices that cannot fulfill facingMode constraints.
                controls = await reader.decodeFromConstraints(
                    { audio: false, video: true },
                    video,
                    (result) => { if (result) submitBarcode(result.getText()); }
                );
            }
            startBtn.style.display = 'none';
            feedback('idle', 'Kamera aktif', 'Arahkan barcode ke area scan.');
        } catch (error) {
            const name = error?.name || '';
            let detail = error?.message || String(error);
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') detail = 'Izin kamera ditolak. Buka pengaturan situs pada browser lalu izinkan Kamera.';
            else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') detail = 'Perangkat kamera tidak ditemukan.';
            else if (name === 'NotReadableError' || name === 'TrackStartError') detail = 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi kamera lain lalu coba lagi.';
            feedback('error', 'Gagal membuka kamera', detail);
            startBtn.disabled = false;
            startBtn.textContent = 'Coba Kamera Lagi';
            startBtn.style.display = '';
            console.error('Legacy checker camera:', error);
        }
    });

JS;
  $s=patchBetween($s,$start,$end,$camera,'camera click handler');
  $updated[$p]=$s;
  // Cache-bust JS on mobile; retain other layout content.
  $p='resources/views/layouts/app.blade.php'; $s=$updated[$p];
  if(strpos($s,"asset('assets/js/app.js')") === false) throw new RuntimeException('Script layout tidak ditemukan.');
  $s=preg_replace("~(asset\('assets/js/app\\.js'\) }}\\?v=)[^\"']+~",'${1}20261116v6',$s,1);
  $updated[$p]=$s;

  $changed=[];
  foreach($paths as $p){if($updated[$p]!==$original[$p])$changed[]=$p;}
  if(!$changed) { echo "Tidak ada perubahan yang diperlukan.\n"; exit(0); }
  foreach($changed as $p){$backup=$base.'/'.$p.'.bak-v6-'.date('Ymd-His'); if(!copy($base.'/'.$p,$backup)) throw new RuntimeException('Gagal backup '.$p); echo "Backup: $backup\n";}
  foreach($changed as $p){if(file_put_contents($base.'/'.$p,$updated[$p])===false) throw new RuntimeException('Gagal tulis '.$p);echo "Updated: $p\n";}
  echo "Patch V6 berhasil. Jalankan php artisan optimize:clear lalu refresh browser.\n";
} catch(Throwable $e) {fwrite(STDERR,"PATCH DIBATALKAN: ".$e->getMessage()."\n");exit(1);}
