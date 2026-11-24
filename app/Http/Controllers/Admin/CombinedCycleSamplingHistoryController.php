<?php

namespace App\Http\Controllers\Admin;

use App\Models\StockOpnameCycle;
use App\Services\CombinedCycleSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CombinedCycleSamplingHistoryController extends CycleSamplingItemHistoryController
{
    public function show(Request $request, StockOpnameCycle $cycle, int $warehouseId, int $itemId): View
    {
        if ($cycle->source_database !== CombinedCycleSnapshotService::MODE) return parent::show($request,$cycle,$warehouseId,$itemId);
        $warehouse=$cycle->warehouses()->findOrFail($warehouseId);
        $item=DB::table('cycle_combined_items')->where('cycle_id',$cycle->id)->where('id',$itemId)->first(['item_code','item_name']);
        abort_unless($item,404);
        $sources=app(CombinedCycleSnapshotService::class)->sources($cycle);
        $dates=$request->validate(['from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d']]);
        $today=CarbonImmutable::now(config('app.timezone','Asia/Jakarta'));
        $custom=$request->exists('from')||$request->exists('to');
        $dateFrom=$custom?['from'=>$dates['from']??'','to'=>$dates['to']??'']:['from'=>$today->subMonthsNoOverflow(3)->toDateString(),'to'=>$today->toDateString()];
        if ($dateFrom['from']!==''&&$dateFrom['to']!==''&&$dateFrom['from']>$dateFrom['to']) throw ValidationException::withMessages(['from'=>'Tanggal mulai tidak boleh melebihi tanggal akhir.']);
        $filter=function($q,string $alias) use ($sources) {
            $q->where(function($pairs) use ($sources,$alias) {
                $pairs->whereRaw('1=0');
                foreach ($sources as $source) $pairs->orWhere(fn($one)=>$one->where($alias.'.source_database',$source->source_database)->where($alias.'.erp_warehouse_id',$source->erp_warehouse_id));
            });
        };
        $gudang=DB::table('sample_cycle_item_stocks as stock')
            ->join('sample_cycle_items as sci','sci.id','=','stock.sample_cycle_item_id')
            ->join('sample_cycles as sc','sc.id','=','sci.sample_cycle_id')
            ->join('sample_cycle_warehouses as sw','sw.id','=','stock.sample_cycle_warehouse_id')
            ->leftJoin('users as checked_user','checked_user.id','=','sci.checked_by')
            ->leftJoin('users as validated_user','validated_user.id','=','sci.validated_by')
            ->where('sc.cycle_type','WAREHOUSE')->whereRaw('UPPER(TRIM(sci.item_code)) = ?',[$item->item_code])
            ->selectRaw('COALESCE(sci.checked_at,sci.validated_at,sc.closed_at,sc.started_at,sc.created_at) as event_at')
            ->selectRaw("'SAMPLING GUDANG - ' || sw.source_database || ' / ' || sw.warehouse_code as sampling_type")
            ->addSelect('sc.cycle_no','sc.status as cycle_status','sc.location','sci.checked_at','sci.validated_at','sci.checker_comment','sci.validation_note','checked_user.name as checker_name','validated_user.name as validator_name','stock.system_qty','stock.validation_system_qty','stock.allocated_physical_qty as physical_qty','stock.result');
        $filter($gudang,'sw');
        $gerai=DB::table('sample_checks as chk')->leftJoin('sample_cycles as sc','sc.id','=','chk.sample_cycle_id')
            ->leftJoin('users as checker','checker.id','=','chk.user_id')->whereRaw('UPPER(TRIM(chk.item_code)) = ?',[$item->item_code])
            ->selectRaw('chk.scanned_at as event_at')->selectRaw("'SAMPLING GERAI - ' || chk.source_database || ' / ' || CAST(chk.erp_warehouse_id AS TEXT) as sampling_type")
            ->addSelect('sc.cycle_no','sc.status as cycle_status','chk.location')->selectRaw('chk.scanned_at as checked_at')
            ->selectRaw('NULL as validated_at, NULL as checker_comment, NULL as validation_note')->addSelect('checker.name as checker_name')
            ->selectRaw('NULL as validator_name')->addSelect('chk.system_qty')->selectRaw('chk.system_qty as validation_system_qty')->addSelect('chk.physical_qty','chk.result');
        $filter($gerai,'chk');
        $history=DB::query()->fromSub($gudang->unionAll($gerai),'h')
            ->when($dateFrom['from']!=='',fn($q)=>$q->where('event_at','>=',$dateFrom['from'].' 00:00:00'))
            ->when($dateFrom['to']!=='',fn($q)=>$q->where('event_at','<',CarbonImmutable::parse($dateFrom['to'])->addDay()->toDateString().' 00:00:00'))
            ->orderByDesc('event_at')->paginate(40)->withQueryString();
        return view('admin.cycles.sampling-item-history',compact('cycle','warehouse','item','history','dateFrom'));
    }
}
