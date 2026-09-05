<?php

$root = dirname(__DIR__, 2);
$checks = [
    'ratio export service exists' => ['app/Services/RatioExportService.php', 'class RatioExportService'],
    'ratio export headers' => ['app/Services/RatioExportService.php', "['ItemCode', 'UOM', 'Ratio']"],
    'ratio controller export method' => ['app/Http/Controllers/Admin/RatioController.php', 'function export(RatioExportService $exporter)'],
    'ratio export route' => ['routes/web.php', "name('ratios.export')"],
    'ratio export button' => ['resources/views/admin/ratios/index.blade.php', "route('admin.ratios.export')"],
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

echo "Ratio export contract passed.\n";
