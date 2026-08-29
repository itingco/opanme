<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use App\Models\StockSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CycleSnapshotService
{
    public function __construct(private readonly ErpStockService $erpStock)
    {
    }

    public function captureOpening(StockOpnameCycle $cycle, CarbonInterface $snapshotAt): int
    {
        $cycle->loadMissing('warehouses');
        $warehouseMap = $cycle->warehouses->keyBy('erp_warehouse_id');
        $rows = $this->erpStock->snapshot($cycle->source_database, $cycle->cutoff_date);

        return DB::transaction(function () use ($cycle, $snapshotAt, $warehouseMap, $rows): int {
            StockSnapshot::query()->where('cycle_id', $cycle->id)->delete();
            $count = 0;

            foreach ($rows as $row) {
                $warehouse = $warehouseMap->get($row['warehouse_id']);
                if (! $warehouse) {
                    continue;
                }

                StockSnapshot::create([
                    'cycle_id' => $cycle->id,
                    'warehouse_id' => $warehouse->id,
                    'erp_warehouse_id' => $warehouse->erp_warehouse_id,
                    'warehouse_code' => $warehouse->warehouse_code,
                    'item_id' => $row['item_id'],
                    'item_code' => $row['item_code'],
                    'item_name' => $row['item_name'],
                    'opening_system_qty' => $row['smallest_on_hand'],
                    'opening_snapshot_at' => $snapshotAt,
                ]);
                $count++;
            }

            return $count;
        });
    }

    public function captureClosing(StockOpnameCycle $cycle, CarbonInterface $snapshotAt): int
    {
        $cycle->loadMissing('warehouses');
        $warehouseMap = $cycle->warehouses->keyBy('erp_warehouse_id');
        $rows = $this->erpStock->snapshot($cycle->source_database, $snapshotAt->toDateString());

        return DB::transaction(function () use ($cycle, $snapshotAt, $warehouseMap, $rows): int {
            StockSnapshot::query()->where('cycle_id', $cycle->id)->update([
                'closing_system_qty' => 0,
                'closing_snapshot_at' => $snapshotAt,
            ]);

            $count = 0;
            foreach ($rows as $row) {
                $warehouse = $warehouseMap->get($row['warehouse_id']);
                if (! $warehouse) {
                    continue;
                }

                $snapshot = StockSnapshot::firstOrNew([
                    'cycle_id' => $cycle->id,
                    'warehouse_id' => $warehouse->id,
                    'item_id' => $row['item_id'],
                ]);
                if (! $snapshot->exists) {
                    $snapshot->opening_system_qty = 0;
                    $snapshot->opening_snapshot_at = $cycle->started_at;
                }
                $snapshot->fill([
                    'erp_warehouse_id' => $warehouse->erp_warehouse_id,
                    'warehouse_code' => $warehouse->warehouse_code,
                    'item_code' => $row['item_code'],
                    'item_name' => $row['item_name'],
                    'closing_system_qty' => $row['smallest_on_hand'],
                    'closing_snapshot_at' => $snapshotAt,
                ]);
                $snapshot->save();
                $count++;
            }

            return $count;
        });
    }
}
