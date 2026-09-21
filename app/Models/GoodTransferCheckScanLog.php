<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodTransferCheckScanLog extends Model
{
    protected $connection = 'apphub';
    protected $table = 'good_transfer_check_scan_logs';

    protected $fillable = [
        'check_header_id',
        'check_detail_id',
        'barcode',
        'item_code',
        'uom_code',
        'ratio_qty',
        'checker_user_id',
        'scanned_at',
    ];

    protected $casts = [
        'ratio_qty' => 'integer',
        'scanned_at' => 'datetime',
    ];
}
