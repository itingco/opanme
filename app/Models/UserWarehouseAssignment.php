<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWarehouseAssignment extends Model
{
    protected $fillable = [
        'user_id', 'source_database', 'erp_warehouse_id', 'warehouse_code', 'warehouse_name',
    ];

    protected $casts = [
        'erp_warehouse_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keyValue(): string
    {
        return $this->source_database.'|'.$this->erp_warehouse_id;
    }
}
