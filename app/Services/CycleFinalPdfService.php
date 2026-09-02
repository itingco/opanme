<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use Carbon\CarbonInterface;
use DateTimeInterface;

class CycleFinalPdfService
{
    private const PAGE_WIDTH = 595.0;   // A4 portrait in points
    private const PAGE_HEIGHT = 842.0;
    private const EPSILON = 0.00005;

    private const LEFT = 28.0;
    private const RIGHT = 567.0;
    private const CONTENT_WIDTH = 539.0;
    private const BOTTOM_LIMIT = 42.0;

    /**
     * Generate a compact final stock-opname report without a third-party PDF package.
     * Summary and detail are merged into the same portrait flow to reduce page count.
     */
    public function render(StockOpnameCycle $cycle, iterable $rows): string
    {
        $rowList = [];
        $stats = [
            'rows' => 0,
            'matched' => 0,
            'variance' => 0,
            'overrides' => 0,
            'non_system' => 0,
            'scan_count' => 0,
            'warehouses' => [],
        ];

        foreach ($rows as $row) {
            $rowList[] = $row;
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
            if (($row->row_type ?? 'ERP') === 'NON_SYSTEM') {
                $stats['non_system']++;
            }
        }

        $warehouseGroups = $this->groupRowsByWarehouse($rowList);
        $pages = $this->buildCompactDetailPages($cycle, $warehouseGroups, $stats);
        $pages = array_merge($pages, $this->buildSignaturePages($cycle));

        $pageCount = count($pages);
        foreach ($pages as $index => &$pageCommands) {
            $pageCommands[] = $this->line(self::LEFT, 27, self::RIGHT, 27, 0.88);
            $pageCommands[] = $this->text(self::LEFT, 15, 'Laporan final - data terkunci setelah FINALIZED', 6.0, false);
            $pageCommands[] = $this->textRight(self::RIGHT, 15, 'Hal '.($index + 1).' / '.$pageCount, 6.0);
        }
        unset($pageCommands);

        return $this->buildPdf(array_map(fn (array $page) => implode("\n", $page), $pages));
    }

    /** @param list<object> $rows
     *  @return list<array{warehouse:string,rows:list<object>}>
     */
    private function groupRowsByWarehouse(array $rows): array
    {
        $groups = [];
        $positions = [];

        foreach ($rows as $row) {
            $warehouse = $this->plain((string) ($row->warehouse_code ?? '-'), 40);
            if (! array_key_exists($warehouse, $positions)) {
                $positions[$warehouse] = count($groups);
                $groups[] = ['warehouse' => $warehouse, 'rows' => []];
            }
            $groups[$positions[$warehouse]]['rows'][] = $row;
        }

        return $groups;
    }

    /**
     * @param list<array{warehouse:string,rows:list<object>}> $warehouseGroups
     * @return list<array<int,string>>
     */
    private function buildCompactDetailPages(StockOpnameCycle $cycle, array $warehouseGroups, array $stats): array
    {
        $pages = [];
        $commands = [];
        $pageNumber = 0;
        $y = 0.0;
        $rowIndex = 0;

        $startPage = function (bool $firstPage) use (&$commands, &$y, &$pageNumber, $cycle, $stats): void {
            $commands = [];
            $pageNumber++;

            if ($firstPage) {
                $y = $this->drawCompactSummary($commands, $cycle, $stats);
            } else {
                $commands[] = $this->text(self::LEFT, 810, 'DETAIL HASIL FINAL STOCK OPNAME', 11.0, true);
                $commands[] = $this->text(self::LEFT, 795, 'ERP: O=Opening, C=Closing, M=Movement | Fisik: S=Scan, O=Override, F=Final', 6.4, false);
                $y = 779.0;
            }
        };

        $finishPage = function () use (&$commands, &$pages): void {
            if ($commands !== []) {
                $pages[] = $commands;
            }
        };

        $startPage(true);

        if ($warehouseGroups === []) {
            $commands[] = $this->text(self::LEFT, $y - 18, 'Tidak ada baris hasil stock opname untuk cycle ini.', 8.0, false);
            $finishPage();
            return $pages;
        }

        foreach ($warehouseGroups as $group) {
            $warehouse = $group['warehouse'];
            $rows = $group['rows'];
            $count = count($rows);
            $offset = 0;

            while ($offset < $count) {
                $needsHeaderSpace = 38.0;
                $nextRowHeight = $this->detailRowHeight($rows[$offset]);
                if ($y - $needsHeaderSpace - $nextRowHeight < self::BOTTOM_LIMIT) {
                    $finishPage();
                    $startPage(false);
                }

                $continuation = $offset > 0;
                $y = $this->drawWarehouseBand($commands, $warehouse, $count, $y, $continuation);
                $y = $this->drawCompactDetailHeader($commands, $y);

                while ($offset < $count) {
                    $row = $rows[$offset];
                    $height = $this->detailRowHeight($row);
                    if ($y - $height < self::BOTTOM_LIMIT) {
                        break;
                    }

                    $this->drawCompactDetailRow($commands, $row, $y, $height, $rowIndex + 1, $rowIndex);
                    $y -= $height;
                    $rowIndex++;
                    $offset++;
                }

                if ($offset < $count) {
                    $finishPage();
                    $startPage(false);
                }
            }

            $y -= 6.0;
        }

        $finishPage();
        return $pages;
    }

