<?php

namespace App\Services;

use App\Models\ItemBarcode;

class BarcodeExportService
{
    public function __construct(private readonly SimpleXlsxWriter $writer)
    {
    }

    public function export(string $path): void
    {
        $rows = ItemBarcode::query()
            ->orderBy('item_code')
            ->orderBy('uom_code')
            ->orderBy('barcode')
            ->cursor()
            ->map(static fn (ItemBarcode $row) => [
                $row->item_code,
                $row->barcode,
                $row->uom_code,
            ]);

        $this->writer->export(
            $path,
            'Master Barcode',
            ['ItemCode', 'Barcode', 'UOM'],
            $rows
        );
    }
}
