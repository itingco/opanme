<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CycleSummaryService
{
    public function calculateValues(
        float|int|string $opening,
        float|int|string $physical,
        float|int|string|null $closing,
        float|int|string|null $override = null
    ): array {
        $openingF = (float) $opening;
        $physicalF = (float) $physical;
        $overrideF = $override === null ? null : (float) $override;
        $finalPhysical = $overrideF ?? $physicalF;
        $variance = $finalPhysical - $openingF;
        $movement = $closing === null ? null : (float) $closing - $openingF;

        return [
            'physical_qty' => number_format($physicalF, 4, '.', ''),
            'override_qty' => $overrideF === null ? null : number_format($overrideF, 4, '.', ''),
            'final_physical_qty' => number_format($finalPhysical, 4, '.', ''),
            'has_override' => $overrideF !== null,
            'variance' => number_format($variance, 4, '.', ''),
            'movement_qty' => $movement === null ? null : number_format($movement, 4, '.', ''),
            'has_movement' => $movement !== null && abs($movement) > 0.00005,
        ];
    }

    public function query(StockOpnameCycle $cycle, ?int $warehouseId = null, bool $varianceOnly = false): Builder
    {
        $normal = $this->normalQuery($cycle);
        $discovered = $this->discoveredQuery($cycle);
        $union = $normal->unionAll($discovered);

        $query = DB::query()->fromSub($union, 'summary_rows');

        if ($warehouseId) {
            $query->where('summary_rows.warehouse_id', $warehouseId);
        }

        if ($varianceOnly) {
            $query->whereRaw('ABS(summary_rows.variance) > 0.00005');
        }

        return $query
            ->orderBy('summary_rows.warehouse_code')
            ->orderBy('summary_rows.row_type')
            ->orderBy('summary_rows.item_code');
    }

    private function normalQuery(StockOpnameCycle $cycle): Builder
    {
        $snapshotKeys = DB::table('stock_snapshots')
            ->select('warehouse_id', 'item_id')
            ->where('cycle_id', $cycle->id);

        $scanKeys = DB::table('scan_transactions')
            ->select('warehouse_id', 'item_id')
            ->where('cycle_id', $cycle->id);

        $keys = $snapshotKeys->union($scanKeys);

        $scanAgg = DB::table('scan_transactions')
            ->selectRaw('warehouse_id, item_id, MAX(item_code) AS item_code, MAX(item_name) AS item_name, SUM(physical_qty) AS physical_qty, COUNT(*) AS scan_count')
            ->where('cycle_id', $cycle->id)
            ->groupBy('warehouse_id', 'item_id');

        return DB::query()->fromSub($keys, 'k')
            ->join('cycle_warehouses as cw', 'cw.id', '=', 'k.warehouse_id')
            ->leftJoin('stock_snapshots as ss', function ($join) use ($cycle) {
                $join->on('ss.warehouse_id', '=', 'k.warehouse_id')
                    ->on('ss.item_id', '=', 'k.item_id')
                    ->where('ss.cycle_id', '=', $cycle->id);
            })
            ->leftJoinSub($scanAgg, 'sa', function ($join) {
                $join->on('sa.warehouse_id', '=', 'k.warehouse_id')
                    ->on('sa.item_id', '=', 'k.item_id');
            })
            ->leftJoin('stock_opname_overrides as so', function ($join) use ($cycle) {
                $join->on('so.warehouse_id', '=', 'k.warehouse_id')
                    ->on('so.item_id', '=', 'k.item_id')
                    ->where('so.cycle_id', '=', $cycle->id);
            })
            ->leftJoin('users as override_user', 'override_user.id', '=', 'so.updated_by')
            ->selectRaw(<<<'SQL'
                'ERP' AS row_type,
                'ERP:' || CAST(k.item_id AS TEXT) AS row_key,
                k.warehouse_id,
                cw.warehouse_code,
                cw.warehouse_name,
                k.item_id,
                CAST(NULL AS BIGINT) AS discovered_item_id,
                CAST(NULL AS VARCHAR) AS alias_code,
                COALESCE(ss.item_code, sa.item_code) AS item_code,
                COALESCE(ss.item_name, sa.item_name) AS item_name,
                CAST(NULL AS VARCHAR) AS smallest_uom_code,
                COALESCE(ss.opening_system_qty, 0) AS opening_system_qty,
                ss.closing_system_qty,
                COALESCE(sa.physical_qty, 0) AS physical_qty,
                COALESCE(sa.scan_count, 0) AS scan_count,
                so.override_qty,
                CAST(NULL AS DECIMAL(28,4)) AS override_input_qty,
                CAST(NULL AS VARCHAR) AS override_input_uom_code,
                CAST(NULL AS VARCHAR) AS override_smallest_uom_code,
                CAST(NULL AS DECIMAL(28,4)) AS override_ratio_used,
                so.comment AS override_comment,
                so.updated_by AS override_updated_by,
                override_user.name AS override_by_name,
                so.updated_at AS override_updated_at,
                COALESCE(so.override_qty, sa.physical_qty, 0) AS final_physical_qty,
                COALESCE(so.override_qty, sa.physical_qty, 0) - COALESCE(ss.opening_system_qty, 0) AS variance,
                CASE
                    WHEN ss.closing_system_qty IS NULL THEN NULL
                    ELSE ss.closing_system_qty - COALESCE(ss.opening_system_qty, 0)
                END AS movement_qty
            SQL);
    }

    private function discoveredQuery(StockOpnameCycle $cycle): Builder
    {
        $scanKeys = DB::table('discovered_scan_transactions')
            ->select('warehouse_id', 'discovered_item_id')
            ->where('cycle_id', $cycle->id);

        $overrideKeys = DB::table('stock_opname_discovered_overrides')
            ->select('warehouse_id', 'discovered_item_id')
            ->where('cycle_id', $cycle->id);

        $keys = $scanKeys->union($overrideKeys);

        $scanAgg = DB::table('discovered_scan_transactions')
            ->selectRaw('warehouse_id, discovered_item_id, SUM(physical_qty) AS physical_qty, COUNT(*) AS scan_count')
            ->where('cycle_id', $cycle->id)
            ->groupBy('warehouse_id', 'discovered_item_id');

        return DB::query()->fromSub($keys, 'dk')
            ->join('cycle_warehouses as cw', 'cw.id', '=', 'dk.warehouse_id')
            ->join('stock_opname_discovered_items as di', function ($join) use ($cycle) {
                $join->on('di.id', '=', 'dk.discovered_item_id')
                    ->where('di.cycle_id', '=', $cycle->id);
            })
            ->leftJoinSub($scanAgg, 'dsa', function ($join) {
                $join->on('dsa.warehouse_id', '=', 'dk.warehouse_id')
                    ->on('dsa.discovered_item_id', '=', 'dk.discovered_item_id');
            })
            ->leftJoin('stock_opname_discovered_overrides as dso', function ($join) use ($cycle) {
                $join->on('dso.warehouse_id', '=', 'dk.warehouse_id')
                    ->on('dso.discovered_item_id', '=', 'dk.discovered_item_id')
                    ->where('dso.cycle_id', '=', $cycle->id);
            })
            ->leftJoin('users as override_user', 'override_user.id', '=', 'dso.updated_by')
            ->selectRaw(<<<'SQL'
                'NON_SYSTEM' AS row_type,
                'NON_SYSTEM:' || CAST(di.id AS TEXT) AS row_key,
                dk.warehouse_id,
                cw.warehouse_code,
                cw.warehouse_name,
                CAST(NULL AS BIGINT) AS item_id,
                di.id AS discovered_item_id,
                di.alias_code,
                di.alias_code AS item_code,
                di.item_name,
                COALESCE(dso.smallest_uom_code, di.smallest_uom_code) AS smallest_uom_code,
                CAST(0 AS DECIMAL(28,4)) AS opening_system_qty,
                CAST(0 AS DECIMAL(28,4)) AS closing_system_qty,
                COALESCE(dsa.physical_qty, 0) AS physical_qty,
                COALESCE(dsa.scan_count, 0) AS scan_count,
                dso.override_qty,
                dso.input_qty AS override_input_qty,
                dso.input_uom_code AS override_input_uom_code,
                dso.smallest_uom_code AS override_smallest_uom_code,
                dso.ratio_used AS override_ratio_used,
                dso.comment AS override_comment,
                dso.updated_by AS override_updated_by,
                override_user.name AS override_by_name,
                dso.updated_at AS override_updated_at,
                COALESCE(dso.override_qty, dsa.physical_qty, 0) AS final_physical_qty,
                COALESCE(dso.override_qty, dsa.physical_qty, 0) AS variance,
                CAST(0 AS DECIMAL(28,4)) AS movement_qty
            SQL);
    }
}
