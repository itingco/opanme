<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use DateTimeInterface;
use RuntimeException;
use Throwable;

class CycleSummaryExcelService
{
    private array $centralEntries = [];
    private $zipHandle = null;

    public function export(
        string $path,
        StockOpnameCycle $cycle,
        iterable $rows,
        ?string $warehouseLabel,
        bool $varianceOnly
    ): void {
        $sheetPath = tempnam(sys_get_temp_dir(), 'opname_sheet_');
        if ($sheetPath === false) {
            throw new RuntimeException('Tidak dapat membuat file sementara untuk Excel.');
        }

        try {
            $lastRow = $this->writeWorksheet($sheetPath, $cycle, $rows, $warehouseLabel, $varianceOnly);
            $this->writePackage($path, $sheetPath, $lastRow);
        } finally {
            @unlink($sheetPath);
        }
    }

    private function writeWorksheet(
        string $sheetPath,
        StockOpnameCycle $cycle,
        iterable $rows,
        ?string $warehouseLabel,
        bool $varianceOnly
    ): int {
        $handle = fopen($sheetPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Tidak dapat menulis worksheet Excel.');
        }

        $headers = [
            'Row Type',
            'Warehouse Code',
            'Warehouse Name',
            'Item Code',
            'Item Name',
            'Smallest UOM',
            'Opening ERP',
            'Scan Fisik',
            'Override',
            'Final Fisik',
            'Variance',
            'Closing ERP',
            'Net Movement',
            'Scan Count',
            'Movement Status',
            'Override Input Qty',
            'Override Input UOM',
            'Override Ratio',
            'Comment Override',
            'Override By',
            'Override At',
        ];

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        fwrite($handle, '<sheetViews><sheetView workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        fwrite($handle, '<sheetFormatPr defaultRowHeight="15"/>');
        fwrite($handle, '<cols>');
        $widths = [14, 16, 24, 18, 38, 14, 16, 16, 16, 16, 16, 16, 16, 12, 18, 18, 18, 16, 42, 20, 22];
        foreach ($widths as $index => $width) {
            $col = $index + 1;
            fwrite($handle, '<col min="'.$col.'" max="'.$col.'" width="'.$width.'" customWidth="1"/>');
        }
        fwrite($handle, '</cols><sheetData>');

        $this->writeRow($handle, 1, [['value' => 'Stock Opname Summary - '.$cycle->cycle_no, 'style' => 3]]);
        $this->writeRow($handle, 2, [
            ['value' => 'Cycle', 'style' => 4], ['value' => $cycle->cycle_no], null,
            ['value' => 'Database', 'style' => 4], ['value' => $cycle->source_database], null,
            ['value' => 'Status', 'style' => 4], ['value' => $cycle->status],
        ]);
        $this->writeRow($handle, 3, [
            ['value' => 'Cutoff', 'style' => 4], ['value' => $this->dateText($cycle->cutoff_date)], null,
            ['value' => 'Mulai', 'style' => 4], ['value' => $this->dateTimeText($cycle->started_at)], null,
            ['value' => 'Selesai', 'style' => 4], ['value' => $this->dateTimeText($cycle->completed_at)],
        ]);
        $this->writeRow($handle, 4, [
            ['value' => 'Filter Warehouse', 'style' => 4], ['value' => $warehouseLabel ?: 'Semua Warehouse'], null,
            ['value' => 'Hanya Selisih', 'style' => 4], ['value' => $varianceOnly ? 'Ya' : 'Tidak'], null,
            ['value' => 'Exported At', 'style' => 4], ['value' => now()->format('Y-m-d H:i:s')],
        ]);
        $this->writeRow($handle, 6, array_map(fn ($header) => ['value' => $header, 'style' => 1], $headers));

        $rowNumber = 7;
        foreach ($rows as $row) {
            $movementKnown = $row->closing_system_qty !== null;
            $hasMovement = $movementKnown && abs((float) $row->movement_qty) > 0.00005;
            $hasOverride = $row->override_qty !== null;

            $this->writeRow($handle, $rowNumber, [
                ['value' => $row->row_type === 'NON_SYSTEM' ? 'NON-SYSTEM' : 'ERP'],
                ['value' => $row->warehouse_code],
                ['value' => $row->warehouse_name],
                ['value' => $row->item_code],
                ['value' => $row->item_name, 'style' => 5],
                ['value' => $row->smallest_uom_code ?: ''],
                ['value' => (float) $row->opening_system_qty, 'style' => 2, 'number' => true],
                ['value' => (float) $row->physical_qty, 'style' => 2, 'number' => true],
                $hasOverride ? ['value' => (float) $row->override_qty, 'style' => 2, 'number' => true] : null,
                ['value' => (float) $row->final_physical_qty, 'style' => 2, 'number' => true],
                ['value' => (float) $row->variance, 'style' => 2, 'number' => true],
                $movementKnown ? ['value' => (float) $row->closing_system_qty, 'style' => 2, 'number' => true] : null,
                $movementKnown ? ['value' => (float) $row->movement_qty, 'style' => 2, 'number' => true] : null,
                ['value' => (int) $row->scan_count, 'style' => 6, 'number' => true],
                ['value' => $movementKnown ? ($hasMovement ? 'ADA MOVEMENT' : 'TIDAK ADA') : 'BELUM ADA'],
                $row->override_input_qty !== null ? ['value' => (float) $row->override_input_qty, 'style' => 2, 'number' => true] : null,
                ['value' => $row->override_input_uom_code ?: ''],
                $row->override_ratio_used !== null ? ['value' => (float) $row->override_ratio_used, 'style' => 2, 'number' => true] : null,
                ['value' => $row->override_comment ?: '', 'style' => 5],
                ['value' => $row->override_by_name ?: ''],
                ['value' => $row->override_updated_at ? $this->dateTimeText($row->override_updated_at) : ''],
            ]);

            $rowNumber++;
        }

        fwrite($handle, '</sheetData>');
        // SpreadsheetML CT_Worksheet requires autoFilter before mergeCells.
        // Writing these in the opposite order makes Microsoft Excel repair the workbook.
        if ($rowNumber > 7) {
            fwrite($handle, '<autoFilter ref="A6:U'.($rowNumber - 1).'"/>');
        }
        fwrite($handle, '<mergeCells count="1"><mergeCell ref="A1:U1"/></mergeCells>');
        fwrite($handle, '</worksheet>');
        fclose($handle);

        return max(6, $rowNumber - 1);
    }

    private function writeRow($handle, int $rowNumber, array $cells): void
    {
        fwrite($handle, '<row r="'.$rowNumber.'">');

        foreach ($cells as $index => $cell) {
            if ($cell === null) {
                continue;
            }

            $column = $this->columnName($index + 1);
            $reference = $column.$rowNumber;
            $style = (int) ($cell['style'] ?? 0);
            $value = $cell['value'] ?? '';

            if (($cell['number'] ?? false) === true) {
                fwrite($handle, '<c r="'.$reference.'" s="'.$style.'"><v>'.$this->numberValue($value).'</v></c>');
                continue;
            }

            $escaped = $this->xml((string) $value);
            fwrite($handle, '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$escaped.'</t></is></c>');
        }

        fwrite($handle, '</row>');
    }

    private function writePackage(string $path, string $sheetPath, int $lastRow): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Tidak dapat membuat folder export Excel.');
        }

        $this->zipHandle = fopen($path, 'wb');
        if ($this->zipHandle === false) {
            throw new RuntimeException('Tidak dapat membuat file Excel.');
        }
        $this->centralEntries = [];

        try {
            $this->addZipString('[Content_Types].xml', $this->contentTypesXml());
            $this->addZipString('_rels/.rels', $this->rootRelationshipsXml());
            $this->addZipString('docProps/app.xml', $this->appPropertiesXml());
            $this->addZipString('docProps/core.xml', $this->corePropertiesXml());
            $this->addZipString('xl/workbook.xml', $this->workbookXml());
            $this->addZipString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
            $this->addZipString('xl/styles.xml', $this->stylesXml());
            $this->addZipFile('xl/worksheets/sheet1.xml', $sheetPath);
            $this->finishZip();
        } catch (Throwable $e) {
            fclose($this->zipHandle);
            $this->zipHandle = null;
            @unlink($path);
            throw $e;
        }

        fclose($this->zipHandle);
        $this->zipHandle = null;
    }

