<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use App\Services\CombinedCycleAllocationService;
use App\Services\CombinedCycleSnapshotService;
use App\Services\CycleSummaryService;
use App\Services\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\CombinedManualAllocationService;

class CombinedCycleAllocationController extends Controller
{
    private function query(Request $request, StockOpnameCycle $cycle, CycleSummaryService $summary)
    {
        abort_unless($cycle->source_database === CombinedCycleSnapshotService::MODE,404);
        abort_if($cycle->status === StockOpnameCycle::STATUS_DRAFT,422,'Cycle belum dimulai.');
        $search = trim((string)$request->query('search',''));
        $query = $summary->query($cycle);
        if ($search !== '') $query->where(function ($q) use ($search) {
            $q->where('summary_rows.item_code','like','%'.$search.'%')->orWhere('summary_rows.item_name','like','%'.$search.'%');
        });
        // Filter before pagination; retain manual records (even stale zero totals) for reset/review.
        $query->where(function ($q) use ($cycle) {
            $q->where('summary_rows.row_type','NON_SYSTEM')
                ->orWhere('summary_rows.final_physical_qty','<>',0)
                ->orWhereExists(function ($sub) use ($cycle) {
                    $sub->selectRaw('1')->from('cycle_combined_stocks as s')
                        ->join('cycle_combined_items as i','i.id','=','s.combined_item_id')
                        ->where('i.cycle_id',$cycle->id)->whereColumn('i.id','summary_rows.item_id')
                        ->where(fn($b)=>$b->where('s.opening_qty','<>',0)->orWhere('s.closing_qty','<>',0));
                })->orWhereExists(function ($sub) use ($cycle) {
                    $sub->selectRaw('1')->from('cycle_combined_manual_allocations as m')
                        ->join('cycle_combined_items as i','i.id','=','m.combined_item_id')
                        ->where('i.cycle_id',$cycle->id)->whereColumn('i.id','summary_rows.item_id');
                });
        });
        return $query->orderBy('summary_rows.warehouse_id')->orderBy('summary_rows.row_key');
    }

    public function show(Request $request, StockOpnameCycle $cycle, CycleSummaryService $summary, CombinedCycleAllocationService $allocation)
    {
        $rows = $this->query($request,$cycle,$summary)->paginate(60)->withQueryString();
        $groups = $allocation->details($cycle,$rows->items());
        return view('admin.cycles.combined-allocation',compact('cycle','rows','groups'));
    }

    private function assertEditable(Request $request, StockOpnameCycle $cycle): void
    {
        abort_unless(in_array($request->user()->role,['ADMIN','ADMIN_GUDANG'],true),403);
        abort_unless($cycle->source_database === CombinedCycleSnapshotService::MODE,404);
        if ($cycle->status !== StockOpnameCycle::STATUS_CLOSED) {
            throw ValidationException::withMessages(['allocation_qty'=>'Edit alokasi hanya diizinkan ketika cycle CLOSED dan belum FINALIZED.']);
        }
    }

    public function save(Request $request, StockOpnameCycle $cycle, int $itemId, CycleSummaryService $summary)
    {
        $this->assertEditable($request,$cycle);
        $data = $request->validate([
            'allocation_qty'=>['required','array','max:100'],
            'allocation_qty.*'=>['required','regex:/^\d{1,12}(?:\.\d{1,4})?$/D'],
            'expected_qty'=>['required','regex:/^\d{1,12}(?:\.\d{1,4})?$/D'],
            'note'=>['required','string','max:1000'],
        ]);
        DB::transaction(function () use ($request,$cycle,$itemId,$summary,$data) {
            $locked = StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $this->assertEditable($request,$locked);
            abort_unless(DB::table('cycle_combined_items')->where('cycle_id',$locked->id)->where('id',$itemId)->exists(),404);
            $row = $summary->query($locked)->reorder()->where('summary_rows.row_type','ERP')->where('summary_rows.item_id',$itemId)->first();
            abort_unless($row,404);
            if (CombinedManualAllocationService::units($row->final_physical_qty) !== CombinedManualAllocationService::units($data['expected_qty'])) {
                throw ValidationException::withMessages(['allocation_qty'=>'Final Fisik telah berubah. Muat ulang dan periksa kembali alokasi.']);
            }
            $ids = DB::table('cycle_combined_stocks')->where('combined_item_id',$itemId)->orderBy('combined_source_id')->pluck('combined_source_id')->all();
            $allocations = CombinedManualAllocationService::validateQuantities($data['allocation_qty'],$ids,$row->final_physical_qty);
            DB::table('cycle_combined_manual_allocations')->updateOrInsert(['combined_item_id'=>$itemId],[
                'physical_qty'=>CombinedManualAllocationService::decimal(CombinedManualAllocationService::units($row->final_physical_qty)),
                'allocations'=>json_encode($allocations,JSON_THROW_ON_ERROR),'note'=>trim($data['note']),
                'updated_by'=>$request->user()->id,'updated_at'=>now(),
            ]);
        });
        return back()->with('success','Alokasi manual disimpan. Total Final Fisik tidak berubah.');
    }

    public function reset(Request $request, StockOpnameCycle $cycle, int $itemId)
    {
        $this->assertEditable($request,$cycle);
        DB::transaction(function () use ($request,$cycle,$itemId) {
            $locked = StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $this->assertEditable($request,$locked);
            abort_unless(DB::table('cycle_combined_items')->where('cycle_id',$locked->id)->where('id',$itemId)->exists(),404);
            DB::table('cycle_combined_manual_allocations')->where('combined_item_id',$itemId)->delete();
        });
        return back()->with('success','Alokasi dikembalikan ke proporsi otomatis.');
    }

    public function export(Request $request, StockOpnameCycle $cycle, CycleSummaryService $summary, CombinedCycleAllocationService $allocation, SimpleXlsxWriter $xlsx)
    {
        $query = $this->query($request,$cycle,$summary);
        $rows = (function () use ($query,$cycle,$allocation) {
            // Each batch contains the entire allocation of every included item.
            foreach ($query->lazy(200)->chunk(200) as $batch) {
                foreach ($allocation->details($cycle,$batch) as $group) {
                    $r = $group['row'];
                    foreach ($group['details'] as $d) {
                        yield [$cycle->cycle_no,$cycle->status,(string)$r->item_code,$r->item_name,$group['uom'],
                            $d['database'],$d['warehouse_code'],$d['warehouse_name'],$d['opening'],$d['closing'],
                            $d['weight']===null?null:round(100*$d['weight'],4),$d['allocated'],$d['variance'],$d['note']];
                    }
                }
            }
        })();
        $name = $cycle->cycle_no.'-alokasi-'.now()->format('Ymd-His').'.xlsx';
        $path = storage_path('app/exports/'.\Illuminate\Support\Str::uuid().'.xlsx');
        $xlsx->export($path,'Alokasi Gudang',['Cycle','Status','Item Code','Item Name','UOM','Database','Warehouse Code','Warehouse Name','Opening ERP','Closing ERP','Proporsi (%)','Alokasi Fisik Final','Selisih vs Opening','Catatan'],$rows);
        return response()->download($path,$name,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
