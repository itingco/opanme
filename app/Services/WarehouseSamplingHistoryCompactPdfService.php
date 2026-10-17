<?php

namespace App\Services;

use Traversable;

/** Laporan ringkas landscape A4 dengan kolom komentar Admin Gudang yang terbaca lengkap. */
class WarehouseSamplingHistoryCompactPdfService
{
    private const WIDTH = 842;
    private const HEIGHT = 595;
    private const LEFT = 24;
    private const RIGHT = 818;
    private const FOOTER_LIMIT = 47;

    public function render(array $filters, array $stats, iterable $rows): string
    {
        $pages = [];
        $content = $this->header($filters, $stats);
        $y = 477.0;
        $hasRows = false;

        foreach ($rows as $row) {
            $hasRows = true;
            // Komentar tidak dipotong: teks di-wrap, tinggi setiap baris menyesuaikan isi.
            $comment = trim((string) ($row->validation_note ?? ''));
            $commentLines = $comment === '' ? ['-'] : $this->wrap($comment, 43);
            $validator = trim((string) ($row->validator_name ?? ''));
            $height = max(36.0, 13.0 + count($commentLines) * 8.3 + ($validator !== '' ? 9.0 : 0.0));

            if ($y - $height < self::FOOTER_LIMIT) {
                $this->footer($content, count($pages) + 1);
                $pages[] = implode("\n", $content);
                $content = $this->header($filters, $stats);
                $y = 477.0;
            }

            $validation = (float) ($row->validation_system_qty ?? $row->warehouse_system_qty ?? 0);
            $final = (float) ($row->warehouse_physical_qty ?? 0);
            $variance = $final - $validation;

            $content[] = $this->text(24, $y, $this->date($row->closed_at ?? null), 5.6, false);
            $content[] = $this->text(68, $y, $this->short((string) ($row->source_database ?? '').' / '.(string) ($row->warehouse_code ?? ''), 19), 5.5, false);
            $content[] = $this->text(140, $y, $this->short((string) $row->item_code, 29), 6.0, true);
            $content[] = $this->text(140, $y - 9.5, $this->short((string) $row->item_name, 41), 5.1, false);
            $content[] = $this->text(301, $y, $this->qty($validation), 5.7, false);
            $content[] = $this->text(347, $y, $this->qty((float) ($row->checker_physical_total ?? 0)), 5.7, false);
            $content[] = $this->text(394, $y, ($row->admin_override_qty ?? null) === null ? '-' : $this->qty((float) $row->admin_override_qty), 5.7, false);
            $content[] = $this->text(444, $y, $this->qty($final), 5.7, true);
            $content[] = $this->text(492, $y, $this->qty($variance), 5.7, false);
            $content[] = $this->text(540, $y, ($row->warehouse_result ?? null) === 'MATCH' ? 'COCOK' : 'SELISIH', 5.7, true);
            $content[] = $this->text(586, $y, $this->short((string) ($row->checker_name ?? '-'), 15), 5.5, false);

            foreach ($commentLines as $index => $line) {
                $content[] = $this->text(647, $y - ($index * 8.3), $line, 6.2, false);
            }
            if ($validator !== '') {
                $content[] = $this->grayText(647, $y - count($commentLines) * 8.3 - 1, 'Admin: '.$this->short($validator, 30), 5.3);
            }
            $y -= $height;
            $content[] = $this->line(self::LEFT, $y + 7, self::RIGHT, $y + 7, 0.88);
        }

        if (! $hasRows) {
            $content[] = $this->text(24, $y, 'Tidak ada data untuk filter yang dipilih.', 9.0, false);
        }
        $this->footer($content, count($pages) + 1);
        $pages[] = implode("\n", $content);
        return $this->pdf($pages);
    }

