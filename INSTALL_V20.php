<?php
/** Apply to the Laravel root after copying patch folders. Keeps backups. */
declare(strict_types=1);
$root = __DIR__;
if (!is_file($root.'/artisan')) { fwrite(STDERR, "Jalankan dari root Laravel (sejajar artisan).\n"); exit(1); }
function patchFile(string $relative, callable $modify): void {
    global $root;
    $path = $root.'/'.$relative;
    if (!is_file($path)) { throw new RuntimeException('File tidak ditemukan: '.$relative); }
    $old = file_get_contents($path);
    $new = $modify($old);
    if ($new === $old) { echo "Tidak berubah: $relative\n"; return; }
    $backup = $path.'.before-v20';
    if (!file_exists($backup)) copy($path, $backup);
    if (file_put_contents($path, $new) === false) throw new RuntimeException('Tidak bisa menulis '.$relative);
    echo "Diubah: $relative\n";
}
try {
    foreach (['app/Http/Controllers/Checker/CameraBarcodeLookupController.php',
        'app/Http/Controllers/Admin/CycleSamplingItemHistoryController.php',
        'resources/views/admin/cycles/sampling-item-history.blade.php',
        'routes/checker_camera_lookup_v20.php'] as $relative) {
        if (!is_file($root.'/'.$relative)) throw new RuntimeException('File patch belum dicopy: '.$relative);
    }
    patchFile('routes/web.php', function ($s) {
        $include = "require __DIR__.'/checker_camera_lookup_v20.php';";
        return str_contains($s, $include) ? $s : rtrim($s)."\n\n".$include."\n";
    });
    patchFile('resources/views/checker/scan.blade.php', function ($s) {
        if (!str_contains($s, 'id="v16-confirm"')) throw new RuntimeException('V16 belum terpasang pada checker/scan.blade.php');
        if (!str_contains($s, 'data-camera-barcode-lookup-url=')) {
            $needle = 'data-camera-ai-url=';
            if (!str_contains($s, $needle)) throw new RuntimeException('Atribut data-camera-ai-url tidak ditemukan.');
            $line = 'data-camera-barcode-lookup-url="{{ $legacy ? route(\'warehouse.legacy.camera-barcode.lookup\', $session) : route(\'checker.camera-barcode.lookup\', $session) }}"' . "\n     ";
            $s = str_replace($needle, $line.$needle, $s);
        }
        if (!str_contains($s, 'id="v20-item-detail"')) {
            $needle = '<div id="v16-confirm" class="v16-confirm" hidden>';
            $s = str_replace($needle, $needle.'<div id="v20-item-detail" role="status" style="margin-bottom:10px;font-size:13px;color:#123b68">Periksa data barang dari hasil pembacaan foto.</div>', $s);
        }
        $s = str_replace('assets/js/checker-camera-v16.js\') }}?v=16.0', 'assets/js/checker-camera-v16.js\') }}?v=20.0', $s);
        return $s;
    });
    patchFile('public/assets/js/checker-camera-v16.js', function ($s) {
        if (!str_contains($s, "const panel = $('v16-confirm')")) throw new RuntimeException('JS V16 tidak cocok; patch dibatalkan.');
        if (!str_contains($s, 'async function resolvePhotoCode(')) {
            $anchor = '    function showNonSystem(payload, barcode) {';
            if (!str_contains($s, $anchor)) throw new RuntimeException('Tidak menemukan showNonSystem.');
            $insertion = <<<'JS'
    const itemDetail = $('v20-item-detail');
    const lookupUrl = root.dataset.cameraBarcodeLookupUrl;
    let verifiedCode = '';
    function ignoreWebQr(code) { return /^https?:\/\//i.test(String(code || '').trim()); }
    async function resolvePhotoCode(raw) {
      const value = String(raw || '').trim();
      verifiedCode = '';
      confirm.disabled = true;
      if (!value || ignoreWebQr(value)) {
        itemDetail.textContent = ignoreWebQr(value) ? 'QR website (http/https) diabaikan.' : 'Kode kosong.';
        return;
      }
      itemDetail.textContent = 'Memeriksa barcode ke master dan cycle aktif...';
      try {
        if (!lookupUrl) throw new Error('Route lookup barcode belum terpasang.');
        const response = await fetch(lookupUrl + '?barcode=' + encodeURIComponent(value), {
          credentials:'same-origin', headers:{Accept:'application/json'}
        });
        const info = await parseResponse(response);
        if (!info.ok) throw new Error(info.message || 'Barcode tidak ditemukan.');
        // TextContent prevents HTML injection from OCR or item names.
        itemDetail.textContent = 'Kode Barang: ' + info.item_code + ' | Nama: ' + info.item_name
          + ' | UOM: ' + info.uom_code + ' | Ratio: ' + info.ratio
          + ' | Qty: ' + info.qty + ' | Qty Smallest: ' + info.physical_qty
          + ' | Database: ' + info.source_database;
        verifiedCode = value;
        confirm.disabled = false;
        setStatus('Barang cocok. Konfirmasi untuk mencatat scan.');
      } catch (e) {
        itemDetail.textContent = 'Tidak dapat dikonfirmasi: ' + e.message;
        setStatus('Barcode tidak cocok. Foto ulang atau masukkan kode barang manual.');
      }
    }
JS;
            $s = str_replace($anchor, $insertion.$anchor, $s);
        }
        $s = str_replace("barcode = String(barcode || '').trim();\n      if (!barcode || busy", "barcode = String(barcode || '').trim();\n      if (ignoreWebQr(barcode)) return; // Website QR is never submitted or counted.\n      if (!barcode || busy", $s);
        $old = "        resultCode.value = value;\n        panel.hidden = false;\n        setStatus('Hasil ditemukan. Periksa kode, lalu klik Konfirmasi & Proses.');\n        resultCode.focus();";
        $new = "        if (ignoreWebQr(value)) { setStatus('QR mengarah ke website, diabaikan.'); return; }\n        resultCode.value = value;\n        panel.hidden = false;\n        await resolvePhotoCode(value);\n        resultCode.focus();";
        if (str_contains($s, $old)) $s = str_replace($old, $new, $s);
        elseif (!str_contains($s, 'await resolvePhotoCode(value);')) throw new RuntimeException('Bagian hasil pembacaan foto tidak cocok.');
        $old2 = "      const value = resultCode.value.trim();\n      if (!/^[A-Za-z0-9._~\\/-]{1,150}$/.test(value))";
        $new2 = "      const value = resultCode.value.trim();\n      if (value !== verifiedCode || confirm.disabled || ignoreWebQr(value)) { setStatus('Periksa data barang terlebih dahulu.'); return; }\n      if (!/^[A-Za-z0-9._~\\/-]{1,150}$/.test(value))";
        if (str_contains($s, $old2)) $s = str_replace($old2, $new2, $s);
        elseif (!str_contains($s, 'value !== verifiedCode')) throw new RuntimeException('Handler konfirmasi V16 tidak cocok.');
        if (!str_contains($s, "resultCode.addEventListener('input'")) {
            $needle = "    $('v16-cancel-read').addEventListener('click'";
            $s = str_replace($needle, "    resultCode.addEventListener('input', () => { verifiedCode = ''; confirm.disabled = true; itemDetail.textContent = 'Kode diedit. Klik Periksa Kode kembali.'; });\n    const recheck = document.createElement('button');\n    recheck.type = 'button'; recheck.className = 'btn'; recheck.textContent = 'Periksa Kode';\n    recheck.addEventListener('click', () => resolvePhotoCode(resultCode.value));\n    confirm.parentNode.appendChild(recheck);\n".$needle, $s);
        }
        return $s;
    });
    patchFile('app/Http/Controllers/Checker/CameraAiReadController.php', function ($s) {
        if (str_contains($s, 'QR website diabaikan')) return $s;
        $needle = "        // This is a candidate only. Frontend MUST get explicit confirmation and call ScanService.";
        if (!str_contains($s, $needle)) throw new RuntimeException('CameraAiReadController belum sesuai V12.');
        return str_replace($needle, "        if (preg_match('~^https?://~i', \$value)) {\n            return response()->json(['ok' => false, 'message' => 'QR website diabaikan.'], 422);\n        }\n".$needle, $s);
    });
    echo "Patch V20 terpasang. Jalankan php artisan optimize:clear dan verifikasi route.\n";
} catch (Throwable $e) { fwrite(STDERR, 'GAGAL: '.$e->getMessage()."\n"); exit(1); }