    /** Draw report title, compact metadata and summary; return Y for the first warehouse section. */
    private function drawCompactSummary(array &$c, StockOpnameCycle $cycle, array $stats): float
    {
        $c[] = $this->text(self::LEFT, 812, 'LAPORAN FINAL STOCK OPNAME', 14.0, true);
        $c[] = $this->text(self::LEFT, 797, 'Status FINALIZED - data terkunci dan tidak dapat di-override lagi', 6.8, false);
        $c[] = $this->line(self::LEFT, 787, self::RIGHT, 787, 0.78);

        $left = [
            ['Cycle', $cycle->cycle_no],
            ['Database', $cycle->source_database],
            ['Cutoff', $cycle->cutoff_date?->format('d/m/Y') ?: '-'],
            ['Warehouse', number_format(count($stats['warehouses']))],
        ];
        $right = [
            ['Mulai', $this->formatDateTimeMinute($cycle->started_at)],
            ['Close', $this->formatDateTimeMinute($cycle->completed_at)],
            ['Finalisasi', $this->formatDateTimeMinute($cycle->finalized_at)],
            ['Finalized By', $this->plain((string) ($cycle->finalizer?->name ?: '-'), 45)],
        ];

        $rowTop = 772.0;
        for ($i = 0; $i < 4; $i++) {
            $bottom = $rowTop - 18.0;
            $c[] = $this->fillRect(self::LEFT, $bottom, 58, 18, 0.955);
            $c[] = $this->rect(self::LEFT, $bottom, 260, 18, 0.86);
            $c[] = $this->text(self::LEFT + 5, $bottom + 6, $left[$i][0], 6.1, true);
            $c[] = $this->text(self::LEFT + 64, $bottom + 6, ': '.$this->plain((string) $left[$i][1], 48), 6.6, false);

            $rx = 306.0;
            $c[] = $this->fillRect($rx, $bottom, 68, 18, 0.955);
            $c[] = $this->rect($rx, $bottom, self::RIGHT - $rx, 18, 0.86);
            $c[] = $this->text($rx + 5, $bottom + 6, $right[$i][0], 6.1, true);
            $c[] = $this->text($rx + 74, $bottom + 6, ': '.$this->plain((string) $right[$i][1], 43), 6.6, false);
            $rowTop -= 18.0;
        }

        $labels = ['Warehouse', 'Item', 'Sesuai', 'Selisih', 'Non-System', 'Override', 'Total Scan'];
        $values = [
            count($stats['warehouses']),
            $stats['rows'],
            $stats['matched'],
            $stats['variance'],
            $stats['non_system'],
            $stats['overrides'],
            $stats['scan_count'],
        ];
        $cardY = 663.0;
        $cardW = self::CONTENT_WIDTH / 7;
        for ($i = 0; $i < 7; $i++) {
            $x = self::LEFT + ($cardW * $i);
            $c[] = $this->fillRect($x, $cardY, $cardW, 31, $i % 2 === 0 ? 0.975 : 0.955);
            $c[] = $this->rect($x, $cardY, $cardW, 31, 0.84);
            $this->drawCenteredText($c, $x, $cardW, $cardY + 19, $labels[$i], 5.6, false);
            $this->drawCenteredText($c, $x, $cardW, $cardY + 6, number_format((int) $values[$i]), 8.5, true);
        }

        $c[] = $this->fillRect(self::LEFT, 631, self::CONTENT_WIDTH, 23, 0.975);
        $c[] = $this->rect(self::LEFT, 631, self::CONTENT_WIDTH, 23, 0.86);
        $c[] = $this->text(self::LEFT + 6, 643, 'ERP: O=Opening, C=Closing, M=Movement | Fisik: S=Scan, O=Override, F=Final | Var=Final - Opening | NON-SYSTEM=tidak ada di ERP', 5.35, false);
        $c[] = $this->text(self::LEFT, 612, 'DETAIL HASIL FINAL', 8.5, true);

        return 600.0;
    }

