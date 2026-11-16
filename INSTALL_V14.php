<?php
/**
 * Patch V14: admin preview of checker manual photo evidence.
 * Run from Laravel project root after extracting this ZIP.
 */
$root = __DIR__;
if (! is_file($root.'/artisan')) {
    fwrite(STDERR, "ERROR: Letakkan seluruh isi ZIP pada folder yang sama dengan artisan.\n");
    exit(1);
}

$controller = 'app/Http/Controllers/Admin/CyclePhotoEvidenceController.php';
$view = 'resources/views/admin/cycles/photo-evidence.blade.php';
$required = [$controller, $view, 'routes/web.php', 'resources/views/admin/cycles/summary.blade.php'];
foreach ($required as $file) {
    if (! is_file($root.'/'.$file)) {
        fwrite(STDERR, "ERROR: File tidak ditemukan: $file\n"); exit(1);
    }
}

$changes = [
    'routes/web.php' => static function (string $source): string {
        if (str_contains($source, "->name('cycles.photo-evidence.index')")) return $source;
        $anchor = "        Route::get('/cycles/{cycle}/summary', [CycleController::class, 'summary'])";
        if (! str_contains($source, $anchor)) throw new RuntimeException('Anchor route admin.cycles.summary tidak ditemukan.');
        $routes = <<<'ROUTES'
        // Admin photo audit: scoped to cycle + warehouse + item + scan.
        Route::get('/cycles/{cycle}/warehouse/{warehouseId}/item/{itemId}/photo-evidence', [\App\Http\Controllers\Admin\CyclePhotoEvidenceController::class, 'index'])
            ->name('cycles.photo-evidence.index');
        Route::get('/cycles/{cycle}/warehouse/{warehouseId}/item/{itemId}/scan/{scanId}/photo-evidence', [\App\Http\Controllers\Admin\CyclePhotoEvidenceController::class, 'photo'])
            ->name('cycles.photo-evidence.photo');

ROUTES;
        return str_replace($anchor, $routes.$anchor, $source);
    },
    'resources/views/admin/cycles/summary.blade.php' => static function (string $source): string {
        if (str_contains($source, "'admin.cycles.photo-evidence.index'")) return $source;
        $anchor = '<strong>{{ $row->item_code }}</strong>';
        if (! str_contains($source, $anchor)) throw new RuntimeException('Anchor item code pada summary tidak ditemukan.');
        $link = <<<'BLADE'
<strong>{{ $row->item_code }}</strong>
                            @if(!$isNonSystem)
                                <small><a href="{{ route('admin.cycles.photo-evidence.index', [$cycle, $row->warehouse_id, $row->item_id]) }}" style="font-weight:700;text-decoration:underline">Detail & Foto Bukti</a></small>
                            @endif
BLADE;
        return str_replace($anchor, $link, $source);
    },
];

// Compute all changes before writing, so unexpected local layouts remain untouched.
$prepared = [];
try {
    foreach ($changes as $file => $transform) {
        $old = file_get_contents($root.'/'.$file);
        $new = $transform($old);
        $prepared[$file] = [$old, $new];
    }
} catch (Throwable $error) {
    fwrite(STDERR, "ERROR: ".$error->getMessage()."\nNo files modified.\n"); exit(1);
}

foreach ($prepared as $file => [$old, $new]) {
    if ($old === $new) { echo "UNCHANGED $file\n"; continue; }
    $destination = $root.'/'.$file;
    $backup = $destination.'.before-v14';
    if (! is_file($backup) && ! copy($destination, $backup)) {
        fwrite(STDERR, "ERROR: Backup gagal: $file\n"); exit(1);
    }
    if (file_put_contents($destination, $new, LOCK_EX) === false) {
        fwrite(STDERR, "ERROR: Gagal menulis: $file\n"); exit(1);
    }
    echo "UPDATED $file (backup tersimpan)\n";
}
echo "\nPatch V14 terpasang. Jalankan php artisan optimize:clear, kemudian php artisan route:list --name=photo-evidence\n";
