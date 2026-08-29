<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ScanTransaction extends Model
{
    public $timestamps=false;
    protected $fillable=['cycle_id','scan_session_id','warehouse_id','checker_id','location','alias_code','item_id','item_code','item_name','uom_level','uom_code','ratio_used','physical_qty','scanned_at'];
    protected $casts = ['ratio_used'=>'decimal:4','physical_qty'=>'decimal:4','scanned_at'=>'datetime'];
    public function checker(): BelongsTo { return $this->belongsTo(User::class,'checker_id'); }
}
