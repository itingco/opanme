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

        return [
            'date_from' => $from,
            'date_to' => $to,
            'source_database' => $source,
            'erp_warehouse_id' => $request->integer('erp_warehouse_id') ?: null,
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

        return SampleCheck::query()
            ->with('user')
            ->whereBetween('scanned_at', [$start, $end])
            ->when($filters['source_database'] !== '', fn ($q) => $q->where('source_database', $filters['source_database']))
            ->when($filters['erp_warehouse_id'], fn ($q, $id) => $q->where('erp_warehouse_id', $id))
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
        if ($filters['source_database'] === '' || ! $filters['erp_warehouse_id']) {
            return null;
        }

        // Target: unique item with positive ERP "Smallest On Hand" on the report end date.
        $rows = $this->stock->warehouseSnapshot(
            $filters['source_database'],
            $filters['date_to'],
            (int) $filters['erp_warehouse_id']
        );
        $targets = array_column(array_filter($rows, static fn (array $r) => (float) $r['smallest_on_hand'] > 0), 'item_code');

        // Coverage intentionally ignores result/user/location/search filters; it measures whether a target item was sampled at least once.
        $sampled = SampleCheck::query()
            ->where('source_database', $filters['source_database'])
            ->where('erp_warehouse_id', $filters['erp_warehouse_id'])
            ->whereBetween('scanned_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59'])
            ->distinct()
            ->pluck('item_code')
            ->all();

        return $this->calculator->calculate($targets, $sampled);
    }
}
