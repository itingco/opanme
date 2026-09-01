<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use Carbon\CarbonInterface;
use DateTimeInterface;

class CycleFinalPdfService
{
    private const PAGE_WIDTH = 842.0;   // A4 landscape in points
    private const PAGE_HEIGHT = 595.0;
    private const EPSILON = 0.00005;

    /**
     * Generate the final stock-opname report without a third-party PDF package.
     * The finalized detail, override audit, checker signatures, and admin approval
     * are kept together in one downloadable report.
     */
    public function render(StockOpnameCycle $cycle, iterable $rows): string
    {
        $detailPages = [];
        $commands = [];
        $rowTop = 514.0;
        $rowIndex = 0;
        $stats = [
            'rows' => 0,
            'matched' => 0,
            'variance' => 0,
            'overrides' => 0,
            'scan_count' => 0,
            'warehouses' => [],
        ];

        $startDetailPage = function () use (&$commands, &$rowTop, &$rowIndex): void {
            $commands = [];
            $this->drawDetailHeader($commands);
            $rowTop = 514.0;
            $rowIndex = 0;
        };

        $finishDetailPage = function () use (&$commands, &$detailPages): void {
            if ($commands !== []) {
                $detailPages[] = $commands;
            }
        };

        $startDetailPage();

        foreach ($rows as $row) {
            $stats['rows']++;
            $stats['scan_count'] += (int) ($row->scan_count ?? 0);
            $stats['warehouses'][(string) ($row->warehouse_id ?? $row->warehouse_code ?? '')] = true;

            $variance = (float) ($row->variance ?? 0);
            if (abs($variance) > self::EPSILON) {
                $stats['variance']++;
            } else {
                $stats['matched']++;
            }

            if ($row->override_qty !== null) {
                $stats['overrides']++;
            }

            $height = $this->detailRowHeight($row);
            if ($rowTop - $height < 42.0) {
                $finishDetailPage();
                $startDetailPage();
            }

            $this->drawDetailRow($commands, $row, $rowTop, $height, $rowIndex);
            $rowTop -= $height;
            $rowIndex++;
        }

        if ($stats['rows'] === 0) {
            $commands[] = $this->text(28, 486, 'Tidak ada baris hasil stock opname untuk cycle ini.', 9.5, false);
        }
        $finishDetailPage();

        $summaryPage = $this->buildSummaryPage($cycle, $stats);
        $signaturePages = $this->buildSignaturePages($cycle);
        $pages = array_merge([$summaryPage], $detailPages, $signaturePages);

        foreach ($pages as $index => &$pageCommands) {
            $pageCommands[] = $this->text(28, 18, 'Laporan final - data terkunci setelah FINALIZED', 6.4, false);
            $pageCommands[] = $this->textRight(814, 18, 'Hal '.($index + 1).' / '.count($pages), 6.4);
        }
        unset($pageCommands);

        return $this->buildPdf(array_map(fn (array $page) => implode("\n", $page), $pages));
    }

