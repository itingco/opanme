<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Models\User;
use App\Services\ErpCatalogService;
use App\Services\SampleReportPdfService;
use App\Services\SampleReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SamplingReportController extends Controller
{
    public function index(Request $request, SampleReportService $report, ErpCatalogService $erp): View
    {
        $filters = $report->filters($request);
        $rows = $report->query($filters)->paginate(60)->withQueryString();
        $warehouses = $filters['source_database'] !== '' ? $erp->warehouses($filters['source_database']) : [];

        $coverage = null;
        $coverageError = null;
        try {
            $coverage = $report->coverage($filters);
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
            'users' => User::query()->where('role', User::ROLE_GERAI)->orderBy('name')->get(['id','name']),
            'databases' => [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI],
            'warehouses' => $warehouses,
        ]);
    }

    public function exportPdf(Request $request, SampleReportService $report, SampleReportPdfService $pdf): Response
    {
        $filters = $report->filters($request);
        try {
            $coverage = $report->coverage($filters);
        } catch (Throwable $e) {
            report($e);
            $coverage = null;
        }
        $contents = $pdf->render($filters, $report->stats($filters), $coverage, $report->query($filters)->cursor());
        $filename = 'Sampling-'.$filters['date_from'].'-'.$filters['date_to'].'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
