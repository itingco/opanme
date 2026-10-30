<?php

namespace App\Models;

use App\Database\AppHubEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ItemBarcode extends Model
{
    protected $connection = 'apphub';
    protected $table = 'ItemBarcode';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $guarded = [];

    private const COLUMN_MAP = [
        'id' => 'id',
        'item_code' => 'ItemCode',
        'barcode' => 'Barcode',
        'uom_code' => 'UOM',
        // Existing screens still expose sort=updated_at; SQL table has no timestamp.
        'updated_at' => 'id',
    ];

    public function newEloquentBuilder($query): Builder
    {
        return new AppHubEloquentBuilder($query);
    }

    public function appHubColumn(string $column): string
    {
        return self::COLUMN_MAP[$column] ?? $column;
    }

    /**
     * Find master barcodes for an ERP item code.
     *
     * Exact ItemCode is always preferred. If no exact row exists and the code
     * is numeric, retry with numeric equivalence so ERP `03176` can match a
     * legacy/Excel master stored as `3176`. Alphanumeric codes remain exact.
     */
    public static function forItemCode(string $itemCode): Collection
    {
        $itemCode = trim($itemCode);

        if ($itemCode === '') {
            return new Collection();
        }

        $exact = static::query()
            ->where('item_code', $itemCode)
            ->orderBy('uom_code')
            ->orderBy('barcode')
            ->get();

        if ($exact->isNotEmpty() || preg_match('/^\d+$/D', $itemCode) !== 1) {
            return $exact;
        }

        return static::query()
            ->whereRaw(
                'TRY_CONVERT(decimal(38,0), LTRIM(RTRIM([ItemCode]))) = TRY_CONVERT(decimal(38,0), ?)',
                [$itemCode]
            )
            ->orderBy('uom_code')
            ->orderBy('barcode')
            ->get();
    }

    public function appHubWritePayload(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if (in_array($key, ['created_at', 'updated_at'], true)) {
                continue;
            }
            $mapped = $this->appHubColumn((string) $key);
            if (in_array($mapped, ['id', 'ItemCode', 'Barcode', 'UOM'], true)) {
                $out[$mapped] = $value;
            }
        }
        return $out;
    }

    public function getItemCodeAttribute(): mixed { return $this->attributes['ItemCode'] ?? null; }
    public function setItemCodeAttribute(mixed $value): void { $this->attributes['ItemCode'] = $value; }

    public function getBarcodeAttribute(): mixed { return $this->attributes['Barcode'] ?? null; }
    public function setBarcodeAttribute(mixed $value): void { $this->attributes['Barcode'] = $value; }

    public function getUomCodeAttribute(): mixed { return $this->attributes['UOM'] ?? null; }
    public function setUomCodeAttribute(mixed $value): void { $this->attributes['UOM'] = $value; }

    public function getUpdatedAtAttribute(): mixed { return null; }
}
