<?php

$root = dirname(__DIR__, 2);
$view = file_get_contents($root.'/resources/views/admin/sampling/index.blade.php');
$css = file_get_contents($root.'/resources/css/app.css');

$checks = [
    'sampling-filter-panel class' => str_contains($view, 'sampling-filter-panel'),
    'period group' => str_contains($view, 'sampling-period-group'),
    'three-column primary grid' => str_contains($view, 'sampling-filter-grid'),
    'searchable warehouse control' => str_contains($view, 'data-searchable-select="warehouse"'),
    'searchable user control' => str_contains($view, 'data-searchable-select="user"'),
    'filter submit label' => str_contains($view, 'Terapkan Filter'),
    'sampling filter css' => str_contains($css, '.sampling-filter-panel'),
    'responsive sampling filter css' => str_contains($css, '@media(max-width:760px)') && str_contains($css, '.sampling-filter-grid'),
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) $failed[] = $name;
}

if ($failed) {
    fwrite(STDERR, "sampling filter ui contract: FAIL\n- ".implode("\n- ", $failed)."\n");
    exit(1);
}

echo "sampling filter ui contract: PASS\n";
