<?php

namespace App\Http\Controllers\Admin;

use App\Services\SimpleXlsxWriter;
use App\Services\WarehouseSamplingHistoryEnhancedService;
use App\Services\WarehouseSamplingHistoryPdfService;
use App\Services\WarehouseSamplingHistoryCompactPdfService;
use App\Services\WarehouseSamplingHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class WarehouseSamplingHistoryEnhancedController extends WarehouseSamplingHistoryController
{
    private function source(): WarehouseSamplingHistoryEnhancedService
    {
        return app(WarehouseSamplingHistoryEnhancedService::class);
    }

    public function index(Request $request, WarehouseSamplingHistoryService $history): View
    {
        $history = $this->source();
        $filters = $history->filters($request);
        return view('warehouse_sampling.history.compact', [
            'filters' => $filters,
            'rows' => $history->orderedQuery($request->user(), $filters)->paginate(100)->withQueryString(),
            'stats' => $history->stats($request->user(), $filters),
            'warehouses' => $history->warehouseOptions($request->user()),
            'checkers' => $history->checkerOptions($request->user()),
        ]);
    }

    public function exportPdf(Request $request, WarehouseSamplingHistoryService $history, WarehouseSamplingHistoryPdfService $pdf): Response
    {
        $history = $this->source();
        $filters = $history->filters($request);
        $content = app(WarehouseSamplingHistoryCompactPdfService::class)->render(
            $filters, $history->stats($request->user(), $filters),
            $history->orderedQuery($request->user(), $filters)->cursor()
        );
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="History-Sampling-Gudang-Ringkas-'.now()->format('Ymd-His').'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function exportExcel(Request $request, WarehouseSamplingHistoryService $history, SimpleXlsxWriter $writer): BinaryFileResponse
    {
        $history = $this->source();
        $filters = $history->filters($request);
        $path = tempnam(sys_get_temp_dir(), 'ws_compact_');
        abort_if($path === false, 500, 'Gagal membuat file export.');
        @unlink($path);
        $path .= '.xlsx';
        $headers = [
            'Tanggal Tutup', 'Periode', 'Database', 'Gudang', 'Kode Item', 'Nama Item', 'UOM',
            'Qty Validasi Gudang', 'Qty Fisik Checker Total', 'Override Admin Total',
            'Qty Final Gudang', 'Selisih Gudang', 'Hasil',
            'Checker', 'Validator', 'Komentar Checker', 'Komentar Admin Gudang',
        ];
        $rows = (function () use ($history, $request, $filters) {
            foreach ($history->orderedQuery($request->user(), $filters)->cursor() as $row) {
                $validated = (float)($row->validation_system_qty ?? $row->warehouse_system_qty ?? 0);
                $final = (float)($row->warehouse_physical_qty ?? 0);
                yield [
                    $row->closed_at ? date('Y-m-d H:i:s', strtotime((string)$row->closed_at)) : '',
                    $row->cycle_no,
                    $row->source_database,
                    $row->warehouse_code,
                    $row->item_code,
                    $row->item_name,
                    $row->uom_code,
                    $validated,
                    (float)($row->checker_physical_total ?? 0),
                    $row->admin_override_qty === null ? '' : (float)$row->admin_override_qty,
                    $final,
                    round($final - $validated, 4),
                    $row->warehouse_result === 'MATCH' ? 'COCOK' : 'SELISIH',
                    $row->checker_name,
                    $row->validator_name,
                    $row->checker_comment,
                    $row->validation_note,
                ];
            }
        })();
        $writer->export($path, 'History Sampling Ringkas', $headers, $rows);
        return response()->download(
            $path, 'History-Sampling-Gudang-Ringkas-'.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }
}