    private function addZipString(string $name, string $content): void
    {
        $crc = crc32($content);
        if ($crc < 0) {
            $crc += 4294967296;
        }

        $this->addZipEntry($name, strlen($content), $crc, function () use ($content) {
            fwrite($this->zipHandle, $content);
        });
    }

    private function addZipFile(string $name, string $sourcePath): void
    {
        $size = filesize($sourcePath);
        if ($size === false) {
            throw new RuntimeException('Tidak dapat membaca ukuran worksheet Excel.');
        }

        $hash = hash_file('crc32b', $sourcePath);
        if ($hash === false) {
            throw new RuntimeException('Tidak dapat menghitung checksum worksheet Excel.');
        }
        $crc = (int) hexdec($hash);

        $this->addZipEntry($name, $size, $crc, function () use ($sourcePath) {
            $source = fopen($sourcePath, 'rb');
            if ($source === false) {
                throw new RuntimeException('Tidak dapat membaca worksheet Excel.');
            }
            stream_copy_to_stream($source, $this->zipHandle);
            fclose($source);
        });
    }

    private function addZipEntry(string $name, int $size, int $crc, callable $writeData): void
    {
        [$dosTime, $dosDate] = $this->dosDateTime();
        $offset = ftell($this->zipHandle);
        $nameLength = strlen($name);

        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0
        );

