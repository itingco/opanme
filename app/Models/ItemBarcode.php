<?php

namespace App\Models;

use App\Database\AppHubEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
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
