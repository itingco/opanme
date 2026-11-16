<?php
/**
 * Jalankan dari root project Laravel: php APPLY_PATCH.php
 * Backup otomatis ke *.before-bulk-override.bak
 */
$root = __DIR__;
$checks = [
    'routes/web.php' => [
        "        Route::get('/cycles/{cycle}/summary-export', [CycleController::class, 'exportSummary'])\n            ->name('cycles.summary.export');",
        "        Route::get('/cycles/{cycle}/summary-export', [CycleController::class, 'exportSummary'])\n            ->name('cycles.summary.export');\n\n        Route::post('/cycles/{cycle}/summary/bulk-override', [\\App\\Http\\Controllers\\Admin\\BulkCycleOverrideController::class, 'store'])\n            ->name('cycles.summary.bulk-override');",
    ],
    'app/Http/Controllers/Admin/CycleController.php' => [
        "        \$rows = \$summary->query(\$cycle, \$warehouseId, \$varianceOnly)\n            ->paginate(60)",
        "        \$search = trim((string) \$request->query('search', ''));\n        \$query = \$summary->query(\$cycle, \$warehouseId, \$varianceOnly);\n        if (\$search !== '') {\n            \$query->where(function (\$q) use (\$search) {\n                \$q->where('summary_rows.item_code', 'like', '%'.\$search.'%')\n                  ->orWhere('summary_rows.item_name', 'like', '%'.\$search.'%');\n            });\n        }\n        \$rows = \$query\n            ->paginate(60)",
    ],
    'resources/views/admin/cycles/summary.blade.php' => [
        "        <label class=\"check\"><input type=\"checkbox\" name=\"variance_only\" value=\"1\" @checked(\$varianceOnly)> Hanya selisih</label>",
        "        <label class=\"summary-search\"><input type=\"search\" name=\"search\" value=\"{{ request('search') }}\" placeholder=\"Cari item code / nama barang\" aria-label=\"Cari item\"></label>\n        <label class=\"check\"><input type=\"checkbox\" name=\"variance_only\" value=\"1\" @checked(\$varianceOnly)> Hanya selisih</label>",
    ],
];
// Tambahkan include di toolbar tanpa mengubah struktur komponen existing.
$extra = [
    'resources/views/admin/cycles/summary.blade.php' => [
        "        <a class=\"btn excel-btn\" href=\"{{ route('admin.cycles.summary.export', \$cycle).(request()->getQueryString() ? '?'.request()->getQueryString() : '') }}\">Export Excel</a>",
        "        @include('admin.cycles.partials.summary-bulk-tools')\n        <a class=\"btn excel-btn\" href=\"{{ route('admin.cycles.summary.export', \$cycle).(request()->getQueryString() ? '?'.request()->getQueryString() : '') }}\">Export Excel</a>",
    ],
];
foreach ($extra as $path => $change) $checks[$path.'::include'] = $change;
$files = [];
foreach ($checks as $key => [$needle, $replacement]) {
    $path = explode('::', $key)[0];
    $filename = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($filename)) { fwrite(STDERR, "FILE TIDAK ADA: $path\n"); exit(1); }
    $content = $files[$path] ?? file_get_contents($filename);
    if (str_contains($content, $replacement)) { $files[$path] = $content; continue; }
    if (substr_count($content, $needle) !== 1) { fwrite(STDERR, "FORMAT BERBEDA, patch dibatalkan: $path\n"); exit(1); }
    $files[$path] = str_replace($needle, $replacement, $content);
}
foreach ($files as $path => $content) {
    $filename = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    if ($content === file_get_contents($filename)) continue;
    $backup = $filename.'.before-bulk-override.bak';
    if (!file_exists($backup) && !copy($filename, $backup)) { fwrite(STDERR, "Gagal backup: $path\n"); exit(1); }
    if (file_put_contents($filename, $content) === false) { fwrite(STDERR, "Gagal menyimpan: $path\n"); exit(1); }
    echo "DIPERBARUI: $path\n";
}
echo "SELESAI. Jalankan: php artisan optimize:clear\n";
