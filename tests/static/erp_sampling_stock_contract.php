<?php
$root = dirname(__DIR__, 2);
$c = @file_get_contents("$root/app/Services/ErpStockService.php") ?: '';
foreach (['warehouseSnapshot(', 'findItemInWarehouse(', 'Cache::remember'] as $n) if (!str_contains($c,$n)) { fwrite(STDERR,"FAIL missing $n\n"); exit(1); }
echo "PASS erp_sampling_stock_contract\n";
