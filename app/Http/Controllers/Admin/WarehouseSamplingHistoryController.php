<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SimpleXlsxWriter;
use App\Services\WarehouseSamplingHistoryPdfService;
use App\Services\WarehouseSamplingHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class WarehouseSamplingHistoryController extends Controller
{
    public function index(Request $request, WarehouseSamplingHistoryService $history): View
    {
        $filters = $history->filters($request);
        $rows = $history->orderedQuery($request->user(), $filters)
            ->paginate(100)
            ->withQueryString();

        return view('warehouse_sampling.history.index', [
            'filters' => $filters,
            'rows' => $rows,
            'stats' => $history->stats($request->user(), $filters),
            'warehouses' => $history->warehouseOptions($request->user()),
            'checkers' => $history->checkerOptions($request->user()),
        ]);
    }

    public function exportPdf(
        Request $request,
        WarehouseSamplingHistoryService $history,
        WarehouseSamplingHistoryPdfService $pdf
    ): Response {
        $filters = $history->filters($request);
        $contents = $pdf->render(
            $filters,
            $history->stats($request->user(), $filters),
            $history->orderedQuery($request->user(), $filters)->cursor()
        );

        $filename = 'History-Sampling-Gudang-'.now()->format('Ymd-His').'.pdf';
        return response($contents, 200, [
            'Content-Type' => WarehouseSamplingHistoryPdfService::CONTENT_TYPE,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function exportExcel(
        Request $request,
        WarehouseSamplingHistoryService $history,
        SimpleXlsxWriter $writer
    ): BinaryFileResponse {
        $filters = $history->filters($request);
        $path = tempnam(sys_get_temp_dir(), 'ws_history_');
        if ($path === false) {
            abort(500, 'Tidak dapat membuat file sementara export Excel.');
        }
        @unlink($path);
        $path .= '.xlsx';

        $headers = [
            'Tanggal Tutup', 'No Periode', 'Area / Lokasi', 'Dibuat Oleh',
            'Checker', 'Waktu Checker', 'Validator', 'Waktu Validasi',
            'Database', 'Warehouse ID', 'Kode Gudang', 'Nama Gudang',
            'Line', 'Kode Item', 'Nama Item', 'UOM',
            'Total Sistem Gabungan', 'Fisik Total Checker',
            'Stok Sistem Gudang', 'Fisik Alokasi Gudang', 'Selisih Gudang', 'Hasil Gudang',
            'Komentar Checker', 'Catatan Validasi',
        ];

        $rows = (function () use ($history, $request, $filters) {
            foreach ($history->orderedQuery($request->user(), $filters)->cursor() as $row) {
                $system = (float) ($row->warehouse_system_qty ?? 0);
                $physical = (float) ($row->warehouse_physical_qty ?? 0);
                yield [
                    $this->dateTime($row->closed_at),
                    $row->cycle_no,
                    $row->location,
                    $row->creator_name,
                    $row->checker_name,
                    $this->dateTime($row->checked_at),
                    $row->validator_name,
                    $this->dateTime($row->validated_at),
                    $row->source_database,
                    (int) $row->erp_warehouse_id,
                    $row->warehouse_code,
                    $row->warehouse_name,
                    (int) $row->line_no,
                    $row->item_code,
                    $row->item_name,
                    $row->uom_code,
                    $this->number($row->total_system_qty),
                    $this->number($row->checker_physical_total),
                    $system,
                    $physical,
                    round($physical - $system, 4),
                    $row->warehouse_result === 'MATCH' ? 'COCOK' : ($row->warehouse_result === 'MISMATCH' ? 'SELISIH' : ''),
                    $row->checker_comment,
                    $row->validation_note,
                ];
            }
        })();

        $writer->export($path, 'History Sampling', $headers, $rows);

        return response()->download(
            $path,
            'History-Sampling-Gudang-'.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function number(mixed $value): float|string
    {
        if ($value === null || $value === '') return '';
        return round((float) $value, 4);
    }

    private function dateTime(mixed $value): string
    {
        if (! $value) return '';
        $timestamp = strtotime((string) $value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : (string) $value;
    }
}
