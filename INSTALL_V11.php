<?php
/**
 * Corrects route /checker/sessions/{session}/scan (previous patches targeted only warehouse legacy).
 * Copy package contents to Laravel root, then run: php INSTALL_V11.php
 */
$root=__DIR__;
if (!is_file($root.'/artisan')) { fwrite(STDERR,"ERROR: Jalankan di root Laravel (sejajar artisan).\n"); exit(1); }
function editV11(string $relative, callable $fn): void {
    global $root;
    $path=$root.'/'.$relative;
    if (!is_file($path)) throw new RuntimeException("File tidak ditemukan: $relative");
    $old=file_get_contents($path);
    $new=$fn($old);
    if ($new===$old) { echo "SKIP $relative\n"; return; }
    $backup=$path.'.before-v11';
    if (!is_file($backup) && !copy($path,$backup)) throw new RuntimeException("Backup gagal: $relative");
    if (file_put_contents($path,$new)===false) throw new RuntimeException("Gagal menyimpan: $relative");
    echo "UPDATE $relative (backup .before-v11)\n";
}
function addAfterV11(string $src,string $needle,string $extra,string $marker): string {
    if (str_contains($src,$marker)) return $src;
    if (substr_count($src,$needle)!==1) throw new RuntimeException("Anchor hilang/tidak unik: ".substr($needle,0,90));
    return str_replace($needle,$needle.$extra,$src);
}
try {
    // The route actually visible in the screenshot is /checker/sessions/3/scan.
    editV11('routes/web.php', static function(string $s): string {
        $needle="        )->name('scan.non-system');";
        $extra="\n\n        Route::post('/sessions/{session}/manual-photo', [\\App\\Http\\Controllers\\Checker\\ManualPhotoCountController::class, 'store'])->name('manual-photo.store');";
        $extra.="\n        Route::post('/sessions/{session}/photo-read', \\App\\Http\\Controllers\\Checker\\LegacyPhotoReadController::class)->name('photo.read');";
        return addAfterV11($s,$needle,$extra,"/sessions/{session}/manual-photo");
    });
    // Legacy warehouse route also supported; do not touch its existing working routes if present.
    editV11('routes/warehouse_sampling.php', static function(string $s):string {
        $needle="    Route::post('/sessions/{session}/scan/non-system', [ScanController::class, 'storeNonSystem'])->name('scan.non-system');";
        if (!str_contains($s,"name('manual-photo.store')")) $s=addAfterV11($s,$needle,"\n    Route::post('/sessions/{session}/manual-photo', [\\App\\Http\\Controllers\\Checker\\ManualPhotoCountController::class, 'store'])->name('manual-photo.store');","manual-photo.store");
        if (!str_contains($s,"name('photo.read')")) $s=addAfterV11($s,$needle,"\n    Route::post('/sessions/{session}/photo-read', \\App\\Http\\Controllers\\Checker\\LegacyPhotoReadController::class)->name('photo.read');","photo.read");
        return $s;
    });
    editV11('resources/views/checker/scan.blade.php', static function(string $s):string {
        // This is the template actually rendered by the /checker route.
        if (!str_contains($s,"checker.partials_photo_tools")) {
            $needle='<form id="manual-scan-form"';
            if (substr_count($s,$needle)!==1) throw new RuntimeException('Form barcode tidak ditemukan/duplikat');
            $s=str_replace($needle,"@include('checker.partials_photo_tools')\n    ".$needle,$s);
        }
        if (!str_contains($s,"checker.partials_manual_count")) {
            $needle='<details class="change-location">';
            if (substr_count($s,$needle)!==1) throw new RuntimeException('Ganti lokasi tidak ditemukan/duplikat');
            $s=str_replace($needle,"@include('checker.partials_manual_count')\n    ".$needle,$s);
        }
        $s=str_replace('Scanner / input barcode','Scan barcode (jika ada)',$s);
        $s=str_replace('Scan atau ketik barcode','Scan barcode atau gunakan input manual di bawah',$s);
        return $s;
    });
    echo "OK: Pastikan files app/, resources/, public/, database/ sudah dicopy. Jalankan php artisan migrate dan php artisan optimize:clear.\n";
} catch(Throwable $e) { fwrite(STDERR,"ERROR V11: ".$e->getMessage()."\n"); exit(1); }
