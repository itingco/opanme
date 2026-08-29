<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'stock_opname'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],
        'erp_ingco' => [
            'driver' => 'sqlsrv',
            'host' => env('ERP_HOST', '127.0.0.1'),
            'port' => env('ERP_PORT', '1433'),
            'database' => env('ERP_INGCO_DATABASE', 'AS_INGCO'),
            'username' => env('ERP_USERNAME'),
            'password' => env('ERP_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('ERP_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('ERP_TRUST_SERVER_CERTIFICATE', 'true'),
        ],
        'erp_smi' => [
            'driver' => 'sqlsrv',
            'host' => env('ERP_HOST', '127.0.0.1'),
            'port' => env('ERP_PORT', '1433'),
            'database' => env('ERP_SMI_DATABASE', 'AS_SMI'),
            'username' => env('ERP_USERNAME'),
            'password' => env('ERP_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('ERP_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('ERP_TRUST_SERVER_CERTIFICATE', 'true'),
        ],
    ],
    'migrations' => 'migrations',
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => ['cluster' => env('REDIS_CLUSTER', 'redis'), 'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'stock-opname')).'-database-')],
    ],
];
