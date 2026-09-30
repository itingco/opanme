<?php

namespace App\Services;

use App\Models\SampleCheck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SampleReportService
{
    public function __construct(
        private readonly ErpStockService $stock,
        private readonly SampleCoverageCalculator $calculator
    ) {
    }

    public function filters(Request $request): array
    {
        $from = $request->date('date_from')?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $to = $request->date('date_to')?->format('Y-m-d') ?? now()->format('Y-m-d');
        if ($from > $to) [$from, $to] = [$to, $from];

        $source = strtoupper(trim((string) $request->input('source_database', '')));
        if (! in_array($source, ['AS_INGCO', 'AS_SMI'], true)) $source = '';

        $warehouseIds = collect($request->input('erp_warehouse_ids', []))
            ->when(
                ! $request->has('erp_warehouse_ids') && $request->integer('erp_warehouse_id'),
                fn ($ids) => $ids->push($request->integer('erp_warehouse_id'))
            )
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'date_from' => $from,
            'date_to' => $to,
            'source_database' => $source,
            'erp_warehouse_ids' => $warehouseIds,
            'erp_warehouse_id' => count($warehouseIds) === 1 ? $warehouseIds[0] : null,
            'user_id' => $request->integer('user_id') ?: null,
            'result' => strtoupper(trim((string) $request->input('result', ''))),
            'location' => trim((string) $request->input('location', '')),
            'q' => trim((string) $request->input('q', '')),
        ];
    }

    public function query(array $filters): Builder
    {
        $start = $filters['date_from'].' 00:00:00';
        $end = $filters['date_to'].' 23:59:59';
        $warehouseIds = $filters['erp_warehouse_ids'] ?? [];
        $allowedPairs = $filters['allowed_warehouse_pairs'] ?? null;

        return SampleCheck::query()
            ->with('user')
            ->whereBetween('scanned_at', [$start, $end])
            ->when(is_array($allowedPairs), function ($q) use ($allowedPairs) {
                if ($allowedPairs === []) {
                    $q->whereRaw('1 = 0');
                    return;
                }

                $q->where(function ($scope) use ($allowedPairs) {
                    foreach ($allowedPairs as $pair) {
                        $scope->orWhere(function ($one) use ($pair) {
                            $one->where('source_database', $pair['source_database'])
                                ->where('erp_warehouse_id', (int) $pair['erp_warehouse_id']);
                        });
                    }
                });
            })
            ->when($filters['source_database'] !== '', fn ($q) => $q->where('source_database', $filters['source_database']))
            ->when($warehouseIds !== [], fn ($q) => $q->whereIn('erp_warehouse_id', $warehouseIds))
            ->when($filters['user_id'], fn ($q, $id) => $q->where('user_id', $id))
            ->when(in_array($filters['result'], ['MATCH','MISMATCH'], true), fn ($q) => $q->where('result', $filters['result']))
            ->when($filters['location'] !== '', fn ($q) => $q->where('location', 'like', '%'.$filters['location'].'%'))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $term = $filters['q'];
                $operator = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where(function ($x) use ($term, $operator) {
                    $x->where('item_code', $operator, "%{$term}%")
                        ->orWhere('item_name', $operator, "%{$term}%")
                        ->orWhere('barcode', $operator, "%{$term}%");
                });
            })
            ->orderByDesc('scanned_at')
            ->orderByDesc('id');
    }

    public function stats(array $filters): array
    {
        $base = $this->query($filters);
        return [
            'total' => (clone $base)->count(),
            'unique_items' => (clone $base)->distinct()->count('item_code'),
            'match' => (clone $base)->where('result', 'MATCH')->count(),
            'mismatch' => (clone $base)->where('result', 'MISMATCH')->count(),
            'users' => (clone $base)->distinct()->count('user_id'),
        ];
    }

    public function coverage(array $filters): ?array
    {
        $warehouseIds = $filters['erp_warehouse_ids'] ?? [];
        if ($filters['source_database'] === '' || $warehouseIds === []) {
            return null;
        }

        $allowedPairs = $filters['allowed_warehouse_pairs'] ?? null;
        if (is_array($allowedPairs)) {
            $allowedIds = collect($allowedPairs)
                ->where('source_database', $filters['source_database'])
                ->pluck('erp_warehouse_id')
                ->map(fn ($id) => (int) $id)
                ->unique();
            $warehouseIds = collect($warehouseIds)->intersect($allowedIds)->values()->all();
            if ($warehouseIds === []) {
                return null;
            }
        }

        $items = [];
        $aggregateTarget = 0;
        $aggregateCompleted = 0;

        foreach ($warehouseIds as $warehouseId) {
            $rows = $this->stock->warehouseSnapshot(
                $filters['source_database'],
                $filters['date_to'],
                (int) $warehouseId
            );

            $targets = array_column(
                array_filter($rows, static fn (array $r) => (float) $r['smallest_on_hand'] > 0),
                'item_code'
            );

            $sampled = SampleCheck::query()
                ->where('source_database', $filters['source_database'])
                ->where('erp_warehouse_id', (int) $warehouseId)
                ->whereBetween('scanned_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59'])
                ->distinct()
                ->pluck('item_code')
                ->all();

            $calculated = $this->calculator->calculate($targets, $sampled);
            $warehouseInfo = $rows[0] ?? null;

            $entry = array_merge($calculated, [
                'erp_warehouse_id' => (int) $warehouseId,
                'warehouse_code' => (string) ($warehouseInfo['warehouse_code'] ?? ('ID '.$warehouseId)),
                'warehouse_name' => (string) ($warehouseInfo['warehouse_name'] ?? 'Warehouse'),
            ]);

            $items[] = $entry;
            $aggregateTarget += $entry['target'];
            $aggregateCompleted += $entry['completed'];
        }

        return [
            'warehouses' => $items,
            'aggregate' => [
                'target' => $aggregateTarget,
                'completed' => $aggregateCompleted,
                'remaining' => max(0, $aggregateTarget - $aggregateCompleted),
                'percentage' => $aggregateTarget === 0
                    ? 100.0
                    : round(($aggregateCompleted / $aggregateTarget) * 100, 2),
            ],
        ];
    }
}
