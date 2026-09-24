<?php

namespace App\Services;

use App\Models\SampleCycle;
use Illuminate\Support\Collection;

class WarehouseSamplingMultiStockService
{
    public function __construct(
        private readonly ErpStockService $stock,
        private readonly WarehouseSamplingCatalogService $catalog
    ) {
    }

    /**
     * Build the combined stock inventory for every warehouse attached to the period.
     *
     * IMPORTANT:
     * - Every selected warehouse is read independently.
     * - Items are merged only after each warehouse snapshot has been collected.
     * - warehouse_stats makes it visible to the UI that stock from all selected
     *   warehouses has actually been loaded.
     *
     * @return array{items:Collection,warehouses:array<int,array<string,mixed>>}
     */
    public function inventory(SampleCycle $cycle): array
    {
        $cycle->loadMissing('warehouses');
        $groups = collect();
        $warehouseStats = [];

        foreach ($cycle->warehouses->groupBy('source_database') as $sourceDatabase => $warehouses) {
            $snapshots = [];
            $itemIds = [];

            foreach ($warehouses as $warehouse) {
                $rows = collect($this->stock->warehouseSnapshot(
                    (string) $sourceDatabase,
                    now(),
                    (int) $warehouse->erp_warehouse_id
                ))->filter(fn (array $row): bool => (float) ($row['smallest_on_hand'] ?? 0) > 0)
                  ->values();

                $snapshots[(int) $warehouse->id] = $rows;
                $warehouseStats[(int) $warehouse->id] = [
                    'id' => (int) $warehouse->id,
                    'source_database' => (string) $sourceDatabase,
                    'warehouse_code' => (string) $warehouse->warehouse_code,
                    'warehouse_name' => (string) $warehouse->warehouse_name,
                    'stock_item_count' => $rows->count(),
                ];

                foreach ($rows as $row) {
                    $itemIds[] = (int) $row['item_id'];
                }
            }

            $catalog = collect($this->catalog->findItems((string) $sourceDatabase, $itemIds))
                ->keyBy('item_id');

            foreach ($warehouses as $warehouse) {
                foreach ($snapshots[(int) $warehouse->id] ?? [] as $row) {
                    $itemId = (int) $row['item_id'];
                    $item = $catalog->get($itemId);
                    $itemCode = trim((string) ($item['item_code'] ?? $row['item_code'] ?? ''));
                    if ($itemCode === '') {
                        continue;
                    }

                    $uomCode = trim((string) ($item['uom_code'] ?? 'PCS')) ?: 'PCS';
                    $key = $this->key($itemCode, $uomCode);
                    $current = $groups->get($key, [
                        'key' => $key,
                        'item_code' => $itemCode,
                        'item_name' => (string) ($item['item_name'] ?? $row['item_name'] ?? ''),
                        'uom_code' => $uomCode,
                        'total_system_qty' => 0.0,
                        'warehouse_count' => 0,
                        'warehouses' => [],
                    ]);

                    $qty = round((float) ($row['smallest_on_hand'] ?? 0), 4);
                    $current['total_system_qty'] += $qty;
                    $current['warehouse_count']++;
                    $current['warehouses'][] = [
                        'cycle_warehouse_id' => (int) $warehouse->id,
                        'source_database' => (string) $sourceDatabase,
                        'warehouse_code' => (string) $warehouse->warehouse_code,
                        'warehouse_name' => (string) $warehouse->warehouse_name,
                        'item_id' => $itemId,
                        'system_qty' => $qty,
                    ];
                    $groups->put($key, $current);
                }
            }
        }

        $items = $groups
            ->sortBy(fn (array $row): string => mb_strtoupper($row['item_code']))
            ->values();

        return [
            'items' => $items,
            'warehouses' => array_values($warehouseStats),
        ];
    }

    /**
     * Aggregate items with positive stock from every database/warehouse attached to the period.
     * The same ItemCode + smallest UOM is presented once to Admin Gudang.
     */
    public function availableItems(SampleCycle $cycle): Collection
    {
        return $this->inventory($cycle)['items'];
    }

    /**
     * Build one item line plus a stock snapshot for EVERY selected warehouse.
     * Warehouses where the item exists but has no stock are included with system_qty = 0,
     * allowing Admin Gudang to allocate checker total to that warehouse during validation.
     */
    public function materialize(SampleCycle $cycle, array $selectedKeys): array
    {
        $cycle->loadMissing('warehouses');
        $selectedKeys = collect($selectedKeys)
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values();

        if ($selectedKeys->isEmpty()) {
            return [];
        }

        $available = $this->availableItems($cycle)
            ->filter(fn (array $row): bool => $selectedKeys->contains($row['key']))
            ->keyBy('key');

        if ($available->isEmpty()) {
            return [];
        }

        $codes = $available->pluck('item_code')->unique()->values()->all();
        $catalogByDatabase = [];
        foreach ($cycle->warehouses->groupBy('source_database') as $sourceDatabase => $warehouses) {
            $catalogByDatabase[$sourceDatabase] = collect($this->catalog->findItemsByCodes((string) $sourceDatabase, $codes))
                ->keyBy(fn (array $row): string => $this->key($row['item_code'], $row['uom_code']));
        }

        $snapshots = [];
        foreach ($cycle->warehouses as $warehouse) {
            $snapshots[(int) $warehouse->id] = collect($this->stock->warehouseSnapshot(
                (string) $warehouse->source_database,
                now(),
                (int) $warehouse->erp_warehouse_id
            ))->keyBy(fn (array $row): int => (int) $row['item_id']);
        }

        $result = [];
        foreach ($selectedKeys as $key) {
            $base = $available->get($key);
            if (! $base) {
                continue;
            }

            $stocks = [];
            $total = 0.0;
            $representativeItemId = null;

            foreach ($cycle->warehouses as $warehouse) {
                $databaseCatalog = $catalogByDatabase[$warehouse->source_database] ?? collect();
                $catalogItem = $databaseCatalog->get($key);
                $itemId = $catalogItem ? (int) $catalogItem['item_id'] : null;
                $qty = 0.0;

                if ($itemId !== null) {
                    $representativeItemId ??= $itemId;
                    $stockRow = $snapshots[(int) $warehouse->id]?->get($itemId);
                    $qty = round((float) ($stockRow['smallest_on_hand'] ?? 0), 4);
                }

                $total += $qty;
                $stocks[] = [
                    'sample_cycle_warehouse_id' => (int) $warehouse->id,
                    'item_id' => $itemId,
                    'system_qty' => $qty,
                ];
            }

            if ($representativeItemId === null || $total <= 0) {
                continue;
            }

            $result[] = [
                'item_id' => $representativeItemId,
                'item_code' => $base['item_code'],
                'item_name' => $base['item_name'],
                'uom_code' => $base['uom_code'],
                'system_qty' => round($total, 4),
                'stocks' => $stocks,
            ];
        }

        return $result;
    }

    private function key(string $itemCode, string $uomCode): string
    {
        return mb_strtoupper(trim($itemCode)).'|'.mb_strtoupper(trim($uomCode));
    }
}
