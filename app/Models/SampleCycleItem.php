<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleCycleItem extends Model
{
    protected $fillable = [
        'sample_cycle_id', 'line_no', 'item_id', 'item_code', 'item_name', 'uom_code',
        'system_qty', 'physical_qty', 'result', 'checked_by', 'checked_at',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'item_id' => 'integer',
        'system_qty' => 'decimal:4',
        'physical_qty' => 'decimal:4',
        'checked_by' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function cycle(): BelongsTo { return $this->belongsTo(SampleCycle::class, 'sample_cycle_id'); }
    public function checker(): BelongsTo { return $this->belongsTo(User::class, 'checked_by'); }
    public function isChecked(): bool { return $this->checked_at !== null; }
}
