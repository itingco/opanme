<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_CHECKER = 'CHECKER';
    public const ROLE_GERAI = 'GERAI';

    protected $fillable = [
        'name', 'username', 'password', 'role', 'is_active',
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
    public function isGerai(): bool { return $this->role === self::ROLE_GERAI; }
    public function hasGeraiWarehouseBinding(): bool { return $this->isGerai() && $this->erp_warehouse_id !== null; }
    public function sampleCycles(): HasMany { return $this->hasMany(SampleCycle::class, 'created_by'); }
}
