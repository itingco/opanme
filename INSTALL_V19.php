<?php
/** Run: php INSTALL_V19.php in the project root. */
$root = __DIR__;
if (!is_file($root.'/artisan')) { fwrite(STDERR, "File artisan tidak ada; jalankan installer dari root Laravel.\n"); exit(1); }
$controller = $root.'/app/Http/Controllers/Admin/CycleController.php';
$view = $root.'/resources/views/admin/cycles/summary.blade.php';
$bulkView = $root.'/resources/views/admin/cycles/partials/summary-bulk-tools.blade.php';
$files = [$controller, $view, $bulkView];
foreach ($files as $file) if (!is_file($file)) { fwrite(STDERR,"File tidak ditemukan: {$file}\n"); exit(1); }
if (!is_file($root.'/database/migrations/2026_11_17_200000_create_stock_opname_override_item_details.php')) {
    fwrite(STDERR,"V18 belum lengkap: migration override_item_details tidak ada. Instal V18 dulu.\n"); exit(1);
}
$contents = array_map('file_get_contents', $files);
[$controllerText, $summaryText, $bulkText] = $contents;
if (!str_contains($controllerText, "'override_qty' => \$data['override_qty']")) {
    if (!str_contains($controllerText, 'stock_opname_override_history')) {
        fwrite(STDERR,"Pola saveOverride tidak ditemukan; installer dibatalkan agar file aman.\n"); exit(1);
    }
} else {
    $needle = "            'comment' => ['required', 'string', 'max:2000'],";
    // insert after validation start in saveOverride only
    $at = strpos($controllerText, 'public function saveOverride(');
    $end = strpos($controllerText, 'public function deleteOverride(', $at);
    if ($at === false || $end === false) { fwrite(STDERR,"Method saveOverride/deleteOverride tidak ditemukan.\n"); exit(1); }
    $method = substr($controllerText, $at, $end - $at);
    if (substr_count($method, $needle) !== 1 || substr_count($method, "'override_qty' => \$data['override_qty']") !== 1) {
        fwrite(STDERR,"Struktur saveOverride berbeda; tidak diubah.\n"); exit(1);
    }
    $method = str_replace($needle, $needle."\n            'override_mode' => ['nullable', 'in:add,replace'],", $method);
    $lookup = '            $this->ensureSummaryItemExists($locked, $warehouse->id, $itemId);';
    if (substr_count($method, $lookup) !== 1) { fwrite(STDERR,"Anchor ensureSummaryItemExists berbeda.\n"); exit(1); }
    $method = str_replace($lookup, $lookup.<<<'INSERT'

            $previous = \App\Models\StockOpnameOverride::query()
                ->where('cycle_id', $locked->id)->where('warehouse_id', $warehouse->id)
                ->where('item_id', $itemId)->lockForUpdate()->first();
            $before = $previous?->override_qty;
            $mode = $data['override_mode'] ?? 'replace';
            $finalQty = $mode === 'add'
                ? number_format(round((float) ($before ?? 0) + (float) $data['override_qty'], 4), 4, '.', '')
                : $data['override_qty'];
INSERT, $method);
    $method = str_replace("'override_qty' => \$data['override_qty']", "'override_qty' => \$finalQty", $method);
    $anchor = "            );\n        });\n\n        return back()->with('success', 'Override fisik berhasil disimpan. Hasil scan asli tidak diubah.');";
    $replacement = <<<'NEW'
            );
            DB::table('stock_opname_override_history')->insert([
                'cycle_id' => $locked->id, 'warehouse_id' => $warehouse->id, 'item_id' => $itemId,
                'action' => $mode, 'source' => 'single', 'batch_id' => null,
                'previous_qty' => $before, 'input_qty' => $data['override_qty'], 'result_qty' => $finalQty,
                'comment' => trim($data['comment']), 'updated_by' => $request->user()->id, 'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Override berhasil disimpan. Hasil scan asli tidak diubah.');
NEW;
    if (substr_count($method, $anchor) !== 1) { fwrite(STDERR,"Pola penyimpanan override tidak ditemukan.\n"); exit(1); }
    $method = str_replace($anchor, $replacement, $method);
    $controllerText = substr($controllerText, 0, $at).$method.substr($controllerText, $end);
}
if (!str_contains($summaryText, 'name="override_mode"')) {
    $needle = '<label>Qty Override<input type="number" name="override_qty"';
    if (substr_count($summaryText, $needle) !== 1) { fwrite(STDERR,"Field override satuan tidak ditemukan di summary; dibatalkan.\n"); exit(1); }
    $select = <<<'VIEW'
<label>Metode Override
    <select name="override_mode" required>
        <option value="add" selected>Tambah Qty ke override sebelumnya</option>
        <option value="replace">Ganti Qty override sebelumnya</option>
    </select>
    <small>Contoh: override lama 10, input 5 = 15 pada mode Tambah.</small>
</label>
                                                
VIEW;
    $summaryText = str_replace($needle, $select.$needle, $summaryText);
}
if (!str_contains($bulkText, 'name="override_mode"')) {
    $needle = '@csrf';
    if (substr_count($bulkText, $needle) !== 1) { fwrite(STDERR,"Form bulk tidak sesuai.\n"); exit(1); }
    $bulkText = str_replace($needle, $needle.<<<'VIEW'

                <fieldset style="border:1px solid #cbd5e1;border-radius:8px;padding:12px">
                    <legend><strong>Metode Override</strong></legend>
                    <label style="display:flex;align-items:center;gap:8px"><input type="radio" name="override_mode" value="add" checked required> <span><strong>Tambah Qty</strong> — jumlahkan dengan override sebelumnya</span></label>
                    <label style="display:flex;align-items:center;gap:8px"><input type="radio" name="override_mode" value="replace"> <span><strong>Ganti Qty</strong> — timpa override sebelumnya</span></label>
                    <small>Contoh: override lama 10, input 5 = 15 jika memilih Tambah. Scan fisik asli tidak berubah.</small>
                </fieldset>
VIEW, $bulkText);
}
$bulkText = str_replace('Maksimal 500 barang', 'Maksimal 2.000 barang', $bulkText);
$updated = [$controllerText, $summaryText, $bulkText];
foreach ($files as $i => $file) {
    if ($updated[$i] === $contents[$i]) { echo 'Sudah sesuai: '.substr($file, strlen($root)+1)."\n"; continue; }
    $backup = $file.'.before-v19-'.date('Ymd-His');
    if (!copy($file, $backup)) { fwrite(STDERR,"Gagal backup: $file\n"); exit(1); }
    if (file_put_contents($file, $updated[$i], LOCK_EX) === false) { fwrite(STDERR,"Gagal menulis: $file\n"); exit(1); }
    echo 'Updated: '.substr($file, strlen($root)+1)."\n";
}
echo "Selesai. Jalankan php artisan migrate dan php artisan optimize:clear.\n";
