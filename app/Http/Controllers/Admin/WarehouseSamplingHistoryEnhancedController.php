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
            'Komentar Admin Gudang', 'Validator Admin Gudang', 'Checker', 'Komentar Checker',
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
                    trim((string) ($row->validation_note ?? '')),
                    $row->validator_name,
                    $row->checker_name,
                    $row->checker_comment,
                ];
            }
        })();
        $writer->export($path, 'History Sampling Ringkas', $headers, $rows);
        $this->formatCommentExcel($path);
        return response()->download(
            $path, 'History-Sampling-Gudang-Ringkas-'.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /**
     * Perapihan hanya untuk Excel History Sampling. Writer XLSX utama tetap
     * dipakai oleh modul lain tanpa perubahan. PharData tersedia bersama PHP/Composer.
     * Jika format tampilan tidak dapat diterapkan, file Excel asli tetap valid.
     */
    private function formatCommentExcel(string $path): void
    {
        if (! class_exists(\PharData::class)) {
            return;
        }

        try {
            $sheetPath = 'phar://'.$path.'/xl/worksheets/sheet1.xml';
            $stylesPath = 'phar://'.$path.'/xl/styles.xml';
            $sheet = file_get_contents($sheetPath);
            $styles = file_get_contents($stylesPath);
            if (! is_string($sheet) || ! is_string($styles)) {
                return;
            }

            // Width mengikuti urutan: periode, item, angka dan komentar Admin (N).
            $widths = [17, 24, 15, 22, 18, 43, 10, 18, 21, 21, 18, 15, 12, 55, 22, 21, 45];
            $cols = '<cols>';
            foreach ($widths as $index => $width) {
                $column = $index + 1;
                $cols .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
            }
            $cols .= '</cols>';
            $newSheet = str_replace('<sheetFormatPr defaultRowHeight="15"/>', '<sheetFormatPr defaultRowHeight="15"/>'.$cols, $sheet);
            if ($newSheet === $sheet) {
                return;
            }

            // Tambah satu style wrapText, tidak merusak dua style bawaan writer.
            $wrapStyle = null;
            $newStyles = preg_replace_callback('/<cellXfs count="(\d+)">(.*?)<\/cellXfs>/s', function (array $match) use (&$wrapStyle): string {
                $wrapStyle = (int) $match[1];
                $wrapped = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>';
                return '<cellXfs count="'.($wrapStyle + 1).'">'.$match[2].$wrapped.'</cellXfs>';
            }, $styles, 1);
            if ($wrapStyle === null || ! is_string($newStyles)) {
                return;
            }

            // N = Komentar Admin Gudang, F = Nama Item, Q = Komentar Checker.
            $newSheet = preg_replace('/<c r="([FNQ])(\d+)" s="0" t="inlineStr">/', '<c r="$1$2" s="'.$wrapStyle.'" t="inlineStr">', $newSheet);
            if (! is_string($newSheet)) {
                return;
            }

            // Height mengikuti panjang teks kolom komentar agar mudah dibaca.
            $newSheet = preg_replace_callback('/<row r="(\d+)">(.*?)<\/row>/s', static function (array $match): string {
                $number = (int) $match[1];
                if ($number === 1) {
                    return '<row r="1" ht="33" customHeight="1">'.$match[2].'</row>';
                }
                if (! preg_match('/<c r="N'.$number.'"[^>]*><is><t[^>]*>(.*?)<\/t><\/is><\/c>/s', $match[2], $cell)) {
                    return $match[0];
                }
                $comment = html_entity_decode($cell[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                $lineCount = max(1, (int) ceil(strlen($comment) / 52));
                $height = min(135, max(21, $lineCount * 16));
                return '<row r="'.$number.'" ht="'.$height.'" customHeight="1">'.$match[2].'</row>';
            }, $newSheet);
            if (! is_string($newSheet)) {
                return;
            }

            $archive = new \PharData($path);
            // Simpan styles lebih dulu: saat sheet merujuk style baru, style sudah tersedia.
            $archive['xl/styles.xml'] = $newStyles;
            $archive['xl/worksheets/sheet1.xml'] = $newSheet;
        } catch (\Throwable $exception) {
            // Export tidak boleh gagal hanya karena styling; data tetap prioritas.
            report($exception);
        }
    }
}