    private function buildSummaryPage(StockOpnameCycle $cycle, array $stats): array
    {
        $c = [];
        $c[] = $this->text(28, 554, 'LAPORAN FINAL STOCK OPNAME', 18, true);
        $c[] = $this->text(28, 536, 'Status FINALIZED - hasil tidak dapat di-override lagi melalui aplikasi', 8.5, false);
        $c[] = $this->line(28, 526, 814, 526, 0.72);

        $meta = [
            ['Cycle', $cycle->cycle_no],
            ['Database', $cycle->source_database],
            ['Cutoff', $cycle->cutoff_date?->format('d/m/Y') ?: '-'],
            ['Mulai', $this->formatDateTime($cycle->started_at)],
            ['Close', $this->formatDateTime($cycle->completed_at)],
            ['Closing Snapshot', $this->formatDateTime($cycle->closing_snapshot_at)],
            ['Finalisasi', $this->formatDateTime($cycle->finalized_at)],
            ['Finalized By', $this->plain($cycle->finalizer?->name ?: '-', 50)],
        ];

        $leftX = 28.0;
        $rightX = 420.0;
        $y = 500.0;
        foreach ($meta as $i => [$label, $value]) {
            $x = $i < 4 ? $leftX : $rightX;
            $rowY = $i < 4 ? $y - ($i * 21) : $y - (($i - 4) * 21);
            $c[] = $this->text($x, $rowY, $label, 7.2, true);
            $c[] = $this->text($x + 92, $rowY, ': '.$this->plain((string) $value, 64), 8.3, false);
        }

        $c[] = $this->text(28, 394, 'RINGKASAN HASIL FINAL', 11.5, true);

        $cards = [
            ['Warehouse', count($stats['warehouses'])],
            ['Baris Item', $stats['rows']],
            ['Sesuai', $stats['matched']],
            ['Selisih', $stats['variance']],
            ['Override Final', $stats['overrides']],
            ['Total Scan', $stats['scan_count']],
        ];

        $cardWidth = 122.0;
        $gap = 9.0;
        foreach ($cards as $i => [$label, $value]) {
            $x = 28 + ($i * ($cardWidth + $gap));
            $c[] = $this->fillRect($x, 326, $cardWidth, 52, 0.97);
            $c[] = $this->rect($x, 326, $cardWidth, 52, 0.82);
            $c[] = $this->text($x + 9, 358, $label, 6.8, true);
            $c[] = $this->text($x + 9, 338, number_format((int) $value), 15, true);
        }

        $c[] = $this->text(28, 294, 'Keterangan', 9.2, true);
        $c[] = $this->text(28, 276, '- Sesuai: Final Fisik sama dengan Opening ERP (toleransi 0.00005).', 7.4, false);
        $c[] = $this->text(28, 260, '- Selisih: Final Fisik berbeda dengan Opening ERP.', 7.4, false);
        $c[] = $this->text(28, 244, '- Final Fisik memakai Qty Override bila ada; jika tidak, memakai hasil Scan Fisik.', 7.4, false);
        $c[] = $this->text(28, 228, '- Closing ERP dan Net Movement dipakai sebagai jejak perubahan stok ERP selama proses opname.', 7.4, false);

        $c[] = $this->fillRect(28, 126, 786, 70, 0.98);
        $c[] = $this->rect(28, 126, 786, 70, 0.84);
        $c[] = $this->text(40, 176, 'PENGESAHAN SISTEM', 9.2, true);
        $c[] = $this->text(40, 158, 'Cycle ini telah difinalisasi dan dikunci pada '.$this->formatDateTime($cycle->finalized_at).'.', 8.0, false);
        $c[] = $this->text(40, 142, 'Setelah status FINALIZED, aplikasi tidak menerima override, penghapusan override, retry closing, assignment, atau scan baru.', 7.3, false);

        $c[] = $this->text(28, 88, 'Detail item dan lembar tanda tangan checker terdapat pada halaman berikutnya.', 8.2, true);

        return $c;
    }

    private function drawDetailHeader(array &$c): void
    {
        $c[] = $this->text(28, 554, 'DETAIL HASIL FINAL STOCK OPNAME', 13, true);
        $c[] = $this->text(28, 538, 'Qty menggunakan smallest UOM yang sama dengan summary aplikasi. Detail Override berisi nama, waktu, dan comment dalam satu kolom.', 7, false);

        $c[] = $this->fillRect(28, 518, 786, 18, 0.93);
        $c[] = $this->rect(28, 518, 786, 18, 0.78);

        $headers = [
            ['Warehouse', 28],
            ['Item', 82],
            ['Item Name', 146],
            ['Opening', 284],
            ['Scan', 332],
            ['Override', 374],
            ['Final', 424],
            ['Variance', 466],
            ['Closing', 514],
            ['Movement', 562],
            ['Scan#', 614],
            ['Detail Override', 656],
        ];

        foreach ($headers as [$label, $x]) {
            $c[] = $this->text($x + 3, 524, $label, $label === 'Detail Override' ? 5.8 : 5.6, true);
        }
    }

