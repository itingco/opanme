<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockSnapshot extends Model
{
    public $timestamps=false;
    protected $fillable=['cycle_id','warehouse_id','erp_warehouse_id','warehouse_code','item_id','item_code','item_name','opening_system_qty','closing_system_qty','opening_snapshot_at','closing_snapshot_at'];
    protected $casts = ['opening_system_qty'=>'decimal:4','closing_system_qty'=>'decimal:4','opening_snapshot_at'=>'datetime','closing_snapshot_at'=>'datetime'];
}
