<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CheckerAssignment extends Model
{
    public $timestamps=false;
    protected $fillable=['cycle_id','warehouse_id','checker_id','assigned_by','created_at'];
    protected $casts = ['created_at'=>'datetime'];
    public function cycle(): BelongsTo { return $this->belongsTo(StockOpnameCycle::class,'cycle_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(CycleWarehouse::class,'warehouse_id'); }
    public function checker(): BelongsTo { return $this->belongsTo(User::class,'checker_id'); }
}
