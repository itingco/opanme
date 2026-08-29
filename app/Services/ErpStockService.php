<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ErpStockService
{
    public function __construct(private readonly ErpConnectionResolver $resolver)
    {
    }

    public function snapshot(string $sourceDatabase, CarbonInterface|string $perDate): array
    {
        $connection = $this->resolver->connectionName($sourceDatabase);
        $date = $perDate instanceof CarbonInterface ? $perDate->format('Y-m-d') : (string) $perDate;

        $rows = DB::connection($connection)->select(
            'EXEC dbo.USP_Dashboard_SisaStokWarehouse @PerDate = ?',
            [$date]
        );

        return array_map(static function ($row): array {
            $r = (array) $row;

            return [
                'item_id' => (int) $r['ItemID'],
                'item_code' => (string) $r['Item Code'],
                'item_name' => (string) $r['Item Name'],
                'warehouse_id' => (int) $r['WarehouseID'],
                'warehouse_code' => (string) $r['Warehouse Code'],
                'warehouse_name' => (string) $r['Warehouse Name'],
                'smallest_on_hand' => (string) ($r['Smallest On Hand'] ?? '0'),
            ];
        }, $rows);
    }
}
