<?php
namespace App\Http\Controllers\Gerai;

use App\Http\Controllers\Controller;
use App\Models\SampleCycle;
use App\Models\StockOpnameCycle;
use App\Services\CycleSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CycleAuditController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $gerai = SampleCycle::query()->where('cycle_type', SampleCycle::TYPE_GERAI)
            ->where('status', SampleCycle::STATUS_CLOSED);
        $final = StockOpnameCycle::query()->where('status', StockOpnameCycle::STATUS_FINALIZED);
        if ($user->isAdminGerai()) {
            $pairs = $user->warehouseAssignments()->get(['source_database','erp_warehouse_id']);
            $gerai->where(function ($q) use ($pairs) {
                if ($pairs->isEmpty()) $q->whereRaw('1=0');
                foreach ($pairs as $p) $q->orWhere(fn($one) => $one
                    ->where('source_database',$p->source_database)->where('erp_warehouse_id',$p->erp_warehouse_id));
            });
            $final->where(function ($q) use ($pairs) {
                if ($pairs->isEmpty()) $q->whereRaw('1=0');
                foreach ($pairs as $p) $q->orWhere(fn($one) => $one
                    ->where('source_database',$p->source_database)
                    ->whereHas('warehouses', fn($w) => $w->where('erp_warehouse_id',$p->erp_warehouse_id)));
            });
        }
        $a = $gerai->withCount(['checks as selisih_count'=>fn($q)=>$q->where('result','MISMATCH')])
            ->orderByDesc('closed_at')->limit(150)->get()->map(fn($c)=>[
            'type'=>'gerai','id'=>$c->id,'date'=>$c->closed_at,'number'=>$c->cycle_no,
            'database'=>$c->source_database,'warehouse'=>$c->warehouse_code,'variance'=>$c->selisih_count]);
        $b = $final->orderByDesc('finalized_at')->limit(150)->get()->map(fn($c)=>[
            'type'=>'final','id'=>$c->id,'date'=>$c->finalized_at,'number'=>$c->cycle_no,
            'database'=>$c->source_database,'warehouse'=>'Multi Gudang','variance'=>null]);
        $history = $a->concat($b)->sortByDesc('date')->take(150)->values();
        return view('gerai.cycle-audit',compact('history'));
    }
    public function gerai(Request $request, SampleCycle $sampleCycle): View
    {
        abort_unless($sampleCycle->cycle_type === SampleCycle::TYPE_GERAI && $sampleCycle->status === SampleCycle::STATUS_CLOSED,404);
        if ($request->user()->isAdminGerai()) abort_unless($request->user()->hasWarehouseAssignment($sampleCycle->source_database,(int)$sampleCycle->erp_warehouse_id),403);
        $rows = $sampleCycle->checks()->where('result','MISMATCH')->orderBy('scanned_at')->paginate(60);
        return view('gerai.cycle-audit-detail',['kind'=>'gerai','number'=>$sampleCycle->cycle_no,'rows'=>$rows]);
    }
    public function final(Request $request, StockOpnameCycle $cycle, CycleSummaryService $summary)
    {
        abort_unless($cycle->status === StockOpnameCycle::STATUS_FINALIZED,404);
        $warehouseId = $request->integer('warehouse_id') ?: null;
        if ($warehouseId) abort_unless($cycle->warehouses()->whereKey($warehouseId)->exists(),404);
        if ($request->user()->isAdminGerai()) {
            $ids = $request->user()->warehouseAssignments()->where('source_database',$cycle->source_database)->pluck('erp_warehouse_id')->all();
            $allowed = $cycle->warehouses()->whereIn('erp_warehouse_id',$ids)->pluck('id')->all();
            abort_if(!$allowed,403);
            if (!$warehouseId) return redirect()->route('cycle-audit.final',['cycle'=>$cycle->id,'warehouse_id'=>$allowed[0]]);
            abort_unless(in_array($warehouseId,$allowed),403);
        }
        $rows = $summary->query($cycle,$warehouseId,true)->paginate(60)->withQueryString();
        return view('gerai.cycle-audit-detail',['kind'=>'final','number'=>$cycle->cycle_no,'rows'=>$rows]);
    }
}
