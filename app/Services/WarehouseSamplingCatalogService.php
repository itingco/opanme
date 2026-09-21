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
}