    private function detailRowHeight(object $row): float
    {
        $nameLines = $this->wrapPlainText((string) ($row->item_name ?? '-'), 31, 2);
        $overrideLines = $row->override_qty === null
            ? ['-']
            : $this->wrapPlainText($this->formatOverrideDetail($row), 31, 5);

        $lineCount = max(count($nameLines), count($overrideLines), 1);
        return max(22.0, 12.0 + ($lineCount * 8.0));
    }

    private function drawDetailRow(array &$c, object $row, float $top, float $height, int $rowIndex): void
    {
        $bottom = $top - $height;
        if ($rowIndex % 2 === 1) {
            $c[] = $this->fillRect(28, $bottom, 786, $height, 0.975);
        }
        $c[] = $this->line(28, $bottom, 814, $bottom, 0.88);

        $baseline = $top - 13.0;
        $c[] = $this->text(31, $baseline, $this->plain((string) ($row->warehouse_code ?? '-'), 10), 5.8, true);
        $c[] = $this->text(85, $baseline, $this->plain((string) ($row->item_code ?? '-'), 12), 5.8, true);

        $this->drawWrappedText($c, 149, $baseline, (string) ($row->item_name ?? '-'), 31, 2, 5.5, 8.0, false);

        $c[] = $this->textRight(330, $baseline, $this->qty($row->opening_system_qty ?? 0), 5.8);
        $c[] = $this->textRight(372, $baseline, $this->qty($row->physical_qty ?? 0), 5.8);
        $c[] = $this->textRight(422, $baseline, $row->override_qty === null ? '-' : $this->qty($row->override_qty), 5.8);
        $c[] = $this->textRight(464, $baseline, $this->qty($row->final_physical_qty ?? 0), 5.8, true);
        $c[] = $this->textRight(512, $baseline, $this->qty($row->variance ?? 0), 5.8, true);
        $c[] = $this->textRight(560, $baseline, $row->closing_system_qty === null ? '-' : $this->qty($row->closing_system_qty), 5.8);
        $c[] = $this->textRight(612, $baseline, $row->movement_qty === null ? '-' : $this->qty($row->movement_qty), 5.8);
        $c[] = $this->textRight(654, $baseline, number_format((int) ($row->scan_count ?? 0)), 5.8);

        $overrideDetail = $row->override_qty === null ? '-' : $this->formatOverrideDetail($row);
        $this->drawWrappedText($c, 659, $baseline, $overrideDetail, 31, 5, 5.25, 8.0, false);
    }

    private function formatOverrideDetail(object $row): string
    {
        $name = $this->plain((string) ($row->override_by_name ?: 'Admin'), 40);
        $date = $this->formatDateTimeShort($row->override_updated_at ?? null);
        $comment = $this->plain((string) ($row->override_comment ?: '-'), 240);

        return $name.' | '.$date.' - '.$comment;
    }

    /** @return list<array{name:string,warehouses:list<string>}> */
    private function checkerEntries(StockOpnameCycle $cycle): array
    {
        $grouped = [];
        foreach ($cycle->assignments as $assignment) {
            $checker = $assignment->checker;
            if (! $checker) {
                continue;
            }

            $key = (string) ($checker->id ?: $checker->username ?: $checker->name);
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $this->plain((string) ($checker->name ?: $checker->username ?: 'Checker'), 70),
                    'warehouses' => [],
                ];
            }

