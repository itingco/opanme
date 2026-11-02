<?php

return [
    // Menu utama. CHECKER_GERAI menjadi satu tipe dengan checker legacy/cycle lama.
    // Akun GERAI/CHECKER lama tetap kompatibel, sedangkan modul opname gudang baru
    // (ADMIN_GUDANG / CHECKER_GUDANG) tidak diubah alurnya.
    'menus' => [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'match' => ['admin.dashboard']],
        'gerai_admin' => ['label' => 'Admin Gerai', 'route' => 'gerai.admin.home', 'match' => ['gerai.admin.*']],
        'gerai_reports' => ['label' => 'Laporan Gerai', 'route' => 'admin.sampling.index', 'match' => ['admin.sampling.*']],
        'gerai_checker' => ['label' => 'Checker Gerai', 'route' => 'gerai.checker.daily', 'match' => ['gerai.checker.*']],
        'warehouse_admin' => ['label' => 'Admin Gudang', 'route' => 'warehouse.admin.index', 'match' => ['warehouse.admin.*']],
        'warehouse_history' => ['label' => 'History Gudang', 'route' => 'warehouse.history.index', 'match' => ['warehouse.history.*']],
        'warehouse_checker' => ['label' => 'Checker Gudang', 'route' => 'warehouse.checker.index', 'match' => ['warehouse.checker.*']],
        // Modul opname gudang lama sengaja tetap dipertahankan apa adanya.
        'warehouse_legacy' => ['label' => 'Opname Gudang', 'route' => 'warehouse.legacy.home', 'match' => ['warehouse.legacy.*']],
        'cycles' => ['label' => 'Cycle Opname', 'route' => 'admin.cycles.index', 'match' => ['admin.cycles.*']],
        'legacy_checker' => ['label' => 'Cycle Opname (Legacy)', 'route' => 'checker.home', 'match' => ['checker.*']],
        'barcodes' => ['label' => 'Master Barcode', 'route' => 'admin.barcodes.index', 'match' => ['admin.barcodes.*']],
        'label' => ['label' => 'Cetak Label', 'route' => 'label.index', 'match' => ['label.*']],
        'ratios' => ['label' => 'Master Ratio', 'route' => 'admin.ratios.index', 'match' => ['admin.ratios.*']],
        'users' => ['label' => 'User & Akses', 'route' => 'admin.users.index', 'match' => ['admin.users.*']],
    ],

    // Hanya 5 role yang aktif di pengaturan hak akses.
    'roles' => [
        'ADMIN',
        'ADMIN_GERAI',
        'ADMIN_GUDANG',
        'CHECKER_GERAI',
        'CHECKER_GUDANG',
    ],

    'defaults' => [
        'ADMIN' => [
            'dashboard','gerai_admin','gerai_reports','warehouse_admin','warehouse_history',
            'cycles','barcodes','label','ratios','users',
        ],
        'ADMIN_GERAI' => ['gerai_admin','gerai_reports'],
        'CHECKER_GERAI' => ['gerai_checker','legacy_checker'],
        'ADMIN_GUDANG' => ['warehouse_admin','warehouse_history','label'],
        'CHECKER_GUDANG' => ['warehouse_checker','warehouse_legacy','label'],
    ],
];
