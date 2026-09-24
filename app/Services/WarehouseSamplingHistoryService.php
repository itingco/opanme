<?php

namespace App\Services;

use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseSamplingHistoryService
{
    public function filters(Request $request): array
    {
        $source = strtoupper(trim((string) $request->input('source_database', '')));
        if (! in_array($source, ['AS_INGCO', 'AS_SMI'], true)) {
            $source = '';
        }

        $result = strtoupper(trim((string) $request->input('result', '')));
        if (! in_array($result, [SampleCheck::RESULT_MATCH, SampleCheck::RESULT_MISMATCH], true)) {
            $result = '';
        }

        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        if ($dateFrom !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = '';
        }
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'q' => trim((string) $request->input('q', '')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'source_database' => $source,
            'warehouse_key' => trim((string) $request->input('warehouse_key', '')),
            'result' => $result,
            'checker_id' => $request->integer('checker_id') ?: null,
        ];
    }

    public function query(User $user, array $filters): Builder
    {
        $query = DB::table('sample_cycle_item_stocks as stock')
            ->join('sample_cycle_items as item', 'item.id', '=', 'stock.sample_cycle_item_id')
            ->join('sample_cycles as cycle', 'cycle.id', '=', 'item.sample_cycle_id')
            ->join('sample_cycle_warehouses as wh', 'wh.id', '=', 'stock.sample_cycle_warehouse_id')
            ->leftJoin('users as checker', 'checker.id', '=', 'item.checked_by')
            ->leftJoin('users as validator', 'validator.id', '=', 'item.validated_by')
            ->leftJoin('users as creator', 'creator.id', '=', 'cycle.created_by')
            ->where('cycle.cycle_type', SampleCycle::TYPE_WAREHOUSE)
            ->where('cycle.status', SampleCycle::STATUS_CLOSED)
            ->when($user->isAdminGudang(), fn (Builder $q) => $q->where('cycle.created_by', $user->id));

        if ($filters['date_from'] !== '') {
            $query->where('cycle.closed_at', '>=', $filters['date_from'].' 00:00:00');
        }
        if ($filters['date_to'] !== '') {
            $query->where('cycle.closed_at', '<=', $filters['date_to'].' 23:59:59');
        }
        if ($filters['source_database'] !== '') {
            $query->where('wh.source_database', $filters['source_database']);
        }
        if ($filters['checker_id']) {
            $query->where('item.checked_by', $filters['checker_id']);
        }
        if ($filters['result'] !== '') {
            $query->where('stock.result', $filters['result']);
        }

        if ($filters['warehouse_key'] !== '') {
            [$warehouseDb, $warehouseId] = array_pad(explode('|', $filters['warehouse_key'], 2), 2, null);
            if (in_array($warehouseDb, ['AS_INGCO', 'AS_SMI'], true) && ctype_digit((string) $warehouseId)) {
                $query->where('wh.source_database', $warehouseDb)
                    ->where('wh.erp_warehouse_id', (int) $warehouseId);
            }
        }

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $q) use ($term, $operator): void {
                $q->where('cycle.cycle_no', $operator, $term)
                    ->orWhere('cycle.location', $operator, $term)
                    ->orWhere('item.item_code', $operator, $term)
                    ->orWhere('item.item_name', $operator, $term)
                    ->orWhere('item.checker_comment', $operator, $term)
                    ->orWhere('item.validation_note', $operator, $term)
                    ->orWhere('wh.warehouse_code', $operator, $term)
                    ->orWhere('wh.warehouse_name', $operator, $term)
                    ->orWhere('checker.name', $operator, $term)
                    ->orWhere('validator.name', $operator, $term);
            });
        }

        return $query->select([
            'stock.id as stock_row_id',
            'stock.sample_cycle_item_id',
            'cycle.id as sample_cycle_id',
            'cycle.cycle_no',
            'cycle.location',
            'cycle.started_at',
            'cycle.closed_at',
            'cycle.target_percentage',
            'creator.name as creator_name',
            'item.line_no',
            'item.item_code',
            'item.item_name',
            'item.uom_code',
            'item.system_qty as total_system_qty',
            'item.physical_qty as checker_physical_total',
            'item.checker_comment',
            'item.checked_at',
            'item.validation_note',
            'item.validated_at',
            'checker.name as checker_name',
            'validator.name as validator_name',
            'wh.source_database',
            'wh.erp_warehouse_id',
            'wh.warehouse_code',
            'wh.warehouse_name',
            'stock.system_qty as warehouse_system_qty',
            'stock.allocated_physical_qty as warehouse_physical_qty',
            'stock.result as warehouse_result',
        ]);
    }

    public function orderedQuery(User $user, array $filters): Builder
    {
        return $this->query($user, $filters)
            ->orderByDesc('cycle.closed_at')
            ->orderByDesc('cycle.id')
            ->orderBy('item.line_no')
            ->orderBy('wh.source_database')
            ->orderBy('wh.warehouse_code');
    }

    public function stats(User $user, array $filters): array
    {
        $base = $this->query($user, $filters);

        $rows = (clone $base)->count();
        $periods = (clone $base)->distinct()->count('cycle.id');
        $items = (clone $base)->distinct()->count('item.id');
        $match = (clone $base)->where('stock.result', SampleCheck::RESULT_MATCH)->count();
        $mismatch = (clone $base)->where('stock.result', SampleCheck::RESULT_MISMATCH)->count();

        return compact('rows', 'periods', 'items', 'match', 'mismatch');
    }

    public function warehouseOptions(User $user): array
    {
        $query = DB::table('sample_cycle_warehouses as wh')
            ->join('sample_cycles as cycle', 'cycle.id', '=', 'wh.sample_cycle_id')
            ->where('cycle.cycle_type', SampleCycle::TYPE_WAREHOUSE)
            ->where('cycle.status', SampleCycle::STATUS_CLOSED)
            ->when($user->isAdminGudang(), fn (Builder $q) => $q->where('cycle.created_by', $user->id))
            ->select(['wh.source_database', 'wh.erp_warehouse_id', 'wh.warehouse_code', 'wh.warehouse_name'])
            ->distinct()
            ->orderBy('wh.source_database')
            ->orderBy('wh.warehouse_code')
            ->get();

        return $query->map(fn ($row): array => [
            'key' => $row->source_database.'|'.$row->erp_warehouse_id,
            'source_database' => (string) $row->source_database,
            'warehouse_code' => (string) $row->warehouse_code,
            'warehouse_name' => (string) $row->warehouse_name,
        ])->all();
    }

    public function checkerOptions(User $user): array
    {
        $query = DB::table('users as u')
            ->join('sample_cycle_items as item', 'item.checked_by', '=', 'u.id')
            ->join('sample_cycles as cycle', 'cycle.id', '=', 'item.sample_cycle_id')
            ->where('cycle.cycle_type', SampleCycle::TYPE_WAREHOUSE)
            ->where('cycle.status', SampleCycle::STATUS_CLOSED)
            ->when($user->isAdminGudang(), fn (Builder $q) => $q->where('cycle.created_by', $user->id))
            ->select(['u.id', 'u.name'])
            ->distinct()
            ->orderBy('u.name')
            ->get();

        return $query->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])->all();
    }
}
