<?php
/**
 * Patch v3: allow per-row ERP / NON-SYSTEM override editor during OPEN and CLOSED.
 * Run from project root: php APPLY_FIX_OPEN_ROW.php
 * No database migration. Never changes close/finalize logic.
 */
$root = __DIR__;
$files = [
    'resources/views/admin/cycles/summary.blade.php',
    'app/Http/Controllers/Admin/CycleController.php',
];
foreach ($files as $path) {
    if (!is_file($root.'/'.$path)) {
        fwrite(STDERR, "ERROR: File tidak ditemukan: $path. Jalankan installer dari root Laravel.\n");
        exit(1);
    }
}
$viewPath = $files[0];
$controllerPath = $files[1];
$view = file_get_contents($root.'/'.$viewPath);
$controller = file_get_contents($root.'/'.$controllerPath);
$originals = [$viewPath => $view, $controllerPath => $controller];
$openGuard = 'in_array($cycle->status, [\\App\\Models\\StockOpnameCycle::STATUS_OPEN, \\App\\Models\\StockOpnameCycle::STATUS_CLOSED], true)';
$oldGuard = '$cycle->status === \\App\\Models\\StockOpnameCycle::STATUS_CLOSED';

// Identify the relevant @if using context instead of replacing unrelated CLOSED-only finalization.
// In the original Summary, there are two such guarded editors:
// 1) "Barang Non-System" toolbar, 2) ERP/Non-System per-row Override / Audit.
$targets = [
    'tombol tambah Non-System' => '/@if\(\s*'.preg_quote($oldGuard, '/').'\s*\)(?=\s*<details\s+class="non-system-admin-add")/',
    'editor override per item' => '/@if\(\s*'.preg_quote($oldGuard, '/').'\s*\)(?=\s*<details\s+class="override-editor\s+action-override-editor")/',
];
foreach ($targets as $label => $pattern) {
    $count = 0;
    $new = preg_replace($pattern, '@if('.$openGuard.')', $view, -1, $count);
    if ($new === null) {
        fwrite(STDERR, "ERROR regex: $label\n"); exit(1);
    }
    $view = $new;
    if ($count === 0) {
        // Check that this specific editor already has its OPEN guard; otherwise stop.
        $checkPattern = str_replace(preg_quote($oldGuard, '/'), preg_quote($openGuard, '/'), $pattern);
        if (!preg_match($checkPattern, $view)) {
            fwrite(STDERR, "ERROR: Tidak menemukan kondisi editor '$label' yang sesuai. Template mungkin berbeda. Tidak ada file diubah.\n");
            exit(1);
        }
        echo "SUDAH AKTIF: $label\n";
    } else {
        echo "AKTIFKAN OPEN: $label\n";
    }
}

// Remove the outdated information banner if v2 patch was not run, without touching CLOSED logic.
$oldBanner = '@if(in_array($cycle->status, [\\App\\Models\\StockOpnameCycle::STATUS_DRAFT, \\App\\Models\\StockOpnameCycle::STATUS_OPEN], true))' . "\n" .
'    <div class="alert info-lite">' . "\n" .
'        Manual override baru dapat dilakukan setelah cycle CLOSED. Saat OPEN, checker tetap dapat mencatat barang Non-System dari halaman scan.' . "\n" .
'    </div>' . "\n" .
'@endif';
$newBanner = '@if($cycle->status === \\App\\Models\\StockOpnameCycle::STATUS_DRAFT)' . "\n" .
'    <div class="alert info-lite">Manual override tersedia setelah cycle OPEN.</div>' . "\n" .
'@elseif($cycle->status === \\App\\Models\\StockOpnameCycle::STATUS_OPEN)' . "\n" .
'    <div class="alert info-lite">Cycle OPEN: admin bisa override per item atau massal meskipun belum ada scan. Checker tetap dapat melakukan scan; override menjadi nilai Final Fisik hingga diedit atau dihapus.</div>' . "\n" .
'@endif';
if (str_contains($view, $oldBanner)) $view = str_replace($oldBanner, $newBanner, $view);

