<?php
/**
 * Patch installer untuk registrasi route; jalankan sekali dari root project.
 * Tidak mengubah database, vendor atau konfigurasi .env.
 */
$root = __DIR__;
$routes = $root . '/routes/web.php';
if (!is_file($routes)) {
    fwrite(STDERR, "Jalankan script ini dari root project Laravel, bukan di luar folder project.\n");
    exit(1);
}
$original = file_get_contents($routes);
$patched = $original;

// Route cycle list harus menunjuk ke controller baru supaya filter database benar-benar bekerja.
$pattern = '~Route::get\(\s*[\'\"]/cycles[\'\"]\s*,\s*\[CycleController::class\s*,\s*[\'\"]index[\'\"]\s*\]\s*\)~';
$count = 0;
$patched = preg_replace($pattern, "Route::get('/cycles', [\\\\App\\\\Http\\\\Controllers\\\\Admin\\\\CycleListController::class, 'index'])", $patched, 1, $count);
if ($count !== 1 && strpos($patched, 'CycleListController::class') === false) {
    fwrite(STDERR, "Tidak menemukan route /cycles pada routes/web.php. Tidak ada file yang diubah.\n");
    exit(1);
}

if (!str_contains($patched, "->name('menu-permissions.index')")) {
    $needle = "        Route::get('/users', [UserController::class, 'index'])";
    $insert = <<<'ROUTES'
        // Setup Role & Hak Akses: Superadmin saja.
        Route::get('/menu-permissions', [\App\Http\Controllers\Admin\MenuPermissionController::class, 'index'])
            ->name('menu-permissions.index');
        Route::put('/menu-permissions', [\App\Http\Controllers\Admin\MenuPermissionController::class, 'update'])
            ->name('menu-permissions.update');

ROUTES;
    if (strpos($patched, $needle) === false) {
        fwrite(STDERR, "Tidak menemukan penanda routes User Management. Tidak ada file yang diubah.\n");
        exit(1);
    }
    $patched = str_replace($needle, $insert.$needle, $patched);
}

if ($patched === $original) {
    echo "Route patch sudah terpasang, tidak ada perubahan.\n";
    exit(0);
}
$backup = $routes . '.backup-' . date('Ymd-His');
if (!copy($routes, $backup)) {
    fwrite(STDERR, "Tidak bisa membuat backup routes/web.php.\n");
    exit(1);
}
if (file_put_contents($routes, $patched, LOCK_EX) === false) {
    fwrite(STDERR, "Gagal menyimpan routes/web.php. Backup: {$backup}\n");
    exit(1);
}
echo "Routes berhasil diperbarui. Backup: {$backup}\n";
echo "Lanjutkan: php artisan optimize:clear dan php artisan route:list\n";
