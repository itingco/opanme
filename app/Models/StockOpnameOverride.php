<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameOverride extends Model
{
    protected $fillable = [
        'cycle_id',
        'warehouse_id',
        'item_id',
        'override_qty',
        'comment',
        'updated_by',
    ];

    protected $casts = [
        'override_qty' => 'decimal:4',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(StockOpnameCycle::class, 'cycle_id');
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
