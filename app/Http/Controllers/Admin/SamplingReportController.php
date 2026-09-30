<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Models\User;
use App\Services\ErpCatalogService;
use App\Services\SampleReportPdfService;
use App\Services\SampleReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SamplingReportController extends Controller
{
    public function index(Request $request, SampleReportService $report, ErpCatalogService $erp): View
    {
        $filters = $this->applyAccessScope($request, $report->filters($request));
        $rows = $report->query($filters)->paginate(60)->withQueryString();
        $warehouses = $this->availableWarehouses($request, $filters['source_database'], $erp);

        $coverage = null;
        $coverageError = null;
        try {
            $coverage = $this->decorateCoverage($report->coverage($filters), $warehouses);
        } catch (Throwable $e) {
            report($e);
            $coverageError = 'Coverage tidak dapat dihitung karena stok ERP gagal dibaca: '.$e->getMessage();
        }

        return view('admin.sampling.index', [
            'filters' => $filters,
            'rows' => $rows,
            'stats' => $report->stats($filters),
            'coverage' => $coverage,
            'coverageError' => $coverageError,
            'users' => $this->availableCheckers($request),
            'databases' => [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI],
            'warehouses' => $warehouses,
            'reportIndexRoute' => $request->routeIs('gerai.admin.sampling.*')
                ? 'gerai.admin.sampling.index'
                : 'admin.sampling.index',
            'reportPdfRoute' => $request->routeIs('gerai.admin.sampling.*')
                ? 'gerai.admin.sampling.pdf'
                : 'admin.sampling.pdf',
            'warehouseLookupRoute' => $request->routeIs('gerai.admin.sampling.*')
                ? 'gerai.admin.warehouses'
                : 'admin.erp.warehouses',
        ]);
    }

    public function exportPdf(Request $request, SampleReportService $report, SampleReportPdfService $pdf, ErpCatalogService $erp): Response
    {
        $filters = $this->applyAccessScope($request, $report->filters($request));

        try {
            $warehouses = $this->availableWarehouses($request, $filters['source_database'], $erp);
            $coverage = $this->decorateCoverage($report->coverage($filters), $warehouses);
        } catch (Throwable $e) {
            report($e);
            $coverage = null;
        }

        $contents = $pdf->render(
            $filters,
            $report->stats($filters),
            $coverage,
            $report->query($filters)->cursor()
        );
        $filename = 'Sampling-Gerai-Harian-'.$filters['date_from'].'-'.$filters['date_to'].'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function applyAccessScope(Request $request, array $filters): array
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            $filters['allowed_warehouse_pairs'] = null;
            return $filters;
        }

        abort_unless($user->isAdminGerai(), 403);

        $pairs = $user->warehouseAssignments()
            ->get(['source_database', 'erp_warehouse_id'])
            ->map(fn ($row) => [
                'source_database' => (string) $row->source_database,
                'erp_warehouse_id' => (int) $row->erp_warehouse_id,
            ])
            ->values()
            ->all();

        // Fallback untuk instalasi yang masih memiliki binding lama satu gudang.
        if ($pairs === [] && $user->source_database && $user->erp_warehouse_id) {
            $pairs[] = [
                'source_database' => (string) $user->source_database,
                'erp_warehouse_id' => (int) $user->erp_warehouse_id,
            ];
        }

        $filters['allowed_warehouse_pairs'] = $pairs;

        if ($filters['source_database'] !== '') {
            $allowedIds = collect($pairs)
                ->where('source_database', $filters['source_database'])
                ->pluck('erp_warehouse_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if (($filters['erp_warehouse_ids'] ?? []) !== []) {
                $filters['erp_warehouse_ids'] = collect($filters['erp_warehouse_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->intersect($allowedIds)
                    ->values()
                    ->all();
            }

            $filters['erp_warehouse_id'] = count($filters['erp_warehouse_ids'] ?? []) === 1
                ? $filters['erp_warehouse_ids'][0]
                : null;
        }

        return $filters;
    }

    private function availableWarehouses(Request $request, string $sourceDatabase, ErpCatalogService $erp): array
    {
        if ($sourceDatabase === '') {
            return [];
        }

        $all = $erp->warehouses($sourceDatabase);
        $user = $request->user();
        if ($user->isAdmin()) {
            return $all;
        }

        $allowedIds = $user->warehouseAssignments()
            ->where('source_database', $sourceDatabase)
            ->pluck('erp_warehouse_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($allowedIds === [] && $user->source_database === $sourceDatabase && $user->erp_warehouse_id) {
            $allowedIds = [(int) $user->erp_warehouse_id];
        }

        return collect($all)
            ->filter(fn (array $warehouse) => in_array((int) $warehouse['warehouse_id'], $allowedIds, true))
            ->values()
            ->all();
    }

    private function availableCheckers(Request $request): Collection
    {
        $query = User::query()
            ->whereIn('role', [User::ROLE_CHECKER_GERAI, User::ROLE_GERAI])
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->user()->isAdmin()) {
            return $query->get(['id', 'name']);
        }

        $pairs = $request->user()->warehouseAssignments()
            ->get(['source_database', 'erp_warehouse_id']);

        if ($pairs->isEmpty()) {
            return collect();
        }

        return $query
            ->whereHas('warehouseAssignments', function ($warehouseQuery) use ($pairs) {
                $warehouseQuery->where(function ($or) use ($pairs) {
                    foreach ($pairs as $pair) {
                        $or->orWhere(function ($one) use ($pair) {
                            $one->where('source_database', $pair->source_database)
                                ->where('erp_warehouse_id', $pair->erp_warehouse_id);
                        });
                    }
                });
            })
            ->get(['id', 'name']);
    }

    private function decorateCoverage(?array $coverage, array $warehouses): ?array
    {
        if (! $coverage || empty($coverage['warehouses'])) {
            return $coverage;
        }

        $catalog = collect($warehouses)->keyBy(fn (array $warehouse) => (int) $warehouse['warehouse_id']);
        foreach ($coverage['warehouses'] as &$item) {
            $warehouse = $catalog->get((int) $item['erp_warehouse_id']);
            if ($warehouse) {
                $item['warehouse_code'] = (string) $warehouse['warehouse_code'];
                $item['warehouse_name'] = (string) $warehouse['warehouse_name'];
            }
        }
        unset($item);

        return $coverage;
    }
}
