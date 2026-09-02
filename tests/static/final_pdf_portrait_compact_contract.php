<?php
$service = file_get_contents(__DIR__.'/../../app/Services/CycleFinalPdfService.php');
$failed = [];

$checks = [
    'PDF uses A4 portrait width' => strpos($service, 'private const PAGE_WIDTH = 595.0') !== false,
    'PDF uses A4 portrait height' => strpos($service, 'private const PAGE_HEIGHT = 842.0') !== false,
    'Compact ERP group is present' => strpos($service, 'ERP O/C/M') !== false,
    'Compact physical group is present' => strpos($service, 'Fisik S/O/F') !== false,
    'Rows are grouped by warehouse' => strpos($service, 'groupRowsByWarehouse') !== false,
    'Summary is merged into report detail flow' => strpos($service, 'drawCompactSummary') !== false,
    'Override audit stays combined' => strpos($service, 'formatOverrideDetail') !== false,
    'Checker signature section remains' => strpos($service, 'PENGESAHAN CHECKER STOCK OPNAME') !== false,
];

foreach ($checks as $label => $ok) {
    if (! $ok) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "Final PDF portrait compact contract failed:\n- ".implode("\n- ", $failed)."\n");
    exit(1);
}

echo "Final PDF portrait compact contract passed.\n";
