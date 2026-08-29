<?php

namespace App\Services;

use InvalidArgumentException;

class ErpConnectionResolver
{
    public function connectionName(string $sourceDatabase): string
    {
        return match (strtoupper(trim($sourceDatabase))) {
            'AS_INGCO' => 'erp_ingco',
            'AS_SMI' => 'erp_smi',
            default => throw new InvalidArgumentException("Database ERP tidak didukung: {$sourceDatabase}"),
        };
    }
}
