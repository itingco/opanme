<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodTransferCheckScanError extends Model
{
    protected $connection = 'apphub';
    protected $table = 'good_transfer_check_scan_errors';

    protected $fillable = [
        'check_header_id',
        'barcode',
        'resolved_item_code',
        'resolved_uom_code',
        'error_type',
        'error_message',
        'picker_user_id',
        'checker_user_id',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];
}