    private function drawWarehouseBand(array &$c, string $warehouse, int $rowCount, float $top, bool $continuation): float
    {
        $height = 16.0;
        $bottom = $top - $height;
        $c[] = $this->fillRect(self::LEFT, $bottom, 392, $height, 0.18);
        $c[] = $this->fillRect(420, $bottom, self::RIGHT - 420, $height, 0.90);
        $c[] = $this->rect(self::LEFT, $bottom, self::CONTENT_WIDTH, $height, 0.72);
        $label = $warehouse.($continuation ? ' (lanjutan)' : '');
        $c[] = $this->text(self::LEFT + 6, $bottom + 5.2, $label, 6.6, true, 1.0);
        $c[] = $this->textRight(self::RIGHT - 6, $bottom + 5.2, number_format($rowCount).' item', 5.8);
        return $bottom - 2.0;
    }

    private function drawCompactDetailHeader(array &$c, float $top): float
    {
        $height = 17.0;
        $bottom = $top - $height;
        $c[] = $this->fillRect(self::LEFT, $bottom, self::CONTENT_WIDTH, $height, 0.90);
        $c[] = $this->rect(self::LEFT, $bottom, self::CONTENT_WIDTH, $height, 0.78);

        $headers = [
            ['No', 28, 47],
            ['Item', 47, 115],
            ['Item Name', 115, 282],
            ['ERP O/C/M', 282, 348],
            ['Fisik S/O/F', 348, 414],
            ['Var', 414, 455],
            ['Detail Override', 455, 567],
        ];

        foreach ($headers as [$label, $x1, $x2]) {
            $center = ($x1 + $x2) / 2;
            if (in_array($label, ['No', 'ERP O/C/M', 'Fisik S/O/F', 'Var'], true)) {
                $approx = strlen($label) * 5.2 * 0.50;
                $c[] = $this->text($center - ($approx / 2), $bottom + 5.5, $label, 5.2, true);
            } else {
                $c[] = $this->text($x1 + 3, $bottom + 5.5, $label, 5.2, true);
            }
        }

        foreach ([47, 115, 282, 348, 414, 455] as $x) {
            $c[] = $this->line($x, $bottom, $x, $top, 0.84);
        }

        return $bottom;
    }

    private function detailRowHeight(object $row): float
    {
        $nameLines = $this->wrapPlainText($this->displayItemName($row), 52, 2);
        $overrideLines = $row->override_qty === null
            ? ['-']
            : $this->wrapPlainText($this->formatOverrideDetail($row), 31, 3);

        $lineCount = max(count($nameLines), count($overrideLines), 1);
        return max(12.2, 5.3 + ($lineCount * 6.4));
    }

