<?php

$root = dirname(__DIR__, 2);
$checks = [
    'Cycle model defines FINALIZED status' => ['app/Models/StockOpnameCycle.php', "STATUS_FINALIZED='FINALIZED'"],
    'Cycle model stores finalized_at' => ['app/Models/StockOpnameCycle.php', "'finalized_at'"],
    'Cycle model stores finalized_by' => ['app/Models/StockOpnameCycle.php', "'finalized_by'"],
    'Finalize route exists' => ['routes/web.php', "cycles/{cycle}/finalize"],
    'Final report route exists' => ['routes/web.php', "cycles/{cycle}/final-report"],
    'Controller has finalize action' => ['app/Http/Controllers/Admin/CycleController.php', 'function finalize('],
    'Controller has final report action' => ['app/Http/Controllers/Admin/CycleController.php', 'function finalReport('],
    'Finalization requires closing snapshot' => ['app/Http/Controllers/Admin/CycleController.php', 'closing_snapshot_at'],
    'PDF service exists' => ['app/Services/CycleFinalPdfService.php', 'class CycleFinalPdfService'],
    'Summary shows final PDF action' => ['resources/views/admin/cycles/summary.blade.php', 'cycles.final-report'],
    'Summary shows finalize action' => ['resources/views/admin/cycles/summary.blade.php', 'cycles.finalize'],
    'Finalized status has dedicated styling' => ['resources/css/app.css', '.status.finalized'],
];

$failed = [];
foreach ($checks as $label => [$file, $needle]) {
    $path = $root.'/'.$file;
    if (!is_file($path) || strpos(file_get_contents($path), $needle) === false) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "FAILED:\n - ".implode("\n - ", $failed)."\n");
    exit(1);
}

echo "Finalized cycle contract passed.\n";
