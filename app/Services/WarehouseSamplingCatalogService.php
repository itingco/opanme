<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WarehouseSamplingCatalogService
{
    public function __construct(private readonly ErpConnectionResolver $resolver)
    {
    }

    public function searchItems(string $sourceDatabase, string $query, int $limit = 30): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $connection = $this->resolver->connectionName($sourceDatabase);
        $term = '%'.strtoupper($query).'%';

        $rows = DB::connection($connection)->select(<<<SQL
            SELECT TOP ({$limit})
                I.ItemID,
                I.ItemCode,
                I.ItemName,
                U.UOMCode
            FROM IC_Items I WITH (NOLOCK)
            LEFT JOIN IC_UOM U WITH (NOLOCK) ON U.UOMID = I.UOMID1
            WHERE UPPER(LTRIM(RTRIM(I.ItemCode))) LIKE ?
               OR UPPER(LTRIM(RTRIM(I.ItemName))) LIKE ?
            ORDER BY I.ItemCode
        SQL, [$term, $term]);

        return array_map(static fn ($row): array => [
            'item_id' => (int) $row->ItemID,
            'item_code' => (string) $row->ItemCode,
            'item_name' => (string) $row->ItemName,
            'uom_code' => (string) ($row->UOMCode ?? 'PCS'),
        ], $rows);
    }

    /**
     * @return list<array{item_id:int,item_code:string,item_name:string,uom_code:string}>
     */
    public function findItems(string $sourceDatabase, array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $itemIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($itemIds === []) {
            return [];
        }

        $connection = $this->resolver->connectionName($sourceDatabase);
        $result = [];

        foreach (array_chunk($itemIds, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = DB::connection($connection)->select(<<<SQL
                SELECT
                    I.ItemID,
                    I.ItemCode,
                    I.ItemName,
                    U.UOMCode
                FROM IC_Items I WITH (NOLOCK)
                LEFT JOIN IC_UOM U WITH (NOLOCK) ON U.UOMID = I.UOMID1
                WHERE I.ItemID IN ({$placeholders})
                ORDER BY I.ItemCode
            SQL, $chunk);

            foreach ($rows as $row) {
                $result[] = [
                    'item_id' => (int) $row->ItemID,
                    'item_code' => (string) $row->ItemCode,
                    'item_name' => (string) $row->ItemName,
                    'uom_code' => (string) ($row->UOMCode ?? 'PCS'),
                ];
            }
        }

        return $result;
    }

    public function findItem(string $sourceDatabase, int $itemId): ?array
    {
        $connection = $this->resolver->connectionName($sourceDatabase);
        $row = DB::connection($connection)->selectOne(<<<'SQL'
            SELECT
                I.ItemID,
                I.ItemCode,
                I.ItemName,
                U.UOMCode
            FROM IC_Items I WITH (NOLOCK)
            LEFT JOIN IC_UOM U WITH (NOLOCK) ON U.UOMID = I.UOMID1
            WHERE I.ItemID = ?
        SQL, [$itemId]);

        if (! $row) {
            return null;
        }

        return [
            'item_id' => (int) $row->ItemID,
            'item_code' => (string) $row->ItemCode,
            'item_name' => (string) $row->ItemName,
            'uom_code' => (string) ($row->UOMCode ?? 'PCS'),
        ];
    }
    /**
     * @return list<array{item_id:int,item_code:string,item_name:string,uom_code:string}>
     */
    public function findItemsByCodes(string $sourceDatabase, array $itemCodes): array
    {
        $itemCodes = array_values(array_unique(array_filter(
            array_map(static fn ($code) => strtoupper(trim((string) $code)), $itemCodes),
            static fn (string $code): bool => $code !== ''
        )));

        if ($itemCodes === []) {
            return [];
        }

        $connection = $this->resolver->connectionName($sourceDatabase);
        $result = [];

        foreach (array_chunk($itemCodes, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = DB::connection($connection)->select(<<<SQL
                SELECT
                    I.ItemID,
                    I.ItemCode,
                    I.ItemName,
                    U.UOMCode
                FROM IC_Items I WITH (NOLOCK)
                LEFT JOIN IC_UOM U WITH (NOLOCK) ON U.UOMID = I.UOMID1
                WHERE UPPER(LTRIM(RTRIM(I.ItemCode))) IN ({$placeholders})
                ORDER BY I.ItemCode
            SQL, $chunk);

            foreach ($rows as $row) {
                $result[] = [
                    'item_id' => (int) $row->ItemID,
                    'item_code' => (string) $row->ItemCode,
                    'item_name' => (string) $row->ItemName,
                    'uom_code' => (string) ($row->UOMCode ?? 'PCS'),
                ];
            }
        }

        return $result;
    }

}