    private function drawCompactDetailRow(array &$c, object $row, float $top, float $height, int $displayNo, int $rowIndex): void
    {
        $bottom = $top - $height;
        $hasOverride = $row->override_qty !== null;
        if ($hasOverride) {
            $c[] = $this->fillRect(self::LEFT, $bottom, self::CONTENT_WIDTH, $height, 0.965);
        } elseif ($rowIndex % 2 === 1) {
            $c[] = $this->fillRect(self::LEFT, $bottom, self::CONTENT_WIDTH, $height, 0.985);
        }

        $c[] = $this->line(self::LEFT, $bottom, self::RIGHT, $bottom, 0.90);
        foreach ([47, 115, 282, 348, 414, 455] as $x) {
            $c[] = $this->line($x, $bottom, $x, $top, 0.93);
        }

        $baseline = $top - 8.0;
        $this->drawCenteredText($c, 28, 19, $baseline, (string) $displayNo, 5.15, false);
        $c[] = $this->text(50, $baseline, $this->plain((string) ($row->item_code ?? '-'), 20), 5.2, true);
        $this->drawWrappedText($c, 118, $baseline, $this->displayItemName($row), 52, 2, 5.15, 6.4, false);

        $erp = $this->qty($row->opening_system_qty ?? 0)
            .'/'.($row->closing_system_qty === null ? '-' : $this->qty($row->closing_system_qty))
            .'/'.($row->movement_qty === null ? '-' : $this->qty($row->movement_qty));
        $physical = $this->qty($row->physical_qty ?? 0)
            .'/'.($row->override_qty === null ? '-' : $this->qty($row->override_qty))
            .'/'.$this->qty($row->final_physical_qty ?? 0);

        $this->drawCenteredText($c, 282, 66, $baseline, $erp, 5.05, false);
        $this->drawCenteredText($c, 348, 66, $baseline, $physical, 5.05, false);
        $c[] = $this->textRight(451, $baseline, $this->qty($row->variance ?? 0), 5.15, true);

        $overrideDetail = $hasOverride ? $this->formatOverrideDetail($row) : '-';
        $this->drawWrappedText($c, 458, $baseline, $overrideDetail, 31, 3, 4.85, 6.2, false);
    }

    private function formatOverrideDetail(object $row): string
    {
        $name = $this->plain((string) ($row->override_by_name ?: 'Admin'), 40);
        $date = $this->formatDateTimeShort($row->override_updated_at ?? null);
        $comment = $this->plain((string) ($row->override_comment ?: '-'), 240);
        $conversion = '';
        if (($row->row_type ?? 'ERP') === 'NON_SYSTEM' && $row->override_input_qty !== null) {
            $conversion = $this->qty($row->override_input_qty).' '.($row->override_input_uom_code ?: '')
                .' x '.$this->qty($row->override_ratio_used ?? 1)
                .' = '.$this->qty($row->override_qty ?? 0).' '.($row->override_smallest_uom_code ?: $row->smallest_uom_code ?: 'PCS').' | ';
        }

        return $name.' | '.$date.' - '.$conversion.$comment;
    }

    private function displayItemName(object $row): string
    {
        $name = (string) ($row->item_name ?? '-');
        if (($row->row_type ?? 'ERP') === 'NON_SYSTEM') {
            return 'NON-SYSTEM | '.$name;
        }

        return $name;
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
            $c[] = $this->text(self::LEFT, 810, 'PENGESAHAN CHECKER STOCK OPNAME', 12.0, true);
            $c[] = $this->text(self::LEFT, 794, 'Nama checker diambil dari assignment cycle. Kolom di bawah disediakan untuk tanda tangan atau paraf.', 6.5, false);
            $c[] = $this->line(self::LEFT, 783, self::RIGHT, 783, 0.78);

            if ($chunk === []) {
                $c[] = $this->text(self::LEFT, 750, 'Tidak ada checker assignment yang tercatat pada cycle ini.', 7.5, false);
            } else {
                foreach ($chunk as $i => $entry) {
                    $col = $i % 3;
                    $row = intdiv($i, 3);
                    $boxW = 169.0;
                    $boxH = 108.0;
                    $gapX = 16.0;
                    $x = self::LEFT + ($col * ($boxW + $gapX));
                    $bottom = [654.0, 530.0, 406.0][$row] ?? 406.0;
                    $this->drawCheckerSignatureBox($c, $x, $bottom, $boxW, $boxH, $entry);
                }
            }

            if ($chunkIndex === $lastChunkIndex) {
                $this->drawFinalizerApproval($c, $cycle);
            } else {
                $c[] = $this->text(self::LEFT, 82, 'Daftar checker berlanjut pada halaman berikutnya.', 6.5, true);
            }

            $pages[] = $c;
        }

