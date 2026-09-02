<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveredScanTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cycle_id',
        'discovered_item_id',
        'scan_session_id',
        'warehouse_id',
        'checker_id',
        'location',
        'input_qty',
        'input_uom_code',
        'smallest_uom_code',
        'ratio_used',
        'physical_qty',
        'scanned_at',
    ];

    protected $casts = [
        'input_qty' => 'decimal:4',
        'ratio_used' => 'decimal:4',
        'physical_qty' => 'decimal:4',
        'scanned_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockOpnameDiscoveredItem::class, 'discovered_item_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(CycleWarehouse::class, 'warehouse_id');
    }
}
