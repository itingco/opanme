<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpnameDiscoveredItem extends Model
{
    protected $fillable = [
        'cycle_id',
        'alias_code',
        'item_name',
        'default_uom_code',
        'smallest_uom_code',
        'default_ratio_to_smallest',
        'created_by',
    ];

    protected $casts = [
        'default_ratio_to_smallest' => 'decimal:4',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(StockOpnameCycle::class, 'cycle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(DiscoveredScanTransaction::class, 'discovered_item_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(StockOpnameDiscoveredOverride::class, 'discovered_item_id');
    }
}
