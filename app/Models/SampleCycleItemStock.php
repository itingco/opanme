<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleCycleItemStock extends Model
{
    protected $fillable = [
        'sample_cycle_item_id', 'sample_cycle_warehouse_id', 'item_id',
        'system_qty', 'sales_invoice_date', 'sales_invoice_qty', 'adjusted_system_qty',
        'sales_invoice_details', 'pending_transfer_qty', 'pending_transfer_details',
        'validation_system_qty', 'allocated_physical_qty', 'result',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'system_qty' => 'decimal:4',
        'sales_invoice_date' => 'date',
        'sales_invoice_qty' => 'decimal:4',
        'adjusted_system_qty' => 'decimal:4',
        'sales_invoice_details' => 'array',
        'pending_transfer_qty' => 'decimal:4',
        'pending_transfer_details' => 'array',
        'validation_system_qty' => 'decimal:4',
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
