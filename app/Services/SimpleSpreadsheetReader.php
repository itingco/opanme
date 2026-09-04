<?php

namespace App\Services;

use PharData;
use RuntimeException;
use Throwable;

class SimpleSpreadsheetReader
{
    /**
     * Read the first worksheet from XLSX, or all rows from CSV.
     * Returns zero-based cell arrays while preserving barcode text.
     */
    public function read(string $path, ?string $extension = null): array
    {
        $extension = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => $this->readXlsx($path),
            'csv', 'txt' => $this->readCsv($path),
            default => throw new RuntimeException('Format file tidak didukung. Gunakan .xlsx atau .csv.'),
        };
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('File import tidak dapat dibaca.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return [];
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);
            $rows = [];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (isset($row[0])) {
                    $row[0] = $this->stripBom((string) $row[0]);
                }
                $rows[] = array_map(static fn ($value) => trim((string) $value), $row);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function readXlsx(string $path): array
    {
        [$archive, $temporaryArchivePath] = $this->openXlsxArchive($path);

        try {
            $workbook = $this->entry($archive, 'xl/workbook.xml');
            $relationships = $this->entry($archive, 'xl/_rels/workbook.xml.rels');

            if (! preg_match('/<sheet\b[^>]*\br:id="([^"]+)"/i', $workbook, $sheetMatch)) {
                throw new RuntimeException('Worksheet pertama tidak ditemukan pada file XLSX.');
            }

            $relationshipId = $sheetMatch[1];
            $sheetTarget = null;

            if (preg_match_all('/<Relationship\b([^>]*)\/?\s*>/i', $relationships, $relationshipMatches)) {
                foreach ($relationshipMatches[1] as $attributesText) {
                    $attributes = $this->attributes($attributesText);
                    if (($attributes['Id'] ?? null) === $relationshipId) {
                        $sheetTarget = $attributes['Target'] ?? null;
                        break;
                    }
                }
            }

            if (! $sheetTarget) {
                throw new RuntimeException('Relasi worksheet XLSX tidak ditemukan.');
            }

            $sheetPath = $this->normalizeWorkbookTarget($sheetTarget);
            $sheetXml = $this->entry($archive, $sheetPath);
            $sharedStrings = $this->sharedStrings($archive);

            if (! preg_match('/<sheetData\b[^>]*>(.*?)<\/sheetData>/si', $sheetXml, $sheetDataMatch)) {
                return [];
            }

            preg_match_all('/<row\b[^>]*>(.*?)<\/row>/si', $sheetDataMatch[1], $rowMatches);
            $rows = [];

            foreach ($rowMatches[1] as $rowXml) {
                $cells = [];
                $maxIndex = -1;

                preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/si', $rowXml, $cellMatches, PREG_SET_ORDER);
                foreach ($cellMatches as $cellMatch) {
                    $attributes = $this->attributes($cellMatch[1]);
                    $reference = $attributes['r'] ?? '';
                    $index = $this->columnIndex($reference);
                    if ($index < 0) {
                        continue;
                    }

                    $type = $attributes['t'] ?? '';
                    $value = $this->cellValue($cellMatch[2], $type, $sharedStrings);
                    $cells[$index] = $value;
                    $maxIndex = max($maxIndex, $index);
                }

                if ($maxIndex < 0) {
                    $rows[] = [];
                    continue;
                }

                $row = [];
                for ($i = 0; $i <= $maxIndex; $i++) {
                    $row[] = $cells[$i] ?? '';
                }
                $rows[] = $row;
            }

            return $rows;
        } finally {
            if ($temporaryArchivePath !== null) {
                @unlink($temporaryArchivePath);
            }
        }
    }

    /**
     * Uploaded files are normally stored by PHP with a temporary filename such
     * as /tmp/phpABC123 (without .xlsx). PharData determines ZIP/XLSX support
     * from the filename extension, so give the uploaded bytes a temporary
     * .xlsx filename before opening them.
     *
     * @return array{0: PharData, 1: ?string}
     */
    private function openXlsxArchive(string $path): array
    {
        $archivePath = $path;
        $temporaryArchivePath = null;
        $pathExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($pathExtension, ['xlsx', 'zip'], true)) {
            $temporaryBase = tempnam(sys_get_temp_dir(), 'stock-opname-xlsx-');
            if ($temporaryBase === false) {
                throw new RuntimeException('File XLSX sementara tidak dapat dibuat.');
            }

            $temporaryArchivePath = $temporaryBase.'.xlsx';
            @unlink($temporaryBase);

            if (! @copy($path, $temporaryArchivePath)) {
                @unlink($temporaryArchivePath);
                throw new RuntimeException('File XLSX upload tidak dapat disiapkan untuk dibaca.');
            }

            $archivePath = $temporaryArchivePath;
        }

        try {
            return [new PharData($archivePath), $temporaryArchivePath];
        } catch (Throwable $e) {
            if ($temporaryArchivePath !== null) {
                @unlink($temporaryArchivePath);
            }

            throw new RuntimeException('File XLSX tidak valid atau tidak dapat dibuka.', 0, $e);
        }
    }

    private function cellValue(string $body, string $type, array $sharedStrings): string
    {
        if ($type === 'inlineStr') {
            return $this->textNodes($body);
        }

        if (! preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $valueMatch)) {
            return '';
        }

        $raw = $this->decodeXml($valueMatch[1]);

        if ($type === 's') {
            return $sharedStrings[(int) $raw] ?? '';
        }

        return trim($raw);
    }

    private function sharedStrings(PharData $archive): array
    {
        $xml = $this->optionalEntry($archive, 'xl/sharedStrings.xml');
        if ($xml === null) {
            return [];
        }

        preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $xml, $matches);

        return array_map(fn ($item) => $this->textNodes($item), $matches[1]);
    }

    private function textNodes(string $xml): string
    {
        preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $xml, $matches);

        return implode('', array_map(fn ($text) => $this->decodeXml($text), $matches[1]));
    }

    private function attributes(string $text): array
    {
        preg_match_all('/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*"([^"]*)"/', $text, $matches, PREG_SET_ORDER);
        $attributes = [];
        foreach ($matches as $match) {
            $attributes[$match[1]] = $this->decodeXml($match[2]);
        }

        return $attributes;
    }

    private function entry(PharData $archive, string $name): string
    {
        $content = $this->optionalEntry($archive, $name);
        if ($content === null) {
            throw new RuntimeException("Komponen XLSX tidak ditemukan: {$name}");
        }

        return $content;
    }

    private function optionalEntry(PharData $archive, string $name): ?string
    {
        try {
            if (! isset($archive[$name])) {
                return null;
            }

            return $archive[$name]->getContent();
        } catch (Throwable $e) {
            throw new RuntimeException("Komponen XLSX tidak dapat dibaca: {$name}", 0, $e);
        }
    }

    private function normalizeWorkbookTarget(string $target): string
    {
        $target = str_replace('\\', '/', $target);
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }
        if (str_starts_with($target, 'xl/')) {
            return $target;
        }

        return 'xl/'.ltrim($target, '/');
    }

    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)\d+$/i', $reference, $match)) {
            return -1;
        }

        $letters = strtoupper($match[1]);
        $number = 0;
        for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
            $number = ($number * 26) + (ord($letters[$i]) - 64);
        }

        return $number - 1;
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($candidates);
        $delimiter = array_key_first($candidates);

        return ($candidates[$delimiter] ?? 0) > 0 ? $delimiter : ',';
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    private function decodeXml(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
