<?php

namespace App\Services;

use Traversable;

class WarehouseSamplingHistoryPdfService
{
    public const CONTENT_TYPE = 'application/pdf';
    private const W = 842.0;
    private const H = 595.0;
    private const L = 24.0;
    private const R = 818.0;

    public function render(array $filters, array $stats, iterable $rows): string
    {
        $list = $rows instanceof Traversable
            ? iterator_to_array($rows, false)
            : (is_array($rows) ? $rows : iterator_to_array($rows, false));

        $chunks = array_chunk($list, 18);
        if ($chunks === []) {
            $chunks = [[]];
        }

        $pages = [];
        foreach ($chunks as $pageIndex => $chunk) {
            $content = [];
            $y = 565.0;
            $content[] = $this->text(self::L, $y, 'LAPORAN HISTORY SAMPLING CEK STOK GUDANG', 13, true); $y -= 17;
            $content[] = $this->text(self::L, $y, $this->filterLine($filters), 6.5, false); $y -= 15;
            $content[] = $this->text(self::L, $y, 'Periode '.$stats['periods'].' | Item '.$stats['items'].' | Baris gudang-item '.$stats['rows'].' | Cocok '.$stats['match'].' | Selisih '.$stats['mismatch'], 7, true); $y -= 12;
            $content[] = $this->line(self::L, $y, self::R, $y); $y -= 11;

            $content[] = $this->text(24, $y, 'Tutup', 5.2, true);
            $content[] = $this->text(65, $y, 'Periode', 5.2, true);
            $content[] = $this->text(145, $y, 'Database / Gudang', 5.2, true);
            $content[] = $this->text(275, $y, 'Item', 5.2, true);
            $content[] = $this->text(475, $y, 'Awal', 5.2, true);
            $content[] = $this->text(520, $y, 'SI', 5.2, true);
            $content[] = $this->text(555, $y, 'Sesudah SI', 5.2, true);
            $content[] = $this->text(615, $y, 'Fisik', 5.2, true);
            $content[] = $this->text(660, $y, 'Selisih', 5.2, true);
            $content[] = $this->text(710, $y, 'Hasil', 5.2, true);
            $content[] = $this->text(755, $y, 'Checker', 5.2, true); $y -= 8;
            $content[] = $this->line(self::L, $y, self::R, $y); $y -= 10;

            foreach ($chunk as $row) {
                $system = (float) ($row->warehouse_system_qty ?? 0);
                $sales = (float) ($row->sales_invoice_qty ?? 0);
                $adjusted = (float) ($row->adjusted_system_qty ?? $system);
                $physical = (float) ($row->warehouse_physical_qty ?? 0);
                $content[] = $this->text(24, $y, $this->dateOnly($row->closed_at ?? null), 4.9, false);
                $content[] = $this->text(65, $y, $this->short((string) $row->cycle_no, 16), 4.9, false);
                $content[] = $this->text(145, $y, $this->short($row->source_database.' / '.$row->warehouse_code, 25), 4.9, false);
                $content[] = $this->text(275, $y, $this->short($row->item_code.' '.$row->item_name, 40), 4.9, false);
                $content[] = $this->text(475, $y, $this->qty($system), 4.9, false);
                $content[] = $this->text(520, $y, $this->qty($sales), 4.9, false);
                $content[] = $this->text(555, $y, $this->qty($adjusted), 4.9, false);
                $content[] = $this->text(615, $y, $this->qty($physical), 4.9, false);
                $content[] = $this->text(660, $y, $this->qty($physical - $adjusted), 4.9, false);
                $content[] = $this->text(710, $y, $row->warehouse_result === 'MATCH' ? 'COCOK' : 'SELISIH', 4.9, true);
                $content[] = $this->text(755, $y, $this->short((string) ($row->checker_name ?? '-'), 12), 4.9, false);

                $comment = trim((string) ($row->checker_comment ?? ''));
                if ($comment !== '') {
                    $content[] = $this->text(275, $y - 8, 'Catatan: '.$this->short($comment, 60), 4.5, false);
                }

                $y -= 24;
                $content[] = $this->line(self::L, $y + 8, self::R, $y + 8, 0.92);
            }

            $content[] = $this->text(self::L, 16, 'Data hanya berasal dari periode Sampling Gudang yang sudah CLOSED.', 5.2, false);
            $content[] = $this->text(765, 16, 'Hal '.($pageIndex + 1).' / '.count($chunks), 5.2, false);
            $pages[] = implode("\n", $content);
        }

        return $this->buildPdf($pages);
    }

    private function filterLine(array $filters): string
    {
        $date = ($filters['date_from'] ?: 'awal').' s/d '.($filters['date_to'] ?: 'akhir');
        $database = $filters['source_database'] ?: 'Semua DB';
        $result = $filters['result'] === 'MATCH' ? 'Cocok' : ($filters['result'] === 'MISMATCH' ? 'Selisih' : 'Semua hasil');
        $search = $filters['q'] !== '' ? ' | Cari: '.$filters['q'] : '';
        return 'Tanggal tutup: '.$date.' | '.$database.' | '.$result.$search;
    }

    private function dateOnly(mixed $value): string
    {
        if (! $value) return '-';
        $timestamp = strtotime((string) $value);
        return $timestamp ? date('d/m/Y', $timestamp) : '-';
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ','), '0'), '.') ?: '0';
    }

    private function short(string $value, int $max): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        $value = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return strlen($value) > $max ? substr($value, 0, $max - 3).'...' : $value;
    }

    private function esc(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
    }

    private function text(float $x, float $y, string $text, float $size, bool $bold): string
    {
        return sprintf('BT /%s %.2F Tf 0 g %.2F %.2F Td (%s) Tj ET', $bold ? 'F2' : 'F1', $size, $x, $y, $this->esc($this->short($text, 500)));
    }

    private function line(float $x1, float $y1, float $x2, float $y2, float $gray = 0.82): string
    {
        return sprintf('%.2F G 0.5 w %.2F %.2F m %.2F %.2F l S', $gray, $x1, $y1, $x2, $y2);
    }

    /** @param list<string> $streams */
    private function buildPdf(array $streams): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $kids = [];
        $next = 5;
        foreach ($streams as $stream) {
            $page = $next++;
            $content = $next++;
            $kids[] = "$page 0 R";
            $objects[$page] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::W,
                self::H,
                $content
            );
            $objects[$content] = "<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "$number 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}
