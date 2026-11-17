<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Read first worksheet XLSX without composer dependencies. */
class BulkOverrideExcelReader
{
    public function read(string $path): array
    {
        if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
            throw ValidationException::withMessages(['bulk_file' => 'PHP extensions zip dan simplexml wajib aktif.']);
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw ValidationException::withMessages(['bulk_file' => 'File XLSX tidak dapat dibuka.']);
        try {
            $strings = [];
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            if ($shared !== false) foreach ($this->xml($shared)->si as $si) $strings[] = $this->textOf($si);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) throw ValidationException::withMessages(['bulk_file' => 'Worksheet pertama tidak ditemukan (sheet1.xml).']);
            $rows = [];
            foreach ($this->xml($sheet)->sheetData->row as $row) {
                $number = (int) $row['r'];
                if ($number > 20000) throw ValidationException::withMessages(['bulk_file' => 'Posisi row Excel melewati batas 20.000.']);
                $values = ['', '', ''];
                foreach ($row->c as $cell) {
                    if (!preg_match('/^([A-Z]+)\d+$/i', (string) $cell['r'], $match)) continue;
                    $column = strtoupper($match[1]);
                    if (!in_array($column, ['A','B','C'], true)) continue;
                    $index = ord($column)-65;
                    $type = (string) $cell['t'];
                    $values[$index] = $type === 's' ? ($strings[(int) $cell->v] ?? '') : ($type === 'inlineStr' ? $this->textOf($cell->is) : (string) $cell->v);
                }
                if (count(array_filter($values, static fn ($v) => trim((string) $v) !== '')) === 0) continue;
                $rows[] = ['line' => $number, 'cols' => $values];
                if (count($rows) > 2002) throw ValidationException::withMessages(['bulk_file' => 'Maksimal 2.000 item per file Excel (di luar header).']);
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
            if ($xml === false) throw ValidationException::withMessages(['bulk_file' => 'XML pada file Excel tidak valid.']);
            return $xml;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
    private function textOf(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) return (string) $node->t;
        $text = '';
        foreach ($node->r as $run) $text .= (string) $run->t;
        return $text;
    }
}
