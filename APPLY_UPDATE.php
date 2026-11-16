<?php
/**
 * Update patch v2 (requires previous Search + Bulk Override patch installed).
 * Run in Laravel project root: php APPLY_UPDATE.php
 */
$root = __DIR__;
$controller = 'app/Http/Controllers/Admin/CycleController.php';
$view = 'resources/views/admin/cycles/summary.blade.php';
foreach ([$controller, $view, 'routes/web.php'] as $file) {
    if (!is_file($root.'/'.$file)) { fwrite(STDERR, "FILE TIDAK ADA: $file\n"); exit(1); }
}
$files = [
    $controller => file_get_contents($root.'/'.$controller),
    $view => file_get_contents($root.'/'.$view),
];
$replace = static function (string $file, string $find, string $with, bool $optional = false) use (&$files): void {
    $current = $files[$file];
    if (strpos($current, $with) !== false) return;
    $count = substr_count($current, $find);
    if ($count === 0 && $optional) return;
    if ($count !== 1) { fwrite(STDERR, "FORMAT BERBEDA: $file, ditemukan $count kali untuk: ".substr($find,0,100)."\n"); exit(1); }
    $files[$file] = str_replace($find, $with, $current);
};
$open = "[StockOpnameCycle::STATUS_OPEN, StockOpnameCycle::STATUS_CLOSED]";
// Preserve explicit FINALIZED guards, alter only restrictive checks inside override methods.
$replace($controller,
    "if (\$locked->status !== StockOpnameCycle::STATUS_CLOSED) {\n                throw ValidationException::withMessages([\n                    'override' => 'Override hanya dapat dilakukan setelah cycle CLOSED agar hasil scan tidak berubah lagi.',",
    "if (!in_array(\$locked->status, $open, true)) {\n                throw ValidationException::withMessages([\n                    'override' => 'Override hanya dapat dilakukan ketika cycle OPEN atau CLOSED.',"
);
$replace($controller,
    "if (\$locked->status !== StockOpnameCycle::STATUS_CLOSED) {\n                throw ValidationException::withMessages([\n                    'override' => 'Override hanya dapat dihapus setelah cycle CLOSED.',",
    "if (!in_array(\$locked->status, $open, true)) {\n                throw ValidationException::withMessages([\n                    'override' => 'Override hanya dapat dihapus ketika cycle OPEN atau CLOSED.',"
);
$replace($controller,
    "if (\$cycle->status !== StockOpnameCycle::STATUS_CLOSED) {\n            throw ValidationException::withMessages([\n                'override' => 'Override hanya dapat dilakukan setelah cycle CLOSED.',",
    "if (!in_array(\$cycle->status, $open, true)) {\n            throw ValidationException::withMessages([\n                'override' => 'Override hanya dapat dilakukan ketika cycle OPEN atau CLOSED.',"
);
// Remove misleading warning in OPEN state, but keep DRAFT warning.
$replace($view,
    "@if(in_array(\$cycle->status, [\\App\\Models\\StockOpnameCycle::STATUS_DRAFT, \\App\\Models\\StockOpnameCycle::STATUS_OPEN], true))\n    <div class=\"alert info-lite\">\n        Manual override baru dapat dilakukan setelah cycle CLOSED. Saat OPEN, checker tetap dapat mencatat barang Non-System dari halaman scan.\n    </div>\n@endif",
    "@if(\$cycle->status === \\App\\Models\\StockOpnameCycle::STATUS_DRAFT)\n    <div class=\"alert info-lite\">Manual override tersedia mulai status OPEN hingga CLOSED.</div>\n@elseif(\$cycle->status === \\App\\Models\\StockOpnameCycle::STATUS_OPEN)\n    <div class=\"alert info-lite\">Cycle OPEN: admin boleh override, checker masih dapat scan. Nilai override menjadi Final Fisik hingga diedit atau dihapus; Finalisasi tetap harus melewati CLOSED.</div>\n@endif"
);
// Both views (Non-System admin panel and per-row override) are conditionally CLOSED; enable for OPEN.
$needle = "@if(\$cycle->status === \\App\\Models\\StockOpnameCycle::STATUS_CLOSED)";
$replacement = "@if(in_array(\$cycle->status, [\\App\\Models\\StockOpnameCycle::STATUS_OPEN, \\App\\Models\\StockOpnameCycle::STATUS_CLOSED], true))";
// Two occurrences in the template have different indentation. Don't touch closed-only finalization block.
foreach (["        $needle", "                            $needle"] as $f) {
    $replace($view, $f, str_replace($needle, $replacement, $f));
}
foreach ($files as $path => $value) {
    $filename = $root.'/'.$path;
    if (file_get_contents($filename) === $value) continue;
    $backup = $filename.'.before-open-override.bak';
    if (!is_file($backup) && !copy($filename, $backup)) { fwrite(STDERR, "BACKUP GAGAL: $path\n"); exit(1); }
    if (file_put_contents($filename, $value) === false) { fwrite(STDERR, "GAGAL SIMPAN: $path\n"); exit(1); }
    echo "UPDATED $path\n";
}
echo "Selesai. php artisan optimize:clear\n";
