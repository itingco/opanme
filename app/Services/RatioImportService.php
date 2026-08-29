<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use App\Models\UomRatio;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class RatioImportService
{
    public function __construct(
        private readonly SimpleSpreadsheetReader $reader,
        private readonly ErpCatalogService $erp,
    ) {
    }

    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw new RuntimeException('Format file tidak didukung. Gunakan template .xlsx atau file .csv.');
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('File upload tidak dapat dibaca.');
        }

        $rows = $this->reader->read($path, $extension);
        [$headerIndex, $columns] = $this->findHeader($rows);

        $result = [
            'total_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $prepared = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $excelRow = $index + 1;
            $database = strtoupper(trim((string) ($row[$columns['database']] ?? '')));
            $barcode = $this->normalizeBarcode((string) ($row[$columns['barcode']] ?? ''));
            $ratioText = trim((string) ($row[$columns['ratio']] ?? ''));

            if ($database === '' && $barcode === '' && $ratioText === '') {
                continue;
            }

            $result['total_rows']++;

            if (! in_array($database, [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI], true)) {
                $this->failure($result, $excelRow, $barcode, 'Database harus AS_INGCO atau AS_SMI.');
                continue;
            }

            if ($barcode === '') {
                $this->failure($result, $excelRow, '', 'Barcode ERP wajib diisi.');
                continue;
            }

            $ratio = $this->normalizeRatio($ratioText);
            if ($ratio === null || $ratio <= 0) {
                $this->failure($result, $excelRow, $barcode, 'Ratio harus berupa angka lebih besar dari 0.');
                continue;
            }

            $prepared[] = [
                'row' => $excelRow,
                'database' => $database,
                'barcode' => $barcode,
                'ratio' => $ratio,
            ];
        }

        if ($prepared === []) {
            return $result;
        }

        $lookups = [];
        foreach ([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI] as $database) {
            $barcodes = array_values(array_unique(array_column(
                array_values(array_filter($prepared, static fn ($row) => $row['database'] === $database)),
                'barcode'
            )));

            if ($barcodes !== []) {
                $lookups[$database] = $this->erp->findBarcodes($database, $barcodes);
            }
        }

        $payloadByKey = [];
        foreach ($prepared as $row) {
            $lookup = $lookups[$row['database']] ?? ['items' => [], 'ambiguous' => []];

            if (in_array($row['barcode'], $lookup['ambiguous'], true)) {
                $this->failure($result, $row['row'], $row['barcode'], 'Barcode ditemukan pada lebih dari satu item ERP.');
                continue;
            }

            $item = $lookup['items'][$row['barcode']] ?? null;
            if ($item === null) {
                $this->failure($result, $row['row'], $row['barcode'], 'Barcode tidak ditemukan di IC_Aliases.');
                continue;
            }

            $key = $row['database'].'|'.$item['item_id'].'|'.$item['uom_level'];
            if (isset($payloadByKey[$key])) {
                if (abs($payloadByKey[$key]['ratio'] - $row['ratio']) > 0.00005) {
                    $this->failure($result, $row['row'], $row['barcode'], 'Item/UOM yang sama sudah ada di file dengan ratio berbeda.');
                } else {
                    $result['skipped']++;
                }
                continue;
            }

            $payloadByKey[$key] = [
                'source_database' => $row['database'],
                'item_id' => $item['item_id'],
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'],
                'uom_level' => $item['uom_level'],
                'uom_code' => $item['uom_code'],
                'ratio' => $row['ratio'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($payloadByKey === []) {
            return $result;
        }

        $existingKeys = $this->existingKeys(array_values($payloadByKey));
        foreach ($payloadByKey as $key => $payload) {
            if (isset($existingKeys[$key])) {
                $result['updated']++;
            } else {
                $result['created']++;
            }
        }

        UomRatio::upsert(
            array_values($payloadByKey),
            ['source_database', 'item_id', 'uom_level'],
            ['item_code', 'item_name', 'uom_code', 'ratio', 'updated_at']
        );

        return $result;
    }

    private function findHeader(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $map = [];
            foreach ($row as $column => $value) {
                $normalized = $this->normalizeHeader((string) $value);
                if (in_array($normalized, ['database', 'db'], true)) {
                    $map['database'] = $column;
                } elseif (in_array($normalized, ['barcode erp', 'barcode', 'aliascode', 'alias code'], true)) {
                    $map['barcode'] = $column;
                } elseif (in_array($normalized, ['ratio', 'ratio ke smallest uom'], true)) {
                    $map['ratio'] = $column;
                }
            }

            if (isset($map['database'], $map['barcode'], $map['ratio'])) {
                return [$index, $map];
            }
        }

        throw new RuntimeException('Header tidak ditemukan. Gunakan kolom: Database, Barcode ERP, Ratio.');
    }

    private function existingKeys(array $payloads): array
    {
        $keys = [];
        foreach ([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI] as $database) {
            $databaseRows = array_values(array_filter($payloads, static fn ($row) => $row['source_database'] === $database));
            $itemIds = array_values(array_unique(array_column($databaseRows, 'item_id')));

            foreach (array_chunk($itemIds, 1000) as $chunk) {
                $existing = UomRatio::query()
                    ->where('source_database', $database)
                    ->whereIn('item_id', $chunk)
                    ->get(['item_id', 'uom_level']);

                foreach ($existing as $ratio) {
                    $keys[$database.'|'.$ratio->item_id.'|'.$ratio->uom_level] = true;
                }
            }
        }

        return $keys;
    }

    private function failure(array &$result, int $row, string $barcode, string $message): void
    {
        $result['failed']++;
        if (count($result['errors']) < 100) {
            $result['errors'][] = [
                'row' => $row,
                'barcode' => $barcode,
                'message' => $message,
            ];
        }
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function normalizeBarcode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[+-]?\d+(?:\.\d+)?[eE][+-]?\d+$/', $value)) {
            return sprintf('%.0f', (float) $value);
        }

        if (preg_match('/^(\d+)\.0+$/', $value, $match)) {
            return $match[1];
        }

        return $value;
    }

    private function normalizeRatio(string $value): ?float
    {
        $value = str_replace(' ', '', trim($value));
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
