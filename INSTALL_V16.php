<?php
/** V16: restore barcode camera above the validated manual entry form of V15. */
declare(strict_types=1);
$root = __DIR__;
$patch = $root.'/V16_PATCH';
if (!is_file($root.'/artisan') || !is_file($root.'/routes/web.php')) {
    fwrite(STDERR, "ERROR: letakkan paket di root Laravel, sejajar dengan artisan.\n"); exit(1);
}
$original = $root.'/resources/views/checker/scan.blade.php';
if (!is_file($original) || !str_contains((string)file_get_contents($original), 'v15-lookup-form')) {
    fwrite(STDERR, "ERROR: halaman V15 belum terpasang. Pasang V15 terlebih dahulu; V16 tidak mengganti seluruh halaman Anda.\n"); exit(1);
}
if (!is_file($root.'/public/assets/js/checker-manual-v15.js')) {
    fwrite(STDERR, "ERROR: JavaScript input manual V15 tidak ditemukan. Lengkapi V15 dahulu.\n"); exit(1);
}
if (!is_file($root.'/public/assets/vendor/zxing-browser.min.js')) {
    fwrite(STDERR, "Peringatan: ZXing tidak ditemukan. Kamera hanya bisa scan otomatis di browser yang mendukung BarcodeDetector.\n");
}
$files = [
    'resources/views/checker/scan.blade.php',
    'public/assets/js/checker-camera-v16.js',
];
try {
    foreach ($files as $file) {
        $from = $patch.'/'.$file;
        $to = $root.'/'.$file;
        if (!is_file($from)) throw new RuntimeException('File tidak ditemukan pada paket: '.$file);
        $dir = dirname($to);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Tidak dapat membuat folder: '.$dir);
        if (is_file($to) && hash_file('sha256',$to)===hash_file('sha256',$from)) {
            echo "Sudah terpasang: $file\n"; continue;
        }
        if (is_file($to)) {
            $backup=$to.'.before-v16-'.date('Ymd-His');
            if (!copy($to,$backup)) throw new RuntimeException('Gagal backup: '.$file);
            echo "Backup: $backup\n";
        }
        if (!copy($from,$to)) throw new RuntimeException('Gagal memasang: '.$file);
        echo "Diperkbarui: $file\n";
    }
    $view = (string)file_get_contents($original);
    if (!str_contains($view,'v16-scanner-video') || !str_contains($view,'v15-lookup-form')) {
        throw new RuntimeException('Validasi akhir gagal: form manual atau kamera tidak ada.');
    }
    echo "\nV16 terpasang. Tidak ada route baru dan tidak ada migration.\n";
    echo "Jalankan: php artisan optimize:clear\n";
    echo "Pastikan route checker.camera-ai.read dan checker.scan.store sudah tersedia.\n";
} catch (Throwable $e) { fwrite(STDERR, 'GAGAL: '.$e->getMessage()."\n"); exit(1); }
