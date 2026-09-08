<?php
$root = dirname(__DIR__, 2);
function mustContain(string $file, string $needle): void { $c=@file_get_contents($file); if ($c===false || !str_contains($c,$needle)) { fwrite(STDERR,"FAIL: {$file} missing {$needle}\n"); exit(1);} }
mustContain("$root/config/database.php", "'apphub'");
mustContain("$root/config/database.php", "APPHUB_DATABASE");
mustContain("$root/app/Models/ItemBarcode.php", "protected \$connection = 'apphub'");
mustContain("$root/app/Models/ItemBarcode.php", "protected \$table = 'itembarcode'");
mustContain("$root/app/Models/UomRatio.php", "protected \$connection = 'apphub'");
mustContain("$root/app/Models/UomRatio.php", "protected \$table = 'itemratio'");
mustContain("$root/database/migrations/2026_09_08_000011_move_masters_to_apphub_and_add_gerai_binding.php", "erp_warehouse_id");
echo "PASS apphub_master_contract\n";
