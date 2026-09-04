<?php

$root = dirname(__DIR__, 2);

$checks = [
    'ratio model uses item_code/uom_code only' => ['app/Models/UomRatio.php', "protected \$fillable = ['item_code','uom_code','ratio']"],
    'ratio import expects itemcode header' => ['app/Services/RatioImportService.php', "['itemcode', 'item code', 'item_code', 'item']"],
    'ratio import expects uom header' => ['app/Services/RatioImportService.php', "['uom', 'uom code', 'uom_code']"],
    'ratio import upserts by item and uom' => ['app/Services/RatioImportService.php', "['item_code', 'uom_code']"],
    'scan ratio lookup uses item code' => ['app/Services/ScanService.php', "->where('item_code', strtoupper(trim((string) \$item['item_code'])))"],
    'scan ratio lookup uses uom code' => ['app/Services/ScanService.php', "->where('uom_code', strtoupper(trim((string) \$item['uom_code'])))"],
    'global ratio migration exists' => ['database/migrations/2026_09_04_000008_make_uom_ratios_global.php', "\$t->unique(['item_code', 'uom_code'], 'uom_ratios_item_uom_unique')"],
    'ratio view shows global scope' => ['resources/views/admin/ratios/index.blade.php', 'Berlaku untuk AS_INGCO dan AS_SMI'],
    'template is included' => ['resources/templates/uom-ratio-import-template.xlsx', null],
];

$failed = [];
foreach ($checks as $label => [$file, $needle]) {
    $path = $root.'/'.$file;
    if (! is_file($path)) {
        $failed[] = $label.' (file missing)';
        continue;
    }
    if ($needle !== null && strpos((string) file_get_contents($path), $needle) === false) {
        $failed[] = $label.' (content missing)';
    }
}

if ($failed) {
    fwrite(STDERR, "FAILED:\n - ".implode("\n - ", $failed)."\n");
    exit(1);
}

echo "Global UOM ratio contract passed.\n";
