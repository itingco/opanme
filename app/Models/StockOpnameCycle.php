<?php

namespace App\Models;

use Database\Factories\StockOpnameCycleFactory;
use Illuminate\Database\Eloquent\Builder;
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

    protected static function booted(): void
    {
        // CycleController lama tidak menerima Request pada index(). Filter diletakkan
        // sebagai scope khusus route list supaya perubahan tetap kecil dan route lain
        // (show/summary/close/override) tidak terpengaruh.
        static::addGlobalScope('admin_cycle_index_filter', function (Builder $builder): void {
            if (app()->runningInConsole() || ! request()->routeIs('admin.cycles.index')) {
                return;
            }

            $builder->with([
                'warehouses:id,cycle_id,warehouse_code,warehouse_name',
                'assignments.checker:id,name,username',
            ]);

            $operator = $builder->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $q = trim((string) request('q', ''));
            $warehouse = trim((string) request('warehouse', ''));
            $checker = trim((string) request('checker', ''));
            $database = strtoupper(trim((string) request('source_database', '')));
            $status = strtoupper(trim((string) request('status', '')));

            if ($q !== '') {
                $builder->where(function (Builder $query) use ($q, $operator): void {
                    $query->where('cycle_no', $operator, "%{$q}%")
                        ->orWhere('source_database', $operator, "%{$q}%");
                });
            }

            if ($warehouse !== '') {
                $builder->whereHas('warehouses', function (Builder $query) use ($warehouse, $operator): void {
                    $query->where(function (Builder $sub) use ($warehouse, $operator): void {
                        $sub->where('warehouse_code', $operator, "%{$warehouse}%")
                            ->orWhere('warehouse_name', $operator, "%{$warehouse}%");
                    });
                });
            }

            if ($checker !== '') {
                $builder->whereHas('assignments.checker', function (Builder $query) use ($checker, $operator): void {
                    $query->where(function (Builder $sub) use ($checker, $operator): void {
                        $sub->where('name', $operator, "%{$checker}%")
                            ->orWhere('username', $operator, "%{$checker}%");
                    });
                });
            }

            if (in_array($database, [self::DB_INGCO, self::DB_SMI], true)) {
                $builder->where('source_database', $database);
            }

            if (in_array($status, [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_FINALIZED], true)) {
                $builder->where('status', $status);
            }
        });
    }

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
