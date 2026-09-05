<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class SimpleXlsxWriter
{
    private array $centralEntries = [];
    private $zipHandle = null;

    public function export(string $path, string $sheetName, array $headers, iterable $rows): void
    {
        $sheetPath = tempnam(sys_get_temp_dir(), 'simple_xlsx_sheet_');
        if ($sheetPath === false) {
            throw new RuntimeException('Tidak dapat membuat file sementara untuk Excel.');
        }

        try {
            $lastRow = $this->writeWorksheet($sheetPath, $headers, $rows);
            $this->writePackage($path, $sheetPath, $sheetName, count($headers), $lastRow);
        } finally {
            @unlink($sheetPath);
        }
    }

    private function writeWorksheet(string $sheetPath, array $headers, iterable $rows): int
    {
        $handle = fopen($sheetPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Tidak dapat menulis worksheet Excel.');
        }

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        fwrite($handle, '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        fwrite($handle, '<sheetFormatPr defaultRowHeight="15"/>');
        fwrite($handle, '<sheetData>');

        $this->writeRow($handle, 1, array_map(fn ($value) => ['value' => $value, 'style' => 1], $headers));

        $rowNumber = 2;
        foreach ($rows as $row) {
            $cells = [];
            foreach (array_values($row) as $value) {
                $cells[] = ['value' => $value];
            }
            $this->writeRow($handle, $rowNumber, $cells);
            $rowNumber++;
        }

        fwrite($handle, '</sheetData>');
        if ($rowNumber > 2 && count($headers) > 0) {
            $lastColumn = $this->columnName(count($headers));
            fwrite($handle, '<autoFilter ref="A1:'.$lastColumn.($rowNumber - 1).'"/>');
        }
        fwrite($handle, '</worksheet>');
        fclose($handle);

        return max(1, $rowNumber - 1);
    }

    private function writeRow($handle, int $rowNumber, array $cells): void
    {
        fwrite($handle, '<row r="'.$rowNumber.'">');
        foreach ($cells as $index => $cell) {
            $reference = $this->columnName($index + 1).$rowNumber;
            $style = (int) ($cell['style'] ?? 0);
            $escaped = $this->xml((string) ($cell['value'] ?? ''));
            fwrite($handle, '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$escaped.'</t></is></c>');
        }
        fwrite($handle, '</row>');
    }

    private function writePackage(string $path, string $sheetPath, string $sheetName, int $columnCount, int $lastRow): void
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
            $this->addZipString('xl/workbook.xml', $this->workbookXml($sheetName));
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
        $hash = hash_file('crc32b', $sourcePath);
        if ($size === false || $hash === false) {
            throw new RuntimeException('Worksheet Excel tidak dapat dibaca.');
        }

        $this->addZipEntry($name, $size, (int) hexdec($hash), function () use ($sourcePath) {
            $source = fopen($sourcePath, 'rb');
            if ($source === false) {
                throw new RuntimeException('Worksheet Excel tidak dapat dibuka.');
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

        fwrite($this->zipHandle, pack(
            'VvvvvvVVVvv',
            0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0
        ).$name);
        $writeData();

        $this->centralEntries[] = compact('name', 'size', 'crc', 'offset', 'dosTime', 'dosDate');
    }

    private function finishZip(): void
    {
        $centralOffset = ftell($this->zipHandle);

        foreach ($this->centralEntries as $entry) {
            $name = $entry['name'];
            fwrite($this->zipHandle, pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0, 0,
                $entry['dosTime'], $entry['dosDate'], $entry['crc'], $entry['size'], $entry['size'],
                strlen($name), 0, 0, 0, 0, 0, $entry['offset']
            ).$name);
        }

        $centralEnd = ftell($this->zipHandle);
        $centralSize = $centralEnd - $centralOffset;
        $count = count($this->centralEntries);

        fwrite($this->zipHandle, pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $centralOffset, 0));
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

    private function xml(string $value): string
    {
        $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->xml(substr($sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
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
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Stock Opname</Application></Properties>';
    }

    private function corePropertiesXml(): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>Stock Opname</dc:creator><cp:lastModifiedBy>Stock Opname</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }
}
