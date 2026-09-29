<?php

namespace App\Services;

use App\Models\SampleCycleItem;
use App\Models\SampleCycleItemStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseSamplingPendingTransferService
{
    public function __construct(
        private readonly ErpConnectionResolver $resolver,
        private readonly BarcodeService $barcodeService
    ) {
    }

    /**
     * Ambil Good Transfer yang destination-nya adalah gudang sampling dan belum diterima.
     * ReceivedBy IS NULL mengikuti rule ERP yang diberikan user.
     * Qty Good Transfer dikonversi ke smallest UOM agar sebanding dengan snapshot stok.
     *
     * @param Collection<int, SampleCycleItem> $items
     * @return array<int, array{pending_transfer_qty:float,validation_system_qty:float,transfers:array<int,array<string,mixed>>,conversion_warnings:array<int,string>}>
     */
    public function forItems(Collection $items, array $salesAdjustments = []): array
    {
        $requests = [];
        $defaults = [];

        foreach ($items as $item) {
            if (! $item instanceof SampleCycleItem) {
                continue;
            }

            $item->loadMissing('stocks.warehouse');

            foreach ($item->stocks as $stock) {
                $stockId = (int) $stock->id;
                $afterSales = round((float) ($salesAdjustments[$stockId]['adjusted_system_qty'] ?? $stock->adjusted_system_qty ?? $stock->system_qty), 4);

                $defaults[$stockId] = [
                    'pending_transfer_qty' => 0.0,
                    'validation_system_qty' => $afterSales,
                    'transfers' => [],
                    'conversion_warnings' => [],
                ];

                if (! $stock->warehouse || $stock->item_id === null) {
                    continue;
                }

                $source = (string) $stock->warehouse->source_database;
                $requests[$source]['source_database'] = $source;
                $requests[$source]['stocks'][] = $stock;
            }
        }

        foreach ($requests as $request) {
            $sourceDatabase = $request['source_database'];
            /** @var array<int, SampleCycleItemStock> $stocks */
            $stocks = $request['stocks'];

            $itemIds = collect($stocks)->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $destinationWarehouseIds = collect($stocks)
                ->map(fn (SampleCycleItemStock $stock) => (int) $stock->warehouse->erp_warehouse_id)
                ->unique()->values();

            if ($itemIds->isEmpty() || $destinationWarehouseIds->isEmpty()) {
                continue;
            }

            $connection = $this->resolver->connectionName($sourceDatabase);
            $rowsByPair = [];
            $ratioCache = [];

            foreach ($itemIds->chunk(350) as $itemChunk) {
                $itemPlaceholders = implode(',', array_fill(0, $itemChunk->count(), '?'));
                $warehousePlaceholders = implode(',', array_fill(0, $destinationWarehouseIds->count(), '?'));

                $sql = <<<SQL
                    SELECT
                        MUT.MutationID,
                        MUT.MutationNumber,
                        MUT.MutationDate,
                        MUT.SourceWarehouseID,
                        MUT.DestinationWarehouseID,
                        ASAL.WarehouseCode AS SourceWarehouseCode,
                        ASAL.Name AS SourceWarehouseName,
                        TUJUAN.WarehouseCode AS DestinationWarehouseCode,
                        TUJUAN.Name AS DestinationWarehouseName,
                        DET.ItemID,
                        DET.UOMLevel,
                        ITEM.ItemCode,
                        ITEM.ItemName,
                        UOM.UOMCode,
                        SUM(CAST(DET.Quantity AS decimal(28,4))) AS Quantity
                    FROM IC_MutationDetails DET WITH (NOLOCK)
                    INNER JOIN IC_Mutations MUT WITH (NOLOCK)
                        ON MUT.MutationID = DET.MutationID
                    LEFT JOIN IC_Warehouses ASAL WITH (NOLOCK)
                        ON ASAL.WarehouseID = MUT.SourceWarehouseID
                    LEFT JOIN IC_Warehouses TUJUAN WITH (NOLOCK)
                        ON TUJUAN.WarehouseID = MUT.DestinationWarehouseID
                    LEFT JOIN IC_Items ITEM WITH (NOLOCK)
                        ON ITEM.ItemID = DET.ItemID
                    LEFT JOIN IC_UOM UOM WITH (NOLOCK)
                        ON UOM.UOMID = CASE DET.UOMLevel
                            WHEN 1 THEN ITEM.UOMID1
                            WHEN 2 THEN ITEM.UOMID2
                            WHEN 3 THEN ITEM.UOMID3
                            WHEN 4 THEN ITEM.UOMID4
                        END
                    WHERE MUT.ReceivedBy IS NULL
                      AND MUT.DestinationWarehouseID IN ({$warehousePlaceholders})
                      AND DET.ItemID IN ({$itemPlaceholders})
                    GROUP BY
                        MUT.MutationID,
                        MUT.MutationNumber,
                        MUT.MutationDate,
                        MUT.SourceWarehouseID,
                        MUT.DestinationWarehouseID,
                        ASAL.WarehouseCode,
                        ASAL.Name,
                        TUJUAN.WarehouseCode,
                        TUJUAN.Name,
                        DET.ItemID,
                        DET.UOMLevel,
                        ITEM.ItemCode,
                        ITEM.ItemName,
                        UOM.UOMCode
                    ORDER BY MUT.MutationDate, MUT.MutationNumber
                SQL;

                $bindings = array_merge(
                    $destinationWarehouseIds->all(),
                    $itemChunk->all()
                );

                foreach (DB::connection($connection)->select($sql, $bindings) as $row) {
                    $itemCode = trim((string) ($row->ItemCode ?? ''));
                    $uomCode = trim((string) ($row->UOMCode ?? ''));
                    $uomLevel = (int) ($row->UOMLevel ?? 1);
                    $documentQty = round((float) ($row->Quantity ?? 0), 4);
                    $warning = null;

                    if ($uomLevel === 1) {
                        $ratio = 1.0;
                    } else {
                        $ratioKey = mb_strtoupper($itemCode).'|'.mb_strtoupper($uomCode);
                        if (! array_key_exists($ratioKey, $ratioCache)) {
                            $ratioCache[$ratioKey] = $this->barcodeService->ratioForDocumentUom($itemCode, $uomCode);
                        }
                        $ratio = $ratioCache[$ratioKey];
                        if ($ratio === null || (float) $ratio <= 0) {
                            $ratio = 0.0;
                            $warning = "Ratio {$itemCode} / {$uomCode} belum tersedia. Qty GT ini tidak ditambahkan ke stok validasi.";
                        }
                    }

                    $baseQty = round($documentQty * (float) $ratio, 4);
                    $pair = (int) $row->ItemID.'|'.(int) $row->DestinationWarehouseID;
                    $rowsByPair[$pair]['qty'] = round((float) ($rowsByPair[$pair]['qty'] ?? 0) + $baseQty, 4);
                    $rowsByPair[$pair]['transfers'][] = [
                        'mutation_id' => (int) $row->MutationID,
                        'mutation_number' => (string) $row->MutationNumber,
                        'mutation_date' => $row->MutationDate ? date('Y-m-d', strtotime((string) $row->MutationDate)) : null,
                        'source_warehouse_code' => (string) ($row->SourceWarehouseCode ?? ''),
                        'source_warehouse_name' => (string) ($row->SourceWarehouseName ?? ''),
                        'destination_warehouse_code' => (string) ($row->DestinationWarehouseCode ?? ''),
                        'destination_warehouse_name' => (string) ($row->DestinationWarehouseName ?? ''),
                        'uom_code' => $uomCode,
                        'document_qty' => $documentQty,
                        'ratio_to_smallest' => (float) $ratio,
                        'qty_smallest' => $baseQty,
                    ];
                    if ($warning !== null) {
                        $rowsByPair[$pair]['warnings'][] = $warning;
                    }
                }
            }

            foreach ($stocks as $stock) {
                $stockId = (int) $stock->id;
                $pair = (int) $stock->item_id.'|'.(int) $stock->warehouse->erp_warehouse_id;
                $pending = $rowsByPair[$pair] ?? ['qty' => 0.0, 'transfers' => [], 'warnings' => []];
                $pendingQty = round((float) ($pending['qty'] ?? 0), 4);
                $afterSales = round((float) ($salesAdjustments[$stockId]['adjusted_system_qty'] ?? $stock->adjusted_system_qty ?? $stock->system_qty), 4);

                $defaults[$stockId] = [
                    'pending_transfer_qty' => $pendingQty,
                    'validation_system_qty' => round($afterSales + $pendingQty, 4),
                    'transfers' => $pending['transfers'] ?? [],
                    'conversion_warnings' => array_values(array_unique($pending['warnings'] ?? [])),
                ];
            }
        }

        return $defaults;
    }
}
