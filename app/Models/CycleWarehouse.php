<?php
namespace App\Models;
use Database\Factories\CycleWarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CycleWarehouse extends Model
{
    /** @use HasFactory<CycleWarehouseFactory> */
    use HasFactory;
    public $timestamps=false;
    protected $fillable=['cycle_id','erp_warehouse_id','warehouse_code','warehouse_name','created_at'];
    protected $casts = ['created_at'=>'datetime'];
    public function cycle(): BelongsTo { return $this->belongsTo(StockOpnameCycle::class,'cycle_id'); }
}