        return $pages;
    }

    /** @param array{name:string,warehouses:list<string>} $entry */
    private function drawCheckerSignatureBox(array &$c, float $x, float $bottom, float $w, float $h, array $entry): void
    {
        $c[] = $this->fillRect($x, $bottom, $w, $h, 0.985);
        $c[] = $this->rect($x, $bottom, $w, $h, 0.82);
        $this->drawCenteredText($c, $x, $w, $bottom + $h - 23, $entry['name'], 7.3, true);
        $this->drawCenteredText($c, $x, $w, $bottom + $h - 39, 'Checker', 6.0, false);

        $warehouses = $entry['warehouses'] === [] ? '-' : implode(', ', $entry['warehouses']);
        $this->drawCenteredText($c, $x + 5, $w - 10, $bottom + $h - 56, 'Warehouse: '.$this->plain($warehouses, 42), 5.5, false);

        $lineY = $bottom + 28;
        $c[] = $this->line($x + 22, $lineY, $x + $w - 22, $lineY, 0.40);
        $this->drawCenteredText($c, $x, $w, $bottom + 13, 'Tanda Tangan / Paraf', 5.7, false);
    }

    private function drawFinalizerApproval(array &$c, StockOpnameCycle $cycle): void
    {
        $bottom = 78.0;
        $height = 250.0;
        $c[] = $this->text(self::LEFT, $bottom + $height + 17, 'DIPERIKSA / DISAHKAN OLEH', 8.0, true);
        $c[] = $this->fillRect(self::LEFT, $bottom + $height - 48, self::CONTENT_WIDTH, 48, 0.975);
        $c[] = $this->rect(self::LEFT, $bottom + $height - 48, self::CONTENT_WIDTH, 48, 0.84);
        $c[] = $this->text(self::LEFT + 8, $bottom + $height - 18, 'Admin Finalisasi', 6.2, true);
        $c[] = $this->text(self::LEFT + 95, $bottom + $height - 18, ': '.$this->plain((string) ($cycle->finalizer?->name ?: '-'), 55), 6.8, false);
        $c[] = $this->text(self::LEFT + 8, $bottom + $height - 36, 'Tanggal Finalisasi', 6.2, true);
        $c[] = $this->text(self::LEFT + 95, $bottom + $height - 36, ': '.$this->formatDateTime($cycle->finalized_at), 6.8, false);

        $boxW = 250.0;
        $boxH = 126.0;
        $boxX = (self::PAGE_WIDTH - $boxW) / 2;
        $boxBottom = $bottom + 36;
        $c[] = $this->rect($boxX, $boxBottom, $boxW, $boxH, 0.82);
        $this->drawCenteredText($c, $boxX, $boxW, $boxBottom + $boxH - 25, $this->plain((string) ($cycle->finalizer?->name ?: '-'), 38), 7.6, true);
        $c[] = $this->line($boxX + 42, $boxBottom + 30, $boxX + $boxW - 42, $boxBottom + 30, 0.40);
        $this->drawCenteredText($c, $boxX, $boxW, $boxBottom + 14, 'Tanda Tangan / Paraf Admin', 5.9, false);
        $c[] = $this->text(self::LEFT, $bottom + 7, 'Status: FINALIZED - dokumen ini menjadi bukti hasil akhir setelah cycle dikunci.', 6.0, false);
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

    private function formatDateTimeMinute(mixed $value): string
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }
        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? $this->plain($value, 30) : date('d/m/Y H:i', $timestamp);
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

    private function text(float $x, float $y, string $text, float $size, bool $bold, float $gray = 0.0): string
    {
        $font = $bold ? 'F2' : 'F1';
        return sprintf(
            'BT /%s %.2F Tf %.3F g %.2F %.2F Td (%s) Tj ET',
            $font,
            $size,
            $gray,
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
