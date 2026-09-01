<?php
$service = file_get_contents(__DIR__.'/../../app/Services/CycleFinalPdfService.php');
$controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Admin/CycleController.php');
$failed = [];

$checks = [
    'PDF detail table has Detail Override column' => strpos($service, "Detail Override") !== false,
    'Override audit is combined into one text' => strpos($service, "formatOverrideDetail") !== false,
    'PDF has checker signature section' => strpos($service, "CHECKER STOCK OPNAME") !== false,
    'PDF has signature/paraf label' => strpos($service, "Tanda Tangan / Paraf") !== false,
    'PDF has admin approval section' => strpos($service, "DIPERIKSA / DISAHKAN OLEH") !== false,
    'Controller loads checker assignments for report' => strpos($controller, "assignments.checker") !== false,
];

foreach ($checks as $label => $ok) {
    if (!$ok) $failed[] = $label;
}

if ($failed) {
    fwrite(STDERR, "Final PDF signature contract failed:\n- ".implode("\n- ", $failed)."\n");
    exit(1);
}

echo "Final PDF signature contract passed.\n";
