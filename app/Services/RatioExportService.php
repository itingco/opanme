<?php

namespace App\Services;

use App\Models\UomRatio;

class RatioExportService
{
    public function __construct(private readonly SimpleXlsxWriter $writer)
    {
    }

    public function export(string $path): void
    {
        $rows = UomRatio::query()
            ->orderBy('item_code')
            ->orderBy('uom_code')
            ->cursor()
            ->map(static fn (UomRatio $row) => [
                $row->item_code,
                $row->uom_code,
                self::ratioText($row->ratio),
            ]);

        $this->writer->export(
            $path,
            'Master Ratio',
            ['ItemCode', 'UOM', 'Ratio'],
            $rows
        );
    }

    private static function ratioText(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
