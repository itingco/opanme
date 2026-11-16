<?php
// Jalankan sekali dari root Laravel. Backup .v9-before dibuat jika file berubah.
$root = __DIR__;
if (!is_file($root.'/artisan')) { fwrite(STDERR, "Jalankan dari root Laravel.\n"); exit(1); }
function modifyV9(string $path, callable $edit): void {
    global $root;
    $file = $root.'/'.$path;
    if (!is_file($file)) { throw new RuntimeException("Tidak ditemukan: $path"); }
    $old = file_get_contents($file);
    $new = $edit($old);
    if ($new === $old) { echo "SKIP $path\n"; return; }
    if (!is_file($file.'.v9-before')) copy($file, $file.'.v9-before');
    if (file_put_contents($file, $new) === false) throw new RuntimeException("Gagal menulis $path");
    echo "UPDATE $path\n";
}
function onceV9(string $s, string $marker, string $needle, string $replacement): string {
    if (str_contains($s, $marker)) return $s;
    if (!str_contains($s, $needle)) throw new RuntimeException('Pola sumber tidak ditemukan: '.substr($needle,0,100));
    return str_replace($needle, $replacement, $s);
}
try {
    modifyV9('routes/web.php', function(string $s): string {
        $needle = "        Route::get('/cycles/{cycle}/summary', [CycleController::class, 'summary'])";
        $append = "        Route::get('/cycles/{cycle}/summary/sampling-history/{warehouseId}/{itemId}', [\\App\\Http\\Controllers\\Admin\\CycleSamplingItemHistoryController::class, 'show'])->name('cycles.summary.sampling-history');\n";
        $append .= "        Route::get('/cycles/{cycle}/manual-photo/{scanId}', [\\App\\Http\\Controllers\\Admin\\CycleSamplingItemHistoryController::class, 'photo'])->name('cycles.manual-photo');\n\n";
        return onceV9($s, 'cycles.summary.sampling-history', $needle, $append.$needle);
    });
    modifyV9('routes/warehouse_sampling.php', function(string $s): string {
        $needle = "    Route::post('/sessions/{session}/scan/non-system', [ScanController::class, 'storeNonSystem'])->name('scan.non-system');";
        // Needle occurs in legacy group only here (route web checker is elsewhere).
        $append = "\n    Route::post('/sessions/{session}/manual-photo', [\\App\\Http\\Controllers\\Checker\\ManualPhotoCountController::class, 'store'])->name('manual-photo.store');";
        return onceV9($s, "name('manual-photo.store')", $needle, $needle.$append);
    });
    modifyV9('resources/views/checker/warehouse-legacy-scan.blade.php', function(string $s): string {
        if (str_contains($s, 'partials_manual_count')) return $s;
        return $s."\n@section('content')\n    @parent\n    @include('checker.partials_manual_count')\n@endsection\n";
    });
    modifyV9('resources/views/admin/cycles/summary.blade.php', function(string $s): string {
        $needle = '                            @if($isNonSystem)<small>Smallest UOM: {{ $row->smallest_uom_code ?: \'PCS\' }}</small>@endif';
        $append = "\n                            @if(!\$isNonSystem)\n                                <small><a href=\"{{ route('admin.cycles.summary.sampling-history', [\$cycle, \$row->warehouse_id, \$row->item_id]) }}\">Riwayat Sampling Gudang</a></small>\n                            @endif";
        return onceV9($s, 'cycles.summary.sampling-history', $needle, $needle.$append);
    });
    modifyV9('resources/views/admin/cycles/scan-detail.blade.php', function(string $s): string {
        $needle = "@php(\$detailItem = \$first ?? (\$snapshotItem ?? null))";
        $append = "\n@php(\$photoEvidence = \\Illuminate\\Support\\Facades\\DB::table('stock_opname_manual_photo_evidence')->where('cycle_id', \$cycle->id)->whereIn('scan_transaction_id', \$scans->pluck('id'))->pluck('scan_transaction_id')->flip())";
        $s = onceV9($s, '$photoEvidence =', $needle, $needle.$append);
        $needle2 = '<th class="num">Physical Qty</th></tr>';
        $s = onceV9($s, '<th>Foto Bukti</th>', $needle2, '<th class="num">Physical Qty</th><th>Foto Bukti</th></tr>');
        $needle3 = "</td></tr>@empty<tr><td colspan=\"9\"";
        $s = onceV9($s, "route('admin.cycles.manual-photo'", $needle3, "</td><td>@if(!\$isNonSystem && \$photoEvidence->has(\$scan->id))<a href=\"{{ route('admin.cycles.manual-photo',[\$cycle, \$scan->id]) }}\" target=\"_blank\" rel=\"noopener\">Lihat Foto</a>@else - @endif</td></tr>@empty<tr><td colspan=\"10\"");
        return $s;
    });
    echo "Patch V9 berhasil dipasang. Jalankan php artisan migrate dan php artisan optimize:clear.\n";
} catch (Throwable $e) { fwrite(STDERR, "GAGAL: ".$e->getMessage()."\n"); exit(1); }
