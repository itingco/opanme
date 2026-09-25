<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'ADMIN';

    // Legacy stock-opname roles are intentionally retained for backward compatibility.
    public const ROLE_CHECKER = 'CHECKER';
    public const ROLE_GERAI = 'GERAI';

    // Explicit access roles for the combined Gerai + Gudang application.
    public const ROLE_ADMIN_GERAI = 'ADMIN_GERAI';
    public const ROLE_CHECKER_GERAI = 'CHECKER_GERAI';
    public const ROLE_ADMIN_GUDANG = 'ADMIN_GUDANG';
    public const ROLE_CHECKER_GUDANG = 'CHECKER_GUDANG';

    protected $fillable = [
        'name', 'username', 'password', 'role', 'is_active',
        // Kept for compatibility with old code. The canonical assignment is now
        // user_warehouse_assignments and may contain many warehouses/databases.
        'source_database', 'erp_warehouse_id', 'warehouse_code', 'warehouse_name',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'erp_warehouse_id' => 'integer',
    ];

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isChecker(): bool { return $this->role === self::ROLE_CHECKER; }
    public function isAdminGerai(): bool { return $this->role === self::ROLE_ADMIN_GERAI; }
    public function isCheckerGerai(): bool { return $this->role === self::ROLE_CHECKER_GERAI; }
    public function isAdminGudang(): bool { return $this->role === self::ROLE_ADMIN_GUDANG; }
    public function isCheckerGudang(): bool { return $this->role === self::ROLE_CHECKER_GUDANG; }

    /** Legacy GERAI accounts are treated as Checker Gerai until they are reclassified. */
    public function isLegacyGerai(): bool { return $this->role === self::ROLE_GERAI; }
    public function isGerai(): bool { return $this->isAdminGerai() || $this->isCheckerGerai() || $this->isLegacyGerai(); }
    public function isGeraiAdminRole(): bool { return $this->isAdmin() || $this->isAdminGerai(); }
    public function isGeraiCheckerRole(): bool { return $this->isCheckerGerai() || $this->isLegacyGerai(); }

    public function sampleCycles(): HasMany
    {
        return $this->hasMany(SampleCycle::class, 'created_by');
    }

    public function assignedWarehouseSampleCycles(): HasMany
    {
        return $this->hasMany(SampleCycle::class, 'assigned_checker_id');
    }

    public function warehouseAssignments(): HasMany
    {
        return $this->hasMany(UserWarehouseAssignment::class);
    }

    public function hasWarehouseAssignment(string $sourceDatabase, int $warehouseId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->relationLoaded('warehouseAssignments')) {
            $exists = $this->warehouseAssignments->contains(
                fn (UserWarehouseAssignment $assignment): bool =>
                    (string) $assignment->source_database === (string) $sourceDatabase
                    && (int) $assignment->erp_warehouse_id === $warehouseId
            );
            if ($exists) {
                return true;
            }
        } elseif ($this->warehouseAssignments()
            ->where('source_database', $sourceDatabase)
            ->where('erp_warehouse_id', $warehouseId)
            ->exists()) {
            return true;
        }

        // Fallback for installations that have not yet migrated the old single binding.
        return (string) $this->source_database === (string) $sourceDatabase
            && (int) $this->erp_warehouse_id === $warehouseId;
    }

    public function hasGeraiWarehouseBinding(): bool
    {
        return $this->warehouseAssignments()->exists() || $this->erp_warehouse_id !== null;
    }
}
