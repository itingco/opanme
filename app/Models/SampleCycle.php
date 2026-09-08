<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SampleCycle extends Model
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'cycle_no', 'created_by', 'source_database', 'erp_warehouse_id',
        'warehouse_code', 'warehouse_name', 'location', 'status', 'started_at', 'closed_at',
    ];

    protected $casts = [
        'erp_warehouse_id' => 'integer',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function checks(): HasMany { return $this->hasMany(SampleCheck::class, 'sample_cycle_id'); }
    public function isOpen(): bool { return $this->status === self::STATUS_OPEN; }
}
