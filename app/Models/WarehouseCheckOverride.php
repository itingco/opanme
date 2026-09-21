<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseCheckOverride extends Model
{
    protected $connection = 'apphub';
    protected $table = 'warehouse_check_overrides';

    protected $fillable = [
        'check_header_id',
        'check_detail_id',
        'supervisor_user_id',
        'checker_user_id',
        'admin_user_id',
        'otp_id',
        'reason_code',
        'reason_note',
        'override_qty',
        'override_base_qty',
        'overridden_at',
    ];

    protected $casts = [
        'override_qty' => 'decimal:4',
        'override_base_qty' => 'decimal:4',
        'overridden_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
