<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleCycleItemStock extends Model
{
    protected $fillable = [
        'sample_cycle_item_id', 'sample_cycle_warehouse_id', 'item_id',
        'system_qty', 'allocated_physical_qty', 'result',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'system_qty' => 'decimal:4',
        'allocated_physical_qty' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SampleCycleItem::class, 'sample_cycle_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(SampleCycleWarehouse::class, 'sample_cycle_warehouse_id');
    }
}
