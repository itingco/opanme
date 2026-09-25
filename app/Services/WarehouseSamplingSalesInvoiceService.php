<?php

namespace App\Services;

use App\Models\SampleCycleItem;
use App\Models\SampleCycleItemStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseSamplingSalesInvoiceService
{
    public function __construct(private readonly ErpConnectionResolver $resolver)
    {
    }

    /**
     * Ambil Sales Invoice pada tanggal checker melakukan pengecekan fisik.
     * Hasil dikembalikan per sample_cycle_item_stocks.id agar preview validasi
     * dan proses simpan memakai sumber data yang sama.
     *
     * @param Collection<int, SampleCycleItem> $items
     * @return array<int, array{sales_date:?string,sales_qty:float,adjusted_system_qty:float,invoices:array<int,array{invoice_date:string,invoice_number:string,qty:float}>}>
     */
    public function forItems(Collection $items): array
    {
        $requests = [];
        $defaults = [];

        foreach ($items as $item) {
            if (! $item instanceof SampleCycleItem) {
                continue;
            }

            $item->loadMissing('stocks.warehouse');
            $date = $item->checked_at?->format('Y-m-d')
                ?? $item->cycle?->started_at?->format('Y-m-d')
                ?? now()->format('Y-m-d');

            foreach ($item->stocks as $stock) {
                $systemQty = round((float) $stock->system_qty, 4);
                $defaults[(int) $stock->id] = [
                    'sales_date' => $date,
                    'sales_qty' => 0.0,
                    'adjusted_system_qty' => $systemQty,
                    'invoices' => [],
                ];

                if (! $stock->warehouse || $stock->item_id === null) {
                    continue;
                }

                $source = (string) $stock->warehouse->source_database;
                $key = $source.'|'.$date;
                $requests[$key]['source_database'] = $source;
                $requests[$key]['date'] = $date;
                $requests[$key]['stocks'][] = $stock;
            }
        }

        foreach ($requests as $request) {
            $sourceDatabase = $request['source_database'];
            $date = $request['date'];
            /** @var array<int, SampleCycleItemStock> $stocks */
            $stocks = $request['stocks'];

            $itemIds = collect($stocks)->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $warehouseIds = collect($stocks)
                ->map(fn (SampleCycleItemStock $stock) => (int) $stock->warehouse->erp_warehouse_id)
                ->unique()->values();

            if ($itemIds->isEmpty() || $warehouseIds->isEmpty()) {
                continue;
            }

            $salesByPair = [];
            $connection = $this->resolver->connectionName($sourceDatabase);

            // SQL Server mempunyai batas parameter. Item di-chunk agar aman walaupun
            // halaman validasi memuat banyak item sekaligus.
            foreach ($itemIds->chunk(400) as $itemChunk) {
                $itemPlaceholders = implode(',', array_fill(0, $itemChunk->count(), '?'));
                $warehousePlaceholders = implode(',', array_fill(0, $warehouseIds->count(), '?'));

                $sql = <<<SQL
                    SELECT
                        D.ItemID,
                        D.WarehouseID,
                        I.InvoiceDate,
                        I.InvoiceNumber,
                        SUM(CAST(D.Quantity AS decimal(28,4))) AS Quantity
                    FROM AR_InvoiceDetails D WITH (NOLOCK)
                    INNER JOIN AR_Invoices I WITH (NOLOCK)
                        ON I.InvoiceID = D.InvoiceID
                    WHERE I.InvoiceDate >= ?
                      AND I.InvoiceDate < DATEADD(DAY, 1, ?)
                      AND D.ItemID IN ({$itemPlaceholders})
                      AND D.WarehouseID IN ({$warehousePlaceholders})
                    GROUP BY D.ItemID, D.WarehouseID, I.InvoiceDate, I.InvoiceNumber
                    ORDER BY D.ItemID, D.WarehouseID, I.InvoiceNumber
                SQL;

                $bindings = array_merge(
                    [$date, $date],
                    $itemChunk->all(),
                    $warehouseIds->all()
                );

                $rows = DB::connection($connection)->select($sql, $bindings);
                foreach ($rows as $row) {
                    $pair = (int) $row->ItemID.'|'.(int) $row->WarehouseID;
                    $qty = round((float) $row->Quantity, 4);
                    $salesByPair[$pair]['qty'] = round((float) ($salesByPair[$pair]['qty'] ?? 0) + $qty, 4);
                    $salesByPair[$pair]['invoices'][] = [
                        'invoice_date' => date('Y-m-d', strtotime((string) $row->InvoiceDate)),
                        'invoice_number' => (string) $row->InvoiceNumber,
                        'qty' => $qty,
                    ];
                }
            }

            foreach ($stocks as $stock) {
                $pair = (int) $stock->item_id.'|'.(int) $stock->warehouse->erp_warehouse_id;
                $sales = $salesByPair[$pair] ?? ['qty' => 0.0, 'invoices' => []];
                $salesQty = round((float) $sales['qty'], 4);
                $systemQty = round((float) $stock->system_qty, 4);

                $defaults[(int) $stock->id] = [
                    'sales_date' => $date,
                    'sales_qty' => $salesQty,
                    'adjusted_system_qty' => round($systemQty - $salesQty, 4),
                    'invoices' => $sales['invoices'],
                ];
            }
        }

        return $defaults;
    }
}
