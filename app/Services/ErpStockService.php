<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
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

    public function warehouseSnapshot(string $sourceDatabase, CarbonInterface|string $perDate, int $erpWarehouseId): array
    {
        $date = $perDate instanceof CarbonInterface ? $perDate->format('Y-m-d') : (string) $perDate;
        $key = 'erp_sampling_stock:'.strtoupper($sourceDatabase).':'.$date;

        $all = Cache::remember($key, now()->addSeconds(90), fn () => $this->snapshot($sourceDatabase, $date));

        return array_values(array_filter(
            $all,
            static fn (array $row): bool => (int) $row['warehouse_id'] === $erpWarehouseId
        ));
    }

    public function findItemInWarehouse(
        string $sourceDatabase,
        CarbonInterface|string $perDate,
        int $erpWarehouseId,
        int $itemId
    ): ?array {
        foreach ($this->warehouseSnapshot($sourceDatabase, $perDate, $erpWarehouseId) as $row) {
            if ((int) $row['item_id'] === $itemId) {
                return $row;
            }
        }

        return null;
    }
}