// Update only the two per-item ERP write/delete guards, keep FINALIZED hard-locked.
$guards = [
    [
        "if (\$locked->status !== StockOpnameCycle::STATUS_CLOSED) {\n                throw ValidationException::withMessages([\n                    'override' => 'Override hanya dapat dilakukan setelah cycle CLOSED agar hasil scan tidak berubah lagi.',",
        "if (!in_array(\$locked->status, [StockOpnameCycle::STATUS_OPEN, StockOpnameCycle::STATUS_CLOSED], true)) {\n                throw ValidationException::withMessages([\n                    'override' => 'Override hanya diizinkan ketika cycle OPEN atau CLOSED.',",
        'validasi simpan override ERP',
    ],
    [
        "if (\$locked->status !== StockOpnameCycle::STATUS_CLOSED) {\n                throw ValidationException::withMessages([\n                    'override' => 'Override hanya dapat dihapus setelah cycle CLOSED.',",
        "if (!in_array(\$locked->status, [StockOpnameCycle::STATUS_OPEN, StockOpnameCycle::STATUS_CLOSED], true)) {\n                throw ValidationException::withMessages([\n                    'override' => 'Override hanya dapat dihapus ketika cycle OPEN atau CLOSED.',",
        'validasi hapus override ERP',
    ],
    [
        "if (\$cycle->status !== StockOpnameCycle::STATUS_CLOSED) {\n            throw ValidationException::withMessages([\n                'override' => 'Override hanya dapat dilakukan setelah cycle CLOSED.',",
        "if (!in_array(\$cycle->status, [StockOpnameCycle::STATUS_OPEN, StockOpnameCycle::STATUS_CLOSED], true)) {\n            throw ValidationException::withMessages([\n                'override' => 'Override hanya dapat dilakukan ketika cycle OPEN atau CLOSED.',",
        'validasi override Non-System',
    ],
];
foreach ($guards as [$old, $replacement, $label]) {
    if (str_contains($controller, $old)) {
        $controller = str_replace($old, $replacement, $controller);
        echo "AKTIFKAN OPEN: $label\n";
    } elseif (str_contains($controller, $replacement) || preg_match('/in_array\(\$(?:locked|cycle)->status,\s*\[StockOpnameCycle::STATUS_OPEN,\s*StockOpnameCycle::STATUS_CLOSED\],\s*true\)/', $controller)) {
        echo "SUDAH AKTIF: $label (periksa hasil uji)\n";
    } else {
        fwrite(STDERR, "ERROR: Format tidak cocok untuk $label. Tidak ada file diubah.\n"); exit(1);
    }
}

// Never silently succeed if UI still has a CLOSED-only editor.
if (!str_contains($view, '@if('.$openGuard.")\n                                <details class=\"override-editor")) {
    // Allow an indentation variation while still requiring the explicit editor guard.
    if (!preg_match('/@if\('.preg_quote($openGuard, '/').'\)\s*<details\s+class="override-editor\s+action-override-editor"/', $view)) {
        fwrite(STDERR, "ERROR: Editor per item masih belum menerima OPEN. Tidak ada file diubah.\n"); exit(1);
    }
}
$results = [$viewPath => $view, $controllerPath => $controller];
foreach ($results as $path => $content) {
    if ($content === $originals[$path]) continue;
    $backup = $root.'/'.$path.'.before-fix-open-row-'.date('YmdHis').'.bak';
    if (!copy($root.'/'.$path, $backup)) { fwrite(STDERR, "ERROR backup: $path\n"); exit(1); }
    if (file_put_contents($root.'/'.$path, $content, LOCK_EX) === false) {
        fwrite(STDERR, "ERROR menulis: $path\n"); exit(1);
    }
    echo "UPDATED: $path (backup: ".basename($backup).")\n";
}
echo "BERHASIL. Jalankan php artisan optimize:clear, refresh browser Ctrl+F5.\n";
