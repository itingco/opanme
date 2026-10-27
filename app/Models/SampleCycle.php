<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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

    protected static function booted(): void
    {
        static::saved(function (SampleCycle $cycle): void {
            if (! $cycle->isWarehouseSampling()) {
                return;
            }

            if (! Schema::hasTable('sample_cycle_checkers')) {
                return;
            }

            // Existing controller still posts assigned_checker_id for backward
            // compatibility. The updated create form additionally posts
            // assigned_checker_ids[]; sync it here so older controller flows and
            // existing integrations keep working without route changes.
            $submitted = request()->input('assigned_checker_ids');

            if (! is_array($submitted)) {
                if ($cycle->assigned_checker_id) {
                    $cycle->checkers()->syncWithoutDetaching([(int) $cycle->assigned_checker_id]);
                }
                return;
            }

            $ids = collect($submitted)
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            if ($cycle->assigned_checker_id && ! $ids->contains((int) $cycle->assigned_checker_id)) {
                $ids->prepend((int) $cycle->assigned_checker_id);
            }

            if ($ids->isEmpty()) {
                throw ValidationException::withMessages([
                    'assigned_checker_ids' => 'Pilih minimal satu Checker Gudang.',
                ]);
            }

            $validIds = User::query()
                ->whereIn('id', $ids->all())
                ->where('role', User::ROLE_CHECKER_GUDANG)
                ->where('is_active', true)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id);

            if ($validIds->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'assigned_checker_ids' => 'Salah satu Checker Gudang tidak valid atau sudah nonaktif.',
                ]);
            }

            $cycle->checkers()->sync($validIds->all());

            if (! $validIds->contains((int) $cycle->assigned_checker_id)) {
                static::withoutEvents(function () use ($cycle, $validIds): void {
                    $cycle->forceFill(['assigned_checker_id' => $validIds->first()])->save();
                });
            }
        });
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function checker(): BelongsTo { return $this->belongsTo(User::class, 'assigned_checker_id'); }
    public function checks(): HasMany { return $this->hasMany(SampleCheck::class, 'sample_cycle_id'); }
    public function items(): HasMany { return $this->hasMany(SampleCycleItem::class, 'sample_cycle_id'); }
    public function warehouses(): HasMany { return $this->hasMany(SampleCycleWarehouse::class, 'sample_cycle_id'); }

    public function checkers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'sample_cycle_checkers',
            'sample_cycle_id',
            'user_id'
        )->withTimestamps();
    }

    public function isAssignedChecker(int $userId): bool
    {
        if ((int) $this->assigned_checker_id === $userId) {
            return true;
        }

        if (! Schema::hasTable('sample_cycle_checkers')) {
            return false;
        }

        if ($this->relationLoaded('checkers')) {
            return $this->checkers->contains(fn (User $checker): bool => (int) $checker->id === $userId);
        }

        return $this->checkers()->whereKey($userId)->exists();
    }

    public function isOpen(): bool { return $this->status === self::STATUS_OPEN; }
    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isClosed(): bool { return $this->status === self::STATUS_CLOSED; }
    public function isWarehouseSampling(): bool { return $this->cycle_type === self::TYPE_WAREHOUSE; }
}
