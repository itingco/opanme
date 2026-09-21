<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GoodTransferSourceService
{
    public function connectionForCompany(string $company): string
    {
        return match (strtoupper(trim($company))) {
            'INGCO' => 'as_ingco',
            'SMI' => 'as_smi',
            default => throw new InvalidArgumentException(
                'Company harus INGCO atau SMI.'
            ),
        };
    }

    public function databaseForCompany(string $company): string
    {
        return match (strtoupper(trim($company))) {
            'INGCO' => (string) config('database.connections.as_ingco.database'),
            'SMI' => (string) config('database.connections.as_smi.database'),
            default => throw new InvalidArgumentException(
                'Company harus INGCO atau SMI.'
            ),
        };
    }

    /**
     * Load one Good Transfer / IC_Mutations document together with UOM code.
     *
     * UOM is resolved from MutationDetails.UOMLevel -> IC_Items.UOMID1..4
     * -> IC_UOM.UOMCode, matching the invoice UOM logic already used by
     * the project.
     */
    public function getTransfer(
        string $company,
        string $mutationNumber
    ): Collection {
        $connection = $this->connectionForCompany($company);

        $sql = <<<'SQL'
SELECT
    MUT.MutationID,
    ROW_NUMBER() OVER (
        ORDER BY ITEM.ItemCode, DET.UOMLevel, DET.Quantity
    ) AS MutationDetailID,
    MUT.MutationNumber,
    MUT.MutationDate,
    ASAL.Name AS SourceWarehouseName,
    TUJUAN.Name AS DestinationWarehouseName,
    ITEM.ItemCode,
    ITEM.ItemName,
    UOM.UOMCode,
    DET.Quantity,
    MUT.CheckedBy,
    MUT.CheckedDateTime
FROM IC_Mutations MUT
LEFT JOIN IC_MutationDetails DET
    ON DET.MutationID = MUT.MutationID
LEFT JOIN IC_Warehouses ASAL
    ON ASAL.WarehouseID = MUT.SourceWarehouseID
LEFT JOIN IC_Warehouses TUJUAN
    ON TUJUAN.WarehouseID = MUT.DestinationWarehouseID
LEFT JOIN IC_Items ITEM
    ON ITEM.ItemID = DET.ItemID
LEFT JOIN IC_UOM UOM
    ON UOM.UOMID =
        CASE DET.UOMLevel
            WHEN 1 THEN ITEM.UOMID1
            WHEN 2 THEN ITEM.UOMID2
            WHEN 3 THEN ITEM.UOMID3
            WHEN 4 THEN ITEM.UOMID4
        END
WHERE MUT.MutationNumber = ?
ORDER BY ITEM.ItemCode, DET.UOMLevel, DET.Quantity;
SQL;

        return collect(
            DB::connection($connection)->select($sql, [$mutationNumber])
        );
    }

    public function markChecked(
        string $company,
        int $mutationId,
        string $checkerName
    ): void {
        $connection = $this->connectionForCompany($company);
        $safeCheckerName = mb_substr($checkerName, 0, 30);

        $affected = DB::connection($connection)
            ->table('IC_Mutations')
            ->where('MutationID', $mutationId)
            ->update([
                'CheckedBy' => $safeCheckerName,
                'CheckedDateTime' => now(),
            ]);

        if ($affected < 1) {
            throw new \RuntimeException(
                'IC_Mutations gagal di-update atau MutationID tidak ditemukan.'
            );
        }
    }
}
