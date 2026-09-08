<?php

namespace App\Models;

use App\Database\AppHubEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UomRatio extends Model
{
    protected $connection = 'apphub';
    protected $table = 'ItemRatio';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $guarded = [];

    private const COLUMN_MAP = [
        'id' => 'id',
        'item_code' => 'ItemCode',
        'uom_code' => 'UOM',
        'ratio' => 'Ratio',
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
            if (in_array($mapped, ['id', 'ItemCode', 'UOM', 'Ratio'], true)) {
                $out[$mapped] = $value;
            }
        }
        return $out;
    }

    public function getItemCodeAttribute(): mixed { return $this->attributes['ItemCode'] ?? null; }
    public function setItemCodeAttribute(mixed $value): void { $this->attributes['ItemCode'] = $value; }

    public function getUomCodeAttribute(): mixed { return $this->attributes['UOM'] ?? null; }
    public function setUomCodeAttribute(mixed $value): void { $this->attributes['UOM'] = $value; }

    public function getRatioAttribute(): mixed { return $this->attributes['Ratio'] ?? null; }
    public function setRatioAttribute(mixed $value): void { $this->attributes['Ratio'] = $value; }

    public function getUpdatedAtAttribute(): mixed { return null; }
}