    private function header(array $filters, array $stats): array
    {
        $c = [];
        $c[] = $this->text(24, 565, 'HISTORY SAMPLING GUDANG - LAPORAN RINGKAS', 12, true);
        $c[] = $this->text(24, 547, 'Tutup: '.($filters['date_from'] ?: 'awal').' s/d '.($filters['date_to'] ?: 'akhir').' | '.($filters['source_database'] ?: 'Semua database'), 7, false);
        $c[] = $this->text(24, 530, 'Periode '.$stats['periods'].' | Item '.$stats['items'].' | Baris '.$stats['rows'].' | Cocok '.$stats['match'].' | Selisih '.$stats['mismatch'], 7, true);
        $c[] = $this->line(24, 514, self::RIGHT, 514);
        $heads = [
            24 => 'Tutup', 68 => 'Gudang', 140 => 'Item', 301 => 'Validasi',
            347 => 'Fisik(*)', 394 => 'Override(*)', 444 => 'Final', 492 => 'Selisih',
            540 => 'Hasil', 586 => 'Checker', 647 => 'Komentar Admin Gudang',
        ];
        foreach ($heads as $x => $label) {
            $c[] = $this->text($x, 501, $label, 5.6, true);
        }
        $c[] = $this->line(24, 492, self::RIGHT, 492);
        return $c;
    }

    private function footer(array &$content, int $page): void
    {
        $content[] = $this->text(24, 27, '(*) Fisik Checker dan Override adalah total gabungan item. Validasi dan Final adalah angka per gudang.', 6.2, false);
        $content[] = $this->text(24, 16, 'Selisih = Qty Final Gudang - Qty Validasi Gudang. Komentar Admin Gudang ditampilkan bila ada.', 5.8, false);
        $content[] = $this->text(777, 16, 'Hal '.$page, 6, false);
    }

    /** Wrap tanpa memotong isi komentar Admin, termasuk jika ada baris baru. */
    private function wrap(string $value, int $maxCharacters): array
    {
        $value = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        $segments = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $lines = [];
        foreach ($segments as $segment) {
            $segment = preg_replace('/[ \t]+/', ' ', trim($segment)) ?? trim($segment);
            if ($segment === '') {
                $lines[] = '';
                continue;
            }
            $words = preg_split('/\s+/', $segment) ?: [];
            $line = '';
            foreach ($words as $word) {
                while (strlen($word) > $maxCharacters) {
                    if ($line !== '') {
                        $lines[] = $line;
                        $line = '';
                    }
                    $lines[] = substr($word, 0, $maxCharacters);
                    $word = substr($word, $maxCharacters);
                }
                if ($word === '') continue;
                if ($line === '') {
                    $line = $word;
                } elseif (strlen($line) + strlen($word) + 1 <= $maxCharacters) {
                    $line .= ' '.$word;
                } else {
                    $lines[] = $line;
                    $line = $word;
                }
            }
            if ($line !== '') $lines[] = $line;
        }
        return $lines ?: ['-'];
    }

    private function date(mixed $value): string
    {
        if (! $value) return '-';
        $ts = strtotime((string) $value);
        return $ts ? date('d/m/Y', $ts) : '-';
    }

    private function qty(float $n): string
    {
        $str = rtrim(rtrim(number_format($n, 4, '.', ','), '0'), '.');
        return $str === '' || $str === '-0' ? '0' : $str;
    }

    private function short(string $value, int $limit): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
        $value = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return strlen($value) > $limit ? substr($value, 0, $limit - 3).'...' : $value;
    }

    private function grayText(float $x, float $y, string $value, float $size): string
    {
        return '0.42 g '.$this->text($x, $y, $value, $size, false).' 0 g';
    }

    private function text(float $x, float $y, string $value, float $size, bool $bold): string
    {
        $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
        return sprintf('BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', $bold ? 'F2' : 'F1', $size, $x, $y, $escaped);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, float $gray = 0.82): string
    {
        return sprintf('%.2F G 0.5 w %.2F %.2F m %.2F %.2F l S', $gray, $x1, $y1, $x2, $y2);
    }

    private function pdf(array $streams): string
    {
        $objs = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
        $kids = [];
        $n = 5;
        foreach ($streams as $stream) {
            $page = $n++;
            $content = $n++;
            $kids[] = "$page 0 R";
            $objs[$page] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::WIDTH.' '.self::HEIGHT."] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $content 0 R >>";
            $objs[$content] = '<< /Length '.strlen($stream).">>\nstream\n$stream\nendstream";
        }
        $objs[2] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objs);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objs as $id => $obj) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$obj\nendobj\n";
        }
        $at = strlen($pdf);
        $max = max(array_keys($objs));
        $pdf .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        $pdf .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R >>\nstartxref\n$at\n%%EOF";
        return $pdf;
    }
}
