<?php
return [
    // Route groups. Order matters: first matching pattern is authoritative.
    'menus' => [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'match' => ['admin.dashboard']],
        'gerai_admin' => ['label' => 'Admin / Sampling Gerai', 'route' => 'gerai.admin.home', 'match' => ['gerai.admin.*']],
        'gerai_reports' => ['label' => 'Laporan Sampling Gerai', 'route' => 'admin.sampling.index', 'match' => ['admin.sampling.*']],
        'gerai_checker' => ['label' => 'Checker Gerai', 'route' => 'gerai.checker.daily', 'match' => ['gerai.checker.*']],
        'warehouse_admin' => ['label' => 'Admin / Sampling Gudang', 'route' => 'warehouse.admin.index', 'match' => ['warehouse.admin.*']],
        'warehouse_history' => ['label' => 'History Sampling Gudang', 'route' => 'warehouse.history.index', 'match' => ['warehouse.history.*']],
        'warehouse_checker' => ['label' => 'Checker Sampling Gudang', 'route' => 'warehouse.checker.index', 'match' => ['warehouse.checker.*']],
        'warehouse_legacy' => ['label' => 'Opname Legacy Gudang', 'route' => 'warehouse.legacy.home', 'match' => ['warehouse.legacy.*']],
        'cycles' => ['label' => 'Cycle Opname Legacy', 'route' => 'admin.cycles.index', 'match' => ['admin.cycles.*']],
        'legacy_checker' => ['label' => 'Checker Opname Legacy', 'route' => 'checker.home', 'match' => ['checker.*']],
        'barcodes' => ['label' => 'Master Barcode', 'route' => 'admin.barcodes.index', 'match' => ['admin.barcodes.*']],
        'label' => ['label' => 'Cetak Label QR', 'route' => 'label.index', 'match' => ['label.*']],
        'ratios' => ['label' => 'Master Ratio', 'route' => 'admin.ratios.index', 'match' => ['admin.ratios.*']],
        'users' => ['label' => 'User & Akses', 'route' => 'admin.users.index', 'match' => ['admin.users.*']],
    ],
    'roles' => ['ADMIN', 'ADMIN_GERAI', 'CHECKER_GERAI', 'GERAI', 'ADMIN_GUDANG', 'CHECKER_GUDANG', 'CHECKER'],
    // Default only applies when no saved permission exists yet.
    'defaults' => [
        'ADMIN' => ['dashboard','gerai_admin','gerai_reports','warehouse_admin','warehouse_history','cycles','barcodes','label','ratios','users'],
        'ADMIN_GERAI' => ['gerai_admin'],
        'CHECKER_GERAI' => ['gerai_checker'],
        'GERAI' => ['gerai_checker'],
        'ADMIN_GUDANG' => ['warehouse_admin','warehouse_history','label'],
        'CHECKER_GUDANG' => ['warehouse_checker','warehouse_legacy','label'],
        'CHECKER' => ['legacy_checker'],
    ],
];
