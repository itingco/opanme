<?php
/**
 * Stock Opname V18. Run from root Laravel: php INSTALL_V18.php
 * Only modifies CycleSummaryService and summary bulk form, backs up originals.
 */
$root = __DIR__;
if (!is_file($root.'/artisan')) { fwrite(STDERR,"Jalankan installer di root Laravel (sejajar artisan).\n"); exit(1); }
$summary = $root.'/app/Services/CycleSummaryService.php';
$view = $root.'/resources/views/admin/cycles/partials/summary-bulk-tools.blade.php';
if (!is_file($summary) || !is_file($view)) { fwrite(STDERR,"File SummaryService atau form massal belum ditemukan. Instalasi dibatalkan.\n"); exit(1); }
$source = file_get_contents($summary);
if (strpos($source, "stock_opname_override_item_details") === false) {
    $old = <<<'OLD'
        $keys = $snapshotKeys->union($scanKeys);
OLD;
    $new = <<<'NEW'
        $overrideKeys = DB::table('stock_opname_overrides')
            ->select('warehouse_id', 'item_id')
            ->where('cycle_id', $cycle->id);

        $keys = $snapshotKeys->union($scanKeys)->union($overrideKeys);
NEW;
    if (substr_count($source, $old) !== 1) { fwrite(STDERR,"Struktur CycleSummaryService berbeda pada bagian keys. Tidak ada file diubah.\n"); exit(1); }
    $source = str_replace($old, $new, $source);
    $old = <<<'OLD'
            ->leftJoin('users as override_user', 'override_user.id', '=', 'so.updated_by')
OLD;
    $new = <<<'NEW'
            ->leftJoin('stock_opname_override_item_details as oid', function ($join) use ($cycle) {
                $join->on('oid.warehouse_id', '=', 'k.warehouse_id')
                    ->on('oid.item_id', '=', 'k.item_id')
                    ->where('oid.cycle_id', '=', $cycle->id);
            })
            ->leftJoin('users as override_user', 'override_user.id', '=', 'so.updated_by')
NEW;
    if (substr_count($source, $old) !== 1) { fwrite(STDERR,"Struktur join user berbeda; tidak ada file diubah.\n"); exit(1); }
    $source = str_replace($old, $new, $source);
    $old = <<<'OLD'
                COALESCE(ss.item_code, sa.item_code) AS item_code,
                COALESCE(ss.item_name, sa.item_name) AS item_name,
OLD;
    $new = <<<'NEW'
                COALESCE(ss.item_code, sa.item_code, oid.item_code) AS item_code,
                COALESCE(ss.item_name, sa.item_name, oid.item_name) AS item_name,
NEW;
    if (substr_count($source, $old) !== 1) { fwrite(STDERR,"Struktur nama item berbeda; tidak ada file diubah.\n"); exit(1); }
    $source = str_replace($old, $new, $source);
}
$viewData = file_get_contents($view);
$viewData = str_replace('Maksimal 500 barang', 'Maksimal 2.000 barang', $viewData);
// Write only after every replacement has been validated.
foreach ([$summary => $source, $view => $viewData] as $file => $content) {
    if (file_get_contents($file) === $content) { echo "Sudah sesuai: ".substr($file, strlen($root)+1)."\n"; continue; }
    $backup = $file.'.before-v18-'.date('Ymd-His');
    if (!copy($file, $backup)) { fwrite(STDERR,"Gagal backup: $file\n"); exit(1); }
    if (file_put_contents($file, $content, LOCK_EX) === false) { fwrite(STDERR,"Gagal mengubah $file\n"); exit(1); }
    echo "UPDATED: ".substr($file, strlen($root)+1)."\n";
}
echo "Selesai. Jalankan php artisan migrate lalu php artisan optimize:clear.\n";
