<?php

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root.'/app/Http/Controllers/Admin/CycleController.php');
$snapshot = file_get_contents($root.'/app/Services/CycleSnapshotService.php');
$failed = [];

if (substr_count($controller, 'lockForUpdate()') < 3) {
    $failed[] = 'Finalize, save override, and delete override must share row locks.';
}
if (strpos($controller, "Cycle sudah FINALIZED. Override terkunci permanen") === false) {
    $failed[] = 'Override must explicitly reject FINALIZED cycles.';
}
if (strpos($controller, "->where('status', StockOpnameCycle::STATUS_CLOSED)") === false) {
    $failed[] = 'Retry closing updates must be conditional on CLOSED status.';
}
if (strpos($snapshot, 'lockForUpdate()') === false || strpos($snapshot, 'STATUS_CLOSED') === false) {
    $failed[] = 'Closing snapshot writes must lock and re-check CLOSED status.';
}

if ($failed) {
    fwrite(STDERR, "FAILED:\n - ".implode("\n - ", $failed)."\n");
    exit(1);
}

echo "Finalized lock contract passed.\n";
