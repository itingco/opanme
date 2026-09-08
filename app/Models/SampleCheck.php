<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleCheck extends Model
{
    public const RESULT_MATCH = 'MATCH';
    public const RESULT_MISMATCH = 'MISMATCH';

    protected $fillable = [
        'sample_cycle_id', 'user_id', 'source_database', 'erp_warehouse_id',
        'warehouse_code', 'warehouse_name', 'location', 'barcode', 'item_id',
        'item_code', 'item_name', 'uom_code', 'system_qty', 'physical_qty',
        'result', 'scanned_at',
    ];

    protected $casts = [
        'erp_warehouse_id' => 'integer',
        'system_qty' => 'decimal:4',
        'physical_qty' => 'decimal:4',
        'scanned_at' => 'datetime',
    ];

    public function cycle(): BelongsTo { return $this->belongsTo(SampleCycle::class, 'sample_cycle_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
