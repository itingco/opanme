<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use UnexpectedValueException;

class ErpCatalogService
{
    public function __construct(private readonly ErpConnectionResolver $resolver)
    {
    }

    public function warehouses(string $sourceDatabase): array
    {
        $connection = $this->resolver->connectionName($sourceDatabase);

        $rows = DB::connection($connection)->select(<<<'SQL'
            SELECT WarehouseID, WarehouseCode, Name
            FROM IC_Warehouses WITH (NOLOCK)
            WHERE Disabled = 0
            ORDER BY DisplaySequence
        SQL);

        return array_map(static fn ($row) => [
            'warehouse_id' => (int) $row->WarehouseID,
            'warehouse_code' => (string) $row->WarehouseCode,
            'warehouse_name' => (string) $row->Name,
        ], $rows);
    }

    public function findBarcodes(string $sourceDatabase, array $barcodes): array
    {
        $barcodes = array_values(array_unique(array_filter(array_map(
            static fn ($barcode) => trim((string) $barcode),
            $barcodes
        ), static fn ($barcode) => $barcode !== '')));

        if ($barcodes === []) {
            return ['items' => [], 'ambiguous' => []];
        }

        $connection = $this->resolver->connectionName($sourceDatabase);
        $items = [];
        $ambiguous = [];

        foreach (array_chunk($barcodes, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = <<<SQL
                SELECT
                    A.AliasCode,
                    A.ItemID,
                    A.UOMLevel,
                    I.ItemCode,
                    I.ItemName,
                    U.UOMCode
                FROM IC_Aliases A WITH (NOLOCK)
                LEFT JOIN IC_Items I WITH (NOLOCK)
                    ON I.ItemID = A.ItemID
                LEFT JOIN IC_UOM U WITH (NOLOCK)
                    ON U.UOMID = CASE A.UOMLevel
                        WHEN 1 THEN I.UOMID1
                        WHEN 2 THEN I.UOMID2
                        WHEN 3 THEN I.UOMID3
                        WHEN 4 THEN I.UOMID4
                    END
                WHERE A.AliasType = 'Barcode'
                  AND A.AliasCode IN ({$placeholders})
                ORDER BY A.AliasCode, I.ItemCode
            SQL;

            $rows = DB::connection($connection)->select($sql, $chunk);

            foreach ($rows as $row) {
                $aliasCode = (string) $row->AliasCode;

                if (isset($ambiguous[$aliasCode])) {
                    continue;
                }

                if (isset($items[$aliasCode])) {
                    unset($items[$aliasCode]);
                    $ambiguous[$aliasCode] = true;
                    continue;
                }

                $items[$aliasCode] = [
                    'alias_code' => $aliasCode,
                    'item_id' => (int) $row->ItemID,
                    'item_code' => (string) $row->ItemCode,
                    'item_name' => (string) $row->ItemName,
                    'uom_level' => (int) $row->UOMLevel,
                    'uom_code' => (string) ($row->UOMCode ?? 'UNKNOWN'),
                ];
            }
        }

        return ['items' => $items, 'ambiguous' => array_keys($ambiguous)];
    }

    public function findBarcode(string $sourceDatabase, string $barcode): ?array
    {
        $barcode = trim($barcode);
        $cacheKey = 'erp_barcode:'.strtoupper($sourceDatabase).':'.sha1($barcode);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $connection = $this->resolver->connectionName($sourceDatabase);
        $rows = DB::connection($connection)->select(<<<'SQL'
            SELECT TOP (2)
                A.AliasCode,
                A.ItemID,
                A.UOMLevel,
                I.ItemCode,
                I.ItemName,
                U.UOMCode
            FROM IC_Aliases A WITH (NOLOCK)
            LEFT JOIN IC_Items I WITH (NOLOCK)
                ON I.ItemID = A.ItemID
            LEFT JOIN IC_UOM U WITH (NOLOCK)
                ON U.UOMID = CASE A.UOMLevel
                    WHEN 1 THEN I.UOMID1
                    WHEN 2 THEN I.UOMID2
                    WHEN 3 THEN I.UOMID3
                    WHEN 4 THEN I.UOMID4
                END
            WHERE A.AliasType = 'Barcode'
              AND A.AliasCode = ?
            ORDER BY I.ItemCode
        SQL, [$barcode]);

        if (count($rows) === 0) {
            return null;
        }

        if (count($rows) > 1) {
            throw new UnexpectedValueException('Barcode ditemukan pada lebih dari satu data item. Hubungi Admin.');
        }

        $row = $rows[0];

        $item = [
            'alias_code' => (string) $row->AliasCode,
            'item_id' => (int) $row->ItemID,
            'item_code' => (string) $row->ItemCode,
            'item_name' => (string) $row->ItemName,
            'uom_level' => (int) $row->UOMLevel,
            'uom_code' => (string) ($row->UOMCode ?? 'UNKNOWN'),
        ];

        Cache::put($cacheKey, $item, now()->addHours(8));

        return $item;
    }
}
