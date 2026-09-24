<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SampleCycle extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_CLOSED = 'CLOSED';

    public const TYPE_GERAI = 'GERAI';
    public const TYPE_WAREHOUSE = 'WAREHOUSE';

    protected $fillable = [
        'cycle_no', 'created_by', 'source_database', 'erp_warehouse_id',
        'warehouse_code', 'warehouse_name', 'location', 'status', 'started_at', 'closed_at',
        'cycle_type', 'target_percentage', 'assigned_checker_id', 'notes',
    ];

    protected $casts = [
        'erp_warehouse_id' => 'integer',
        'target_percentage' => 'decimal:2',
        'assigned_checker_id' => 'integer',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function checker(): BelongsTo { return $this->belongsTo(User::class, 'assigned_checker_id'); }
    public function checks(): HasMany { return $this->hasMany(SampleCheck::class, 'sample_cycle_id'); }
    public function items(): HasMany { return $this->hasMany(SampleCycleItem::class, 'sample_cycle_id'); }
    public function warehouses(): HasMany { return $this->hasMany(SampleCycleWarehouse::class, 'sample_cycle_id'); }

    public function isOpen(): bool { return $this->status === self::STATUS_OPEN; }
    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isClosed(): bool { return $this->status === self::STATUS_CLOSED; }
    public function isWarehouseSampling(): bool { return $this->cycle_type === self::TYPE_WAREHOUSE; }
}
