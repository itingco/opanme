<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SampleCycleWarehouse extends Model
{
    protected $fillable = [
        'sample_cycle_id', 'source_database', 'erp_warehouse_id',
        'warehouse_code', 'warehouse_name',
    ];

    protected $casts = [
        'erp_warehouse_id' => 'integer',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SampleCycle::class, 'sample_cycle_id');
    }

    public function itemStocks(): HasMany
    {
        return $this->hasMany(SampleCycleItemStock::class, 'sample_cycle_warehouse_id');
    }
}
