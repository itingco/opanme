<?php
/**
 * Install this patch only in the Laravel project root, next to artisan.
 * Supports local V12-V14 versions; never overwrites controller customizations.
 */
declare(strict_types=1);
$root = __DIR__;
if (!is_file($root.'/artisan') || !is_file($root.'/routes/web.php')) {
    fwrite(STDERR, "ERROR: jalankan installer pada root project Laravel (sejajar artisan).\n");
    exit(1);
}
$bundle = $root.'/V15_PATCH';
if (!is_file($bundle.'/resources/views/checker/scan.blade.php')) {
    fwrite(STDERR, "ERROR: folder V15_PATCH harus disalin bersama INSTALL_V15.php.\n");
    exit(1);
}
function installFile(string $from, string $to, bool $onlyIfMissing=false): void {
    if ($onlyIfMissing && file_exists($to)) { echo "Dipertahankan: $to\n"; return; }
    if (!is_file($from)) throw new RuntimeException("File paket tidak ditemukan: $from");
    if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0775, true) && !is_dir(dirname($to))) throw new RuntimeException('Gagal membuat folder: '.dirname($to));
    $content = file_get_contents($from);
    if (is_file($to) && file_get_contents($to) === $content) { echo "Sudah terpasang: $to\n"; return; }
    if (is_file($to)) {
        $backup=$to.'.before-v15-'.date('YmdHis');
        if (!copy($to,$backup)) throw new RuntimeException("Backup gagal: $to");
        echo "Backup: $backup\n";
    }
    if (file_put_contents($to,$content) === false) throw new RuntimeException("Gagal menulis $to");
    echo "Terpasang: $to\n";
}
try {
    // The existing V12 store service and V14 photo audit remain unchanged.
    foreach ([
        'app/Http/Controllers/Checker/CameraManualController.php',
        'app/Http/Controllers/Checker/CameraAiReadController.php',
        'database/migrations/2026_11_16_235900_create_opname_manual_scan_photos_table.php',
        'config/opanme_camera_ai.php',
    ] as $p) {
        installFile($bundle.'/optional_dependencies/'.$p, $root.'/'.$p, true);
    }
    foreach ([
        'app/Http/Controllers/Checker/CameraItemLookupController.php',
        'resources/views/checker/scan.blade.php',
        'public/assets/js/checker-manual-v15.js',
        'routes/checker_item_lookup_v15.php',
        'routes/checker_camera_fallback_v15.php',
    ] as $p) installFile($bundle.'/'.$p, $root.'/'.$p);
    $routesPath = $root.'/routes/web.php';
    $routes = file_get_contents($routesPath);
    foreach (['checker_item_lookup_v15.php', 'checker_camera_fallback_v15.php'] as $name) {
        if (!str_contains($routes, "require __DIR__.'/$name';")) {
            $routes .= "\nrequire __DIR__.'/$name';\n";
        }
    }
    if ($routes !== file_get_contents($routesPath)) {
        $backup = $routesPath.'.before-v15-'.date('YmdHis');
        if (!copy($routesPath, $backup)) throw new RuntimeException('Backup route gagal');
        file_put_contents($routesPath, $routes);
        echo "Route tambahan terdaftar dalam routes/web.php\n";
    }
    echo "\nFile selesai dipasang. Pemeriksaan route:\n";
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/artisan').' route:list --name=manual-item.lookup 2>&1';
    exec($cmd, $lines, $status); echo implode("\n", $lines)."\n";
    if ($status !== 0 || !str_contains(implode("\n", $lines), 'checker.manual-item.lookup')) {
        throw new RuntimeException('Route lookup tidak terdaftar. Periksa hasil php artisan route:list dan log Laravel.');
    }
    echo "\nBERHASIL: Jalankan php artisan migrate && php artisan optimize:clear\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'PATCH BELUM BERHASIL: '.$e->getMessage()."\n");
    exit(1);
}
