<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Read first worksheet of XLSX, without adding a Composer dependency. */
class BulkOverrideExcelReader
{
    public function read(string $path): array
    {
        if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
            throw ValidationException::withMessages(['bulk_file' => 'PHP extension zip dan simplexml diperlukan untuk membaca Excel.']);
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['bulk_file' => 'File XLSX tidak bisa dibuka.']);
        }
        try {
            $strings = [];
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            if ($shared !== false) {
                $xml = $this->xml($shared);
                foreach ($xml->si as $si) {
                    $strings[] = $this->textOf($si);
                }
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) {
                throw ValidationException::withMessages(['bulk_file' => 'Worksheet pertama (sheet1.xml) tidak ditemukan.']);
            }
            $xml = $this->xml($sheet);
            $rows = [];
            foreach ($xml->sheetData->row as $row) {
                $rowNumber = (int) $row['r'];
                if ($rowNumber > 10000) {
                    throw ValidationException::withMessages(['bulk_file' => 'Worksheet terlalu besar (maksimal 10.000 baris posisi).']);
                }
                $values = ['', '', ''];
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    if (!preg_match('/^([A-Z]+)\d+$/i', $ref, $matches)) continue;
                    $column = strtoupper($matches[1]);
                    if (!in_array($column, ['A', 'B', 'C'], true)) continue;
                    $index = ord($column) - ord('A');
                    $type = (string) $cell['t'];
                    if ($type === 's') {
                        $values[$index] = $strings[(int) $cell->v] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $values[$index] = $this->textOf($cell->is);
                    } else {
                        $values[$index] = (string) $cell->v;
                    }
                }
                if (count(array_filter($values, static fn ($v) => trim((string) $v) !== '')) > 0) {
                    $rows[] = ['line' => $rowNumber, 'cols' => $values];
                    if (count($rows) > 501) {
                        throw ValidationException::withMessages(['bulk_file' => 'Maksimal 500 item per file (plus header).']);
                    }
                }
            }
            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function xml(string $source): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($source, \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw ValidationException::withMessages(['bulk_file' => 'Struktur XML Excel tidak valid.']);
            }
            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function textOf(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) return (string) $node->t;
        $text = '';
        foreach ($node->r as $run) $text .= (string) $run->t;
        return $text;
    }
}
