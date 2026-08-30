<?php

$root = dirname(__DIR__, 2);
$checks = [
    'User controller supports role filter' => ["app/Http/Controllers/Admin/UserController.php", "input('role'"],
    'User controller supports status filter' => ["app/Http/Controllers/Admin/UserController.php", "input('status'"],
    'User controller supports page size' => ["app/Http/Controllers/Admin/UserController.php", "input('per_page'"],
    'User view has role filter' => ["resources/views/admin/users/index.blade.php", 'name="role"'],
    'User view has status filter' => ["resources/views/admin/users/index.blade.php", 'name="status"'],
    'User view has sortable username column' => ["resources/views/admin/users/index.blade.php", 'data-sort="username"'],
    'Ratio controller supports UOM level filter' => ["app/Http/Controllers/Admin/RatioController.php", "filled('uom_level'"],
    'Ratio controller supports page size' => ["app/Http/Controllers/Admin/RatioController.php", "input('per_page'"],
    'Ratio view has UOM filter' => ["resources/views/admin/ratios/index.blade.php", 'name="uom_level"'],
    'Ratio view has sortable ratio column' => ["resources/views/admin/ratios/index.blade.php", 'data-sort="ratio"'],
];

$failed = [];
foreach ($checks as $label => [$file, $needle]) {
    $contents = file_get_contents($root.'/'.$file);
    if (strpos($contents, $needle) === false) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "FAILED:\n - ".implode("\n - ", $failed)."\n");
    exit(1);
}

echo "Admin master table contract passed.\n";
