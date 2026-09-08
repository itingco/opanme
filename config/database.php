<?php

use Illuminate\Support\Str;

$sqlsrvBase = static fn (string $database, string $hostEnv, string $portEnv, string $userEnv, string $passEnv, string $encryptEnv, string $trustEnv): array => [
    'driver' => 'sqlsrv',
    'host' => env($hostEnv, env('ERP_HOST', '127.0.0.1')),
    'port' => env($portEnv, env('ERP_PORT', '1433')),
    'database' => $database,
    'username' => env($userEnv, env('ERP_USERNAME')),
    'password' => env($passEnv, env('ERP_PASSWORD')),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'encrypt' => env($encryptEnv, env('ERP_ENCRYPT', 'yes')),
    'trust_server_certificate' => env($trustEnv, env('ERP_TRUST_SERVER_CERTIFICATE', 'true')),
];

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
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

        'apphub' => $sqlsrvBase(
            env('APPHUB_DATABASE', 'DB_AppHub'),
            'APPHUB_HOST',
            'APPHUB_PORT',
            'APPHUB_USERNAME',
            'APPHUB_PASSWORD',
            'APPHUB_ENCRYPT',
            'APPHUB_TRUST_SERVER_CERTIFICATE'
        ),

        'erp_ingco' => $sqlsrvBase(
            env('ERP_INGCO_DATABASE', 'AS_INGCO'),
            'ERP_HOST',
            'ERP_PORT',
            'ERP_USERNAME',
            'ERP_PASSWORD',
            'ERP_ENCRYPT',
            'ERP_TRUST_SERVER_CERTIFICATE'
        ),

        'erp_smi' => $sqlsrvBase(
            env('ERP_SMI_DATABASE', 'AS_SMI'),
            'ERP_HOST',
            'ERP_PORT',
            'ERP_USERNAME',
            'ERP_PASSWORD',
            'ERP_ENCRYPT',
            'ERP_TRUST_SERVER_CERTIFICATE'
        ),
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'stock-opname')).'-database-'),
        ],
    ],
];
