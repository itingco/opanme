<?php

$root = dirname(__DIR__, 2);
$checks = [
    'barcode migration exists' => ['database/migrations/2026_09_04_000010_create_item_barcodes_table.php', "->string('barcode', 150)->unique()"],
    'barcode model exists' => ['app/Models/ItemBarcode.php', 'class ItemBarcode'],
    'barcode import exists' => ['app/Services/BarcodeImportService.php', "'barcode' => \$barcode"],
    'barcode admin route exists' => ['routes/web.php', "name('barcodes.index')"],
    'barcode export route exists' => ['routes/web.php', "name('barcodes.export')"],
    'scan uses local barcode' => ['app/Services/ScanService.php', "ItemBarcode::query()"],
    'scan resolves ERP by item/uom' => ['app/Services/ScanService.php', 'findItemByCodeAndUom'],
    'ERP resolver exists' => ['app/Services/ErpCatalogService.php', 'public function findItemByCodeAndUom'],
    'barcode view exists' => ['resources/views/admin/barcodes/index.blade.php', 'Master Barcode'],
];

$failed = [];
foreach ($checks as $label => [$file, $needle]) {
    $path = $root.'/'.$file;
    if (! is_file($path) || strpos((string) file_get_contents($path), $needle) === false) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "FAILED:\n - ".implode("\n - ", $failed)."\n");
    exit(1);
}

echo "Local barcode contract passed.\n";
