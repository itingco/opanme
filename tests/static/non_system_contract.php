<?php

$root = dirname(__DIR__, 2);
$checks = [
    'discovered items migration exists' => ['database/migrations/2026_09_02_000007_create_discovered_stock_opname_tables.php', 'stock_opname_discovered_items'],
    'discovered scan table exists' => ['database/migrations/2026_09_02_000007_create_discovered_stock_opname_tables.php', 'discovered_scan_transactions'],
    'discovered override table exists' => ['database/migrations/2026_09_02_000007_create_discovered_stock_opname_tables.php', 'stock_opname_discovered_overrides'],
    'discovered item model exists' => ['app/Models/StockOpnameDiscoveredItem.php', 'class StockOpnameDiscoveredItem'],
    'discovered scan model exists' => ['app/Models/DiscoveredScanTransaction.php', 'class DiscoveredScanTransaction'],
    'discovered override model exists' => ['app/Models/StockOpnameDiscoveredOverride.php', 'class StockOpnameDiscoveredOverride'],
    'unknown ERP barcode triggers non-system flow' => ['app/Services/ScanService.php', "'requires_non_system' => true"],
    'non-system scan endpoint exists' => ['routes/web.php', 'scan/non-system'],
    'non-system scanner form exists' => ['resources/views/checker/scan.blade.php', 'non-system-item-form'],
    'admin discovered route exists' => ['routes/web.php', 'non-system/override'],
    'summary labels non-system' => ['resources/views/admin/cycles/summary.blade.php', 'NON-SYSTEM'],
    'summary service exposes row type' => ['app/Services/CycleSummaryService.php', 'row_type'],
    'excel exports row type' => ['app/Services/CycleSummaryExcelService.php', 'Row Type'],
    'final pdf labels non-system' => ['app/Services/CycleFinalPdfService.php', 'NON-SYSTEM'],
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

echo "Non-System contract passed.\n";
