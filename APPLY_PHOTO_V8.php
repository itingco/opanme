<?php
/** Run from project Laravel root after copying the package directory contents here. */
$root = __DIR__;
if (! is_file($root.'/artisan')) { fwrite(STDERR, "Jalankan di root Laravel (sejajar artisan).\n"); exit(1); }
$routeFile = $root.'/routes/warehouse_sampling.php';
$route = file_get_contents($routeFile);
if ($route === false) { fwrite(STDERR, "routes/warehouse_sampling.php tidak ditemukan.\n"); exit(1); }
if (str_contains($route, "->name('scan.non-system');") && ! str_contains($route, "->name('photo.read');")) {
    $anchor = "    Route::post('/sessions/{session}/scan/non-system', [ScanController::class, 'storeNonSystem'])->name('scan.non-system');";
    $position = strpos($route, "->name('warehouse.legacy.')");
    if ($position === false) { fwrite(STDERR, "Route Checker Legacy belum ditemukan. Tidak ada file diubah.\n"); exit(1); }
    $start = strpos($route, "->name('warehouse.legacy.')");
    $idx = strpos($route, $anchor, $start);
    if ($idx === false) { fwrite(STDERR, "Anchor route legacy tidak cocok. Tidak ada file diubah.\n"); exit(1); }
    $replacement = $anchor."\n    Route::post('/sessions/{session}/photo-read', \\App\\Http\\Controllers\\Checker\\LegacyPhotoReadController::class)\n        ->middleware('throttle:12,1')->name('photo.read');";
    $newRoute = substr_replace($route, $replacement, $idx, strlen($anchor));
    copy($routeFile, $routeFile.'.before-photo-v8.bak');
    if (file_put_contents($routeFile, $newRoute) === false) { fwrite(STDERR,"Gagal menulis route.\n"); exit(1); }
    echo "Route photo.read berhasil ditambahkan; backup dibuat.\n";
} else { echo "Route sudah terpasang atau tidak sesuai. Cek php artisan route:list --name=photo.read\n"; }
