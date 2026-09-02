<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameDiscoveredOverride extends Model
{
    protected $fillable = [
        'cycle_id',
        'warehouse_id',
        'discovered_item_id',
        'input_qty',
        'input_uom_code',
        'smallest_uom_code',
        'ratio_used',
        'override_qty',
        'comment',
        'updated_by',
    ];

    protected $casts = [
        'input_qty' => 'decimal:4',
        'ratio_used' => 'decimal:4',
        'override_qty' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockOpnameDiscoveredItem::class, 'discovered_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(CycleWarehouse::class, 'warehouse_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
