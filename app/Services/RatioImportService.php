<?php

namespace App\Services;

use App\Models\UomRatio;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class RatioImportService
{
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

        $payloadByKey = [];

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $excelRow = $index + 1;
            $itemCode = $this->normalizeCode((string) ($row[$columns['item_code']] ?? ''));
            $uomCode = $this->normalizeCode((string) ($row[$columns['uom_code']] ?? ''));
            $ratioText = trim((string) ($row[$columns['ratio']] ?? ''));

            if ($itemCode === '' && $uomCode === '' && $ratioText === '') {
                continue;
            }

            $result['total_rows']++;

            if ($itemCode === '') {
                $this->failure($result, $excelRow, '', $uomCode, 'ItemCode wajib diisi.');
                continue;
            }

            if ($uomCode === '') {
                $this->failure($result, $excelRow, $itemCode, '', 'UOM wajib diisi.');
                continue;
            }

            $ratio = $this->normalizeRatio($ratioText);
            if ($ratio === null || $ratio <= 0) {
                $this->failure($result, $excelRow, $itemCode, $uomCode, 'Ratio harus berupa angka lebih besar dari 0.');
                continue;
            }

            $key = $itemCode.'|'.$uomCode;
            if (isset($payloadByKey[$key])) {
                if (abs($payloadByKey[$key]['ratio'] - $ratio) > 0.00005) {
                    $this->failure(
                        $result,
                        $excelRow,
                        $itemCode,
                        $uomCode,
                        'ItemCode/UOM yang sama sudah ada di file dengan ratio berbeda.'
                    );
                } else {
                    $result['skipped']++;
                }
                continue;
            }

            $payloadByKey[$key] = [
                'item_code' => $itemCode,
                'uom_code' => $uomCode,
                'ratio' => $ratio,
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
            ['item_code', 'uom_code'],
            ['ratio', 'updated_at']
        );

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
                } elseif (in_array($normalized, ['uom', 'uom code', 'uom_code'], true)) {
                    $map['uom_code'] = $column;
                } elseif (in_array($normalized, ['ratio', 'ratio ke smallest uom'], true)) {
                    $map['ratio'] = $column;
                }
            }

            if (isset($map['item_code'], $map['uom_code'], $map['ratio'])) {
                return [$index, $map];
            }
        }

        throw new RuntimeException('Header tidak ditemukan. Gunakan kolom: ItemCode, UOM, Ratio.');
    }

    private function existingKeys(array $payloads): array
    {
        $keys = [];
        $itemCodes = array_values(array_unique(array_column($payloads, 'item_code')));

        foreach (array_chunk($itemCodes, 500) as $chunk) {
            $existing = UomRatio::query()
                ->whereIn('item_code', $chunk)
                ->get(['item_code', 'uom_code']);

            foreach ($existing as $ratio) {
                $keys[strtoupper(trim($ratio->item_code)).'|'.strtoupper(trim($ratio->uom_code))] = true;
            }
        }

        return $keys;
    }

    private function failure(array &$result, int $row, string $itemCode, string $uomCode, string $message): void
    {
        $result['failed']++;
        if (count($result['errors']) < 100) {
            $result['errors'][] = [
                'row' => $row,
                'item_code' => $itemCode,
                'uom_code' => $uomCode,
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

    private function normalizeCode(string $value): string
    {
        return strtoupper(trim($value));
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