            $warehouseCode = $assignment->warehouse?->warehouse_code;
            if ($warehouseCode && ! in_array($warehouseCode, $grouped[$key]['warehouses'], true)) {
                $grouped[$key]['warehouses'][] = $this->plain((string) $warehouseCode, 24);
            }
        }

        $entries = array_values($grouped);
        usort($entries, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));
        return $entries;
    }

    /** @return list<array<int,string>> */
    private function buildSignaturePages(StockOpnameCycle $cycle): array
    {
        $entries = $this->checkerEntries($cycle);
        $chunks = $entries === [] ? [[]] : array_chunk($entries, 9);
        $pages = [];
        $lastChunkIndex = count($chunks) - 1;

        foreach ($chunks as $chunkIndex => $chunk) {
            $c = [];
            $c[] = $this->text(28, 554, 'PENGESAHAN CHECKER STOCK OPNAME', 13, true);
            $c[] = $this->text(28, 537, 'Nama checker diambil dari assignment cycle. Kolom di bawah disediakan untuk tanda tangan atau paraf.', 7.2, false);
            $c[] = $this->line(28, 526, 814, 526, 0.76);

            if ($chunk === []) {
                $c[] = $this->text(28, 490, 'Tidak ada checker assignment yang tercatat pada cycle ini.', 8.5, false);
            } else {
                foreach ($chunk as $i => $entry) {
                    $col = $i % 3;
                    $row = intdiv($i, 3);
                    $boxW = 252.0;
                    $boxH = 112.0;
                    $gapX = 15.0;
                    $x = 28.0 + ($col * ($boxW + $gapX));
                    $bottom = [388.0, 264.0, 140.0][$row] ?? 140.0;
                    $this->drawCheckerSignatureBox($c, $x, $bottom, $boxW, $boxH, $entry);
                }
            }

            if ($chunkIndex === $lastChunkIndex) {
                $this->drawFinalizerApproval($c, $cycle);
            } else {
                $c[] = $this->text(28, 82, 'Daftar checker berlanjut pada halaman berikutnya.', 7.2, true);
            }

            $pages[] = $c;
        }

        return $pages;
    }

    /** @param array{name:string,warehouses:list<string>} $entry */
    private function drawCheckerSignatureBox(array &$c, float $x, float $bottom, float $w, float $h, array $entry): void
    {
        $c[] = $this->fillRect($x, $bottom, $w, $h, 0.985);
        $c[] = $this->rect($x, $bottom, $w, $h, 0.80);
        $c[] = $this->text($x + 12, $bottom + $h - 23, $entry['name'], 9.0, true);
        $c[] = $this->text($x + 12, $bottom + $h - 40, 'Checker', 7.0, false);

        $warehouses = $entry['warehouses'] === [] ? '-' : implode(', ', $entry['warehouses']);
        $c[] = $this->text($x + 12, $bottom + $h - 61, 'Warehouse:', 6.5, true);
        $this->drawWrappedText($c, $x + 64, $bottom + $h - 61, $warehouses, 38, 2, 6.5, 9.0, false);

        $lineY = $bottom + 31;
        $c[] = $this->line($x + 28, $lineY, $x + $w - 28, $lineY, 0.35);
        $this->drawCenteredText($c, $x, $w, $bottom + 14, 'Tanda Tangan / Paraf', 6.5, false);
    }

    private function drawFinalizerApproval(array &$c, StockOpnameCycle $cycle): void
    {
        $bottom = 48.0;
        $height = 72.0;
        $c[] = $this->fillRect(28, $bottom, 786, $height, 0.975);
        $c[] = $this->rect(28, $bottom, 786, $height, 0.78);
        $c[] = $this->text(40, $bottom + 51, 'DIPERIKSA / DISAHKAN OLEH', 8.3, true);
        $c[] = $this->text(40, $bottom + 31, 'Admin Finalisasi', 6.7, true);
        $c[] = $this->text(127, $bottom + 31, ': '.$this->plain((string) ($cycle->finalizer?->name ?: '-'), 55), 7.6, false);
        $c[] = $this->text(40, $bottom + 14, 'Tanggal Finalisasi', 6.7, true);
        $c[] = $this->text(127, $bottom + 14, ': '.$this->formatDateTime($cycle->finalized_at), 7.6, false);

        $signatureX1 = 572.0;
        $signatureX2 = 788.0;
        $c[] = $this->line($signatureX1, $bottom + 30, $signatureX2, $bottom + 30, 0.35);
        $this->drawCenteredText($c, $signatureX1, $signatureX2 - $signatureX1, $bottom + 15, 'Tanda Tangan / Paraf Admin', 6.3, false);
        $this->drawCenteredText($c, $signatureX1, $signatureX2 - $signatureX1, $bottom + 3, $this->plain((string) ($cycle->finalizer?->name ?: '-'), 38), 6.6, true);
    }

    private function drawWrappedText(
        array &$c,
        float $x,
        float $y,
        string $text,
        int $maxChars,
        int $maxLines,
        float $size,
        float $lineHeight,
        bool $bold
    ): void {
        foreach ($this->wrapPlainText($text, $maxChars, $maxLines) as $i => $line) {
            $c[] = $this->text($x, $y - ($i * $lineHeight), $line, $size, $bold);
        }
    }

    /** @return list<string> */
    private function wrapPlainText(string $text, int $maxChars, int $maxLines): array
    {
        $clean = $this->plain($text, 1000);
        if ($clean === '') {
            return ['-'];
        }

        $words = preg_split('/\s+/', $clean) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
            $current = strlen($word) <= $maxChars ? $word : substr($word, 0, $maxChars);

            if (count($lines) >= $maxLines - 1) {
                break;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }
        if ($lines === []) {
            $lines = ['-'];
        }

        $reconstructed = implode(' ', $lines);
        if (strlen($clean) > strlen($reconstructed)) {
            $last = count($lines) - 1;
            $lines[$last] = rtrim(substr($lines[$last], 0, max(0, $maxChars - 3))).'...';
        }

        return $lines;
    }

    private function drawCenteredText(array &$c, float $x, float $width, float $y, string $text, float $size, bool $bold): void
    {
        $clean = $this->plain($text, 120);
        $approxWidth = strlen($clean) * $size * 0.50;
        $c[] = $this->text($x + max(0, ($width - $approxWidth) / 2), $y, $clean, $size, $bold);
    }

    private function qty(mixed $value): string
    {
        $formatted = number_format((float) $value, 4, '.', ',');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');
        return $trimmed === '' || $trimmed === '-0' ? '0' : $trimmed;
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i:s');
        }
        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? $this->plain($value, 30) : date('d/m/Y H:i:s', $timestamp);
        }
        return '-';
    }

    private function formatDateTimeShort(mixed $value): string
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('d M Y H:i');
        }
        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? $this->plain($value, 28) : date('d M Y H:i', $timestamp);
        }
        return '-';
    }

    private function plain(string $value, int $maxChars): string
    {
        $cleaned = preg_replace('/\s+/u', ' ', trim($value));
        $value = $cleaned === null ? '' : $cleaned;
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
        if (strlen($value) > $maxChars) {
            $value = substr($value, 0, max(0, $maxChars - 3)).'...';
        }
        return $value;
    }

    private function text(float $x, float $y, string $text, float $size, bool $bold): string
    {
        $font = $bold ? 'F2' : 'F1';
        return sprintf(
            'BT /%s %.2F Tf 0 g %.2F %.2F Td (%s) Tj ET',
            $font,
            $size,
            $x,
            $y,
            $this->escapePdfText($this->plain($text, 500))
        );
    }

    private function textRight(float $rightX, float $y, string $text, float $size, bool $bold = false): string
    {
        $approxWidth = strlen($text) * $size * 0.50;
        return $this->text(max(0, $rightX - $approxWidth), $y, $text, $size, $bold);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, float $gray): string
    {
        return sprintf('%.2F G 0.5 w %.2F %.2F m %.2F %.2F l S', $gray, $x1, $y1, $x2, $y2);
    }

    private function rect(float $x, float $y, float $w, float $h, float $gray): string
    {
        return sprintf('%.2F G 0.7 w %.2F %.2F %.2F %.2F re S', $gray, $x, $y, $w, $h);
    }

    private function fillRect(float $x, float $y, float $w, float $h, float $gray): string
    {
        return sprintf('%.3F g %.2F %.2F %.2F %.2F re f', $gray, $x, $y, $w, $h);
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $text);
    }

    /** @param list<string> $pageStreams */
    private function buildPdf(array $pageStreams): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $kids = [];
        $next = 5;
        foreach ($pageStreams as $stream) {
            $pageObject = $next++;
            $contentObject = $next++;
            $kids[] = $pageObject.' 0 R';

            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentObject
            );
            $objects[$contentObject] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
