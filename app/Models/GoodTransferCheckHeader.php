<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodTransferCheckHeader extends Model
{
    protected $connection = 'apphub';
    protected $table = 'good_transfer_check_headers';

    protected $fillable = [
        'company',
        'source_database',
        'mutation_id',
        'mutation_number',
        'mutation_date',
        'source_warehouse_name',
        'destination_warehouse_name',
        'picker_user_id',
        'checker_user_id',
        'status',
        'started_at',
        'completed_at',
        'source_updated_at',
    ];

    protected $casts = [
        'mutation_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'source_updated_at' => 'datetime',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(GoodTransferCheckDetail::class, 'check_header_id')
            ->orderBy('source_detail_id');
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picker_user_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_user_id');
    }

    public function scanErrors(): HasMany
    {
        return $this->hasMany(GoodTransferCheckScanError::class, 'check_header_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(GoodTransferCheckOverride::class, 'check_header_id');
    }
}
