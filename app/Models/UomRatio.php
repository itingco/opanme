<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UomRatio extends Model
{
    use HasFactory;

    protected $fillable = ['item_code','uom_code','ratio'];

    protected $casts = [
        'ratio' => 'decimal:4',
    ];
}
