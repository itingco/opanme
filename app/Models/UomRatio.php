<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class UomRatio extends Model
{
    use HasFactory;
    protected $fillable = ['source_database','item_id','item_code','item_name','uom_level','uom_code','ratio'];
    protected $casts = ['ratio' => 'decimal:4', 'uom_level' => 'integer', 'item_id' => 'integer'];
}
