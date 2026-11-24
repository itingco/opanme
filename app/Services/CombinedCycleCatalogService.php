<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Catalog for combined opname, in the same unit as Smallest On Hand. */
class CombinedCycleCatalogService
{
    public function __construct(private readonly ErpConnectionResolver $resolver)
    {
    }

    public function findItemsByCodes(string $sourceDatabase, array $itemCodes): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn ($code): string => mb_strtoupper(trim((string) $code)), $itemCodes),
            static fn (string $code): bool => $code !== ''
        )));
        if ($codes === []) return [];

        $connection = DB::connection($this->resolver->connectionName($sourceDatabase));
        $result = [];
        foreach (array_chunk($codes, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            // ERP UOM levels descend from largest to smallest. Zero/NULL means unused.
            // Resolve the last populated UOM ID BEFORE joining the UOM master;
            // do not silently fall back to a larger unit if the master is missing.
            $rows = $connection->select(<<<SQL
                SELECT I.ItemID, I.ItemCode, I.ItemName, U.UOMCode
                FROM IC_Items I
                LEFT JOIN IC_UOM U ON U.UOMID = COALESCE(
                    NULLIF(I.UOMID4, 0),
                    NULLIF(I.UOMID3, 0),
                    NULLIF(I.UOMID2, 0),
                    NULLIF(I.UOMID1, 0)
                )
                WHERE UPPER(LTRIM(RTRIM(I.ItemCode))) IN ({$placeholders})
                ORDER BY I.ItemCode
            SQL, $chunk);
            foreach ($rows as $row) {
                $result[] = [
                    'item_id' => (int) $row->ItemID,
                    'item_code' => trim((string) $row->ItemCode),
                    'item_name' => (string) $row->ItemName,
                    'uom_code' => mb_strtoupper(trim((string) ($row->UOMCode ?? ''))),
                ];
            }
        }
        return $result;
    }
}
