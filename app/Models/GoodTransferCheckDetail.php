<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodTransferCheckDetail extends Model
{
    protected $connection = 'apphub';
    protected $table = 'good_transfer_check_details';

    protected $fillable = [
        'check_header_id',
        'source_detail_id',
        'item_code',
        'item_name',
        'uom_code',
        'expected_qty',
        'transfer_uom_ratio',
        'expected_base_qty',
        'scanned_base_qty',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'expected_qty' => 'decimal:4',
        'transfer_uom_ratio' => 'integer',
        'expected_base_qty' => 'decimal:4',
        'scanned_base_qty' => 'decimal:4',
        'completed_at' => 'datetime',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(GoodTransferCheckHeader::class, 'check_header_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(GoodTransferCheckScanLog::class, 'check_detail_id');
    }

    public function overrideRecord()
    {
        return $this->hasOne(GoodTransferCheckOverride::class, 'check_detail_id');
    }
}
