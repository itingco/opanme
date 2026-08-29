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

        $query = DB::query()->fromSub($keys, 'k')
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
                k.warehouse_id,
                cw.warehouse_code,
                cw.warehouse_name,
                k.item_id,
                COALESCE(ss.item_code, sa.item_code) AS item_code,
                COALESCE(ss.item_name, sa.item_name) AS item_name,
                COALESCE(ss.opening_system_qty, 0) AS opening_system_qty,
                ss.closing_system_qty,
                COALESCE(sa.physical_qty, 0) AS physical_qty,
                COALESCE(sa.scan_count, 0) AS scan_count,
                so.override_qty,
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

        if ($warehouseId) {
            $query->where('k.warehouse_id', $warehouseId);
        }

        if ($varianceOnly) {
            $query->whereRaw('ABS(COALESCE(so.override_qty, sa.physical_qty, 0) - COALESCE(ss.opening_system_qty, 0)) > 0.00005');
        }

        return $query
            ->orderBy('cw.warehouse_code')
            ->orderByRaw('COALESCE(ss.item_code, sa.item_code)');
    }
}