        fwrite($this->zipHandle, $localHeader.$name);
        $writeData();

        $this->centralEntries[] = compact('name', 'size', 'crc', 'offset', 'dosTime', 'dosDate');
    }

    private function finishZip(): void
    {
        $centralOffset = ftell($this->zipHandle);

        foreach ($this->centralEntries as $entry) {
            $name = $entry['name'];
            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                $entry['dosTime'],
                $entry['dosDate'],
                $entry['crc'],
                $entry['size'],
                $entry['size'],
                strlen($name),
                0,
                0,
                0,
                0,
                0,
                $entry['offset']
            );
            fwrite($this->zipHandle, $centralHeader.$name);
        }

        $centralEnd = ftell($this->zipHandle);
        $centralSize = $centralEnd - $centralOffset;
        $count = count($this->centralEntries);

        fwrite($this->zipHandle, pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $centralOffset,
            0
        ));
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function numberValue(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function xml(string $value): string
    {
        $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function dateText(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value ? (string) $value : '-';
    }

    private function dateTimeText(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value ? (string) $value : '-';
    }

    private function dosDateTime(): array
    {
        $timestamp = getdate();
        $year = max(1980, (int) $timestamp['year']);
        $dosTime = ((int) $timestamp['hours'] << 11) | ((int) $timestamp['minutes'] << 5) | ((int) $timestamp['seconds'] >> 1);
        $dosDate = (($year - 1980) << 9) | ((int) $timestamp['mon'] << 5) | (int) $timestamp['mday'];

        return [$dosTime, $dosDate];
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>Stock Opname Web</Application><AppVersion>1.0</AppVersion>'
            .'</Properties>';
    }

    private function corePropertiesXml(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>Stock Opname Web</dc:creator><cp:lastModifiedBy>Stock Opname Web</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView/></bookViews><sheets><sheet name="Summary" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.0000"/></numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FF17365D"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF175CD3"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFD0D5DD"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment horizontal="right"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment wrapText="1" vertical="top"/></xf>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment horizontal="right"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
