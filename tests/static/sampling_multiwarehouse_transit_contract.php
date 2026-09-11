<?php

$root = dirname(__DIR__, 2);
$checks = [
    "$root/app/Services/ErpStockService.php" => [
        'findItemInTransitWarehouses(',
        "str_contains(\$warehouseIdentity, 'TRANSIT')",
        "(float) \$row['smallest_on_hand'] <= 0",
    ],
    "$root/app/Services/SampleCheckingService.php" => [
        'transit_stock',
        'findItemInTransitWarehouses(',
    ],
    "$root/app/Services/SampleReportService.php" => [
        'erp_warehouse_ids',
        "whereIn('erp_warehouse_id'",
        "'warehouses' => \$items",
    ],
    "$root/resources/views/admin/sampling/index.blade.php" => [
        'name="erp_warehouse_ids[]"',
        'Coverage Gabungan',
        "coverage['warehouses']",
    ],
    "$root/resources/views/gerai/scan.blade.php" => [
        'sample-transit-stock',
        'Stok tersedia di Gudang In Transit',
    ],
    "$root/resources/js/sampling.js" => [
        'renderTransitStock(',
        'data.transit_stock',
    ],
];

foreach ($checks as $file => $needles) {
    $contents = @file_get_contents($file) ?: '';
    foreach ($needles as $needle) {
        if (! str_contains($contents, $needle)) {
            fwrite(STDERR, "FAIL {$file} missing {$needle}\n");
            exit(1);
        }
    }
}

echo "PASS sampling_multiwarehouse_transit_contract\n";
