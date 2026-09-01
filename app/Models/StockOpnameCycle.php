<?php

namespace App\Models;

use Database\Factories\StockOpnameCycleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpnameCycle extends Model
{
    /** @use HasFactory<StockOpnameCycleFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_FINALIZED='FINALIZED';

    public const DB_INGCO = 'AS_INGCO';
    public const DB_SMI = 'AS_SMI';

    protected $fillable = [
        'cycle_no',
        'source_database',
        'cutoff_date',
        'status',
        'started_at',
        'completed_at',
        'closing_snapshot_at',
        'closing_snapshot_error',
        'finalized_at',
        'finalized_by',
        'created_by',
    ];

    protected $casts = [
        'cutoff_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'closing_snapshot_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function warehouses(): HasMany { return $this->hasMany(CycleWarehouse::class, 'cycle_id'); }
    public function assignments(): HasMany { return $this->hasMany(CheckerAssignment::class, 'cycle_id'); }
    public function snapshots(): HasMany { return $this->hasMany(StockSnapshot::class, 'cycle_id'); }
    public function overrides(): HasMany { return $this->hasMany(StockOpnameOverride::class, 'cycle_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function finalizer(): BelongsTo { return $this->belongsTo(User::class, 'finalized_by'); }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}
