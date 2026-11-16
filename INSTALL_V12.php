<?php
/** Run from Laravel root after extracting this package. Backups created before edits. */
$root = __DIR__;
if (! is_file($root.'/artisan')) { fwrite(STDERR, "ERROR: Place patch beside artisan.\n"); exit(1); }
$updates = [
    'routes/web.php' => function (string $s): string {
        $anchor = "        )->name('scan.non-system');";
        if (! str_contains($s, "->name('camera-manual.store')")) {
            if (! str_contains($s, $anchor)) throw new RuntimeException('Cannot find checker route anchor.');
            $insert = <<<'TEXT'

        Route::post('/sessions/{session}/manual-photo', [\App\Http\Controllers\Checker\CameraManualController::class, 'store'])
            ->name('camera-manual.store');
        Route::post('/sessions/{session}/camera-ai', [\App\Http\Controllers\Checker\CameraAiReadController::class, '__invoke'])
            ->name('camera-ai.read');
TEXT;
            $s = str_replace($anchor, $anchor.$insert, $s);
        }
        $anchor2 = "        Route::get('/cycles/{cycle}/summary', [CycleController::class, 'summary'])";
        if (! str_contains($s, "->name('cycles.manual-photo.view')")) {
            if (! str_contains($s, $anchor2)) throw new RuntimeException('Cannot find admin route anchor.');
            $insert2 = <<<'TEXT'
        Route::get('/cycles/{cycle}/warehouse/{warehouseId}/scan/{scanId}/photo', [\App\Http\Controllers\Checker\CameraManualController::class, 'adminPhoto'])
            ->name('cycles.manual-photo.view');

TEXT;
            $s = str_replace($anchor2, $insert2.$anchor2, $s);
        }
        return $s;
    },
    'routes/warehouse_sampling.php' => function (string $s): string {
        $anchor = "->name('scan.non-system');";
        if (! str_contains($s, "->name('warehouse.legacy.camera-manual.store')") && ! str_contains($s, "->name('camera-manual.store')")) {
            $pos = strrpos($s, $anchor);
            if ($pos === false) throw new RuntimeException('Cannot find warehouse-legacy route anchor.');
            $end = $pos+strlen($anchor);
            $insert = <<<'TEXT'

    Route::post('/sessions/{session}/manual-photo', [\App\Http\Controllers\Checker\CameraManualController::class, 'store'])->name('camera-manual.store');
    Route::post('/sessions/{session}/camera-ai', [\App\Http\Controllers\Checker\CameraAiReadController::class, '__invoke'])->name('camera-ai.read');
TEXT;
            $s = substr($s, 0, $end).$insert.substr($s, $end);
        }
        return $s;
    },
    'public/assets/js/app.js' => function (string $s): string {
        $anchor = "    form?.addEventListener('submit',";
        if (! str_contains($s, 'window.__opnameSubmitCameraCode = submitBarcode')) {
            if (! str_contains($s, $anchor)) throw new RuntimeException('Cannot locate scanner submit handler.');
            $s = str_replace($anchor, "    window.__opnameSubmitCameraCode = submitBarcode;\n".$anchor, $s);
        }
        $old = 'if (!barcode || busy || nonSystemOpen) return;';
        if (str_contains($s, $old)) $s = str_replace($old, 'if (!barcode || busy || nonSystemOpen || window.__opnameScannerPause) return;', $s);
        if (! str_contains($s, 'window.opanmeLoudBeep = function')) {
            $anchor2 = 'function signal(ok) {';
            if (! str_contains($s, $anchor2)) throw new RuntimeException('Cannot find sound function.');
            $beep = <<<'JS'
// Audio volume is limited by the browser and the phone's physical volume.
window.opanmeLoudBeep = function () {
    try {
        const A = window.AudioContext || window.webkitAudioContext;
        if (!A) return;
        const ctx = window.__opnameAudioCtx || (window.__opnameAudioCtx = new A());
        if (ctx.state === 'suspended') ctx.resume().catch(() => {});
        const t = ctx.currentTime;
        [0, 0.13].forEach((offset, index) => {
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'square'; o.frequency.value = index ? 1046 : 880;
            g.gain.setValueAtTime(0.001, t+offset);
            g.gain.exponentialRampToValueAtTime(0.24, t+offset+0.014);
            g.gain.exponentialRampToValueAtTime(0.001, t+offset+0.11);
            o.connect(g); g.connect(ctx.destination);
            o.start(t+offset); o.stop(t+offset+0.12);
        });
        if (navigator.vibrate) navigator.vibrate([100,50,100]);
    } catch(e) { console.debug('Audio not available', e); }
};
JS;
            $s = str_replace($anchor2, $beep."\n".$anchor2."\n    if (ok) { window.opanmeLoudBeep(); return; }", $s);
            // Unlock AudioContext through a real camera-button interaction.
            $marker = "    startBtn?.addEventListener('click', async () => {";
            if (str_contains($s, $marker)) {
                $unlock = "    startBtn?.addEventListener('pointerdown', () => { try { const A = window.AudioContext || window.webkitAudioContext; if (A) { const ctx = window.__opnameAudioCtx || (window.__opnameAudioCtx = new A()); ctx.resume().catch(() => {}); } } catch(e) {} });\n";
                $s = str_replace($marker, $unlock.$marker, $s);
            }

        }
        return $s;
    },
    'resources/views/layouts/app.blade.php' => function (string $s): string {
        $updated = preg_replace('/(assets\/js\/app\.js.*?\?v=)[^"\']+/', '${1}20261116v12', $s);
        if ($updated === null) throw new RuntimeException('Invalid layout asset version pattern.');
        return $updated;
    },
];
foreach ($updates as $file => $transform) {
    $path = $root.'/'.$file;
    if (! is_file($path)) { fwrite(STDERR, "Missing $file\n"); exit(1); }
    $old = file_get_contents($path);
    try { $new = $transform($old); } catch (Throwable $e) { fwrite(STDERR, "$file: {$e->getMessage()}\n"); exit(1); }
    if ($new === $old) { echo "UNCHANGED $file\n"; continue; }
    $backup = $path.'.before-v12';
    if (! is_file($backup) && ! copy($path, $backup)) { fwrite(STDERR, "Backup failed: $file\n"); exit(1); }
    if (file_put_contents($path, $new) === false) { fwrite(STDERR, "Write failed: $file\n"); exit(1); }
    echo "UPDATED $file (backup: $backup)\n";
}
echo "Done. Run php artisan migrate and php artisan optimize:clear\n";
