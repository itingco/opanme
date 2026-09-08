<?php

namespace App\Services;

use App\Models\ItemBarcode;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class BarcodeImportService
{
    private const LOOKUP_CHUNK_SIZE = 1000;
    private const UPSERT_CHUNK_SIZE = 500;

    public function __construct(private readonly SimpleSpreadsheetReader $reader)
    {
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

        $payloadByBarcode = [];

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $excelRow = $index + 1;
            $itemCode = $this->normalizeCode((string) ($row[$columns['item_code']] ?? ''));
            $barcode = $this->normalizeBarcode((string) ($row[$columns['barcode']] ?? ''));
            $uomCode = $this->normalizeCode((string) ($row[$columns['uom_code']] ?? ''));

            if ($itemCode === '' && $barcode === '' && $uomCode === '') {
                continue;
            }

            $result['total_rows']++;

            if ($itemCode === '') {
                $this->failure($result, $excelRow, $barcode, 'ItemCode wajib diisi.');
                continue;
            }

            if ($barcode === '') {
                $this->failure($result, $excelRow, '', 'Barcode wajib diisi.');
                continue;
            }

            if ($uomCode === '') {
                $this->failure($result, $excelRow, $barcode, 'UOM wajib diisi.');
                continue;
            }

            if (isset($payloadByBarcode[$barcode])) {
                $existing = $payloadByBarcode[$barcode];
                if ($existing['item_code'] !== $itemCode || $existing['uom_code'] !== $uomCode) {
                    $this->failure(
                        $result,
                        $excelRow,
                        $barcode,
                        'Barcode yang sama muncul di file untuk ItemCode/UOM yang berbeda.'
                    );
                } else {
                    $result['skipped']++;
                }
                continue;
            }

            $payloadByBarcode[$barcode] = [
                'item_code' => $itemCode,
                'barcode' => $barcode,
                'uom_code' => $uomCode,
            ];
        }

        if ($payloadByBarcode === []) {
            return $result;
        }

        $existingByBarcode = [];

        foreach (array_chunk(array_keys($payloadByBarcode), self::LOOKUP_CHUNK_SIZE) as $barcodeChunk) {
            $existingRows = ItemBarcode::query()
                ->whereIn('barcode', $barcodeChunk)
                ->get(['barcode', 'item_code', 'uom_code']);

            foreach ($existingRows as $existingRow) {
                $existingByBarcode[(string) $existingRow->barcode] = $existingRow;
            }
        }

        foreach ($payloadByBarcode as $barcode => $payload) {
            $existing = $existingByBarcode[$barcode] ?? null;
            if (! $existing) {
                $result['created']++;
                continue;
            }

            if (
                strtoupper(trim((string) $existing->item_code)) === $payload['item_code']
                && strtoupper(trim((string) $existing->uom_code)) === $payload['uom_code']
            ) {
                $result['skipped']++;
                unset($payloadByBarcode[$barcode]);
                continue;
            }

            $result['updated']++;
        }

        if ($payloadByBarcode !== []) {
            foreach (array_chunk(array_values($payloadByBarcode), self::UPSERT_CHUNK_SIZE) as $upsertChunk) {
                ItemBarcode::upsert(
                    $upsertChunk,
                    ['barcode'],
                    ['item_code', 'uom_code']
                );
            }
        }

        return $result;
    }

    private function findHeader(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $map = [];
            foreach ($row as $column => $value) {
                $normalized = $this->normalizeHeader((string) $value);

                if (in_array($normalized, ['itemcode', 'item code', 'item_code', 'item'], true)) {
                    $map['item_code'] = $column;
                } elseif (in_array($normalized, ['barcode', 'barcode item', 'barcode_item'], true)) {
                    $map['barcode'] = $column;
                } elseif (in_array($normalized, ['uom', 'uom code', 'uom_code', 'satuan'], true)) {
                    $map['uom_code'] = $column;
                }
            }

            if (isset($map['item_code'], $map['barcode'], $map['uom_code'])) {
                return [$index, $map];
            }
        }

        throw new RuntimeException('Header tidak ditemukan. Gunakan kolom: ItemCode, Barcode, UOM.');
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function normalizeCode(string $value): string
    {
        return strtoupper(trim($value));
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
}
