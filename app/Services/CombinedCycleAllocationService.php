<?php

namespace App\Services;

use App\Models\StockOpnameCycle;
use Illuminate\Support\Facades\DB;

class CombinedCycleAllocationService
{
    public function __construct(private readonly CombinedQtyAllocator $allocator, private readonly CombinedCycleSnapshotService $snapshots) {}

    /** Same final qty as Summary; manual allocation only redistributes that quantity. */
    public function details(StockOpnameCycle $cycle, iterable $rows): array
    {
        $rows = collect($rows);
        $sources = $this->snapshots->sources($cycle);
        $ids = $rows->where('row_type','ERP')->pluck('item_id')->filter()->unique()->all();
        $stocks = DB::table('cycle_combined_stocks as s')
            ->join('cycle_combined_items as i','i.id','=','s.combined_item_id')
            ->where('i.cycle_id',$cycle->id)->whereIn('s.combined_item_id',$ids)
            ->get(['s.*','i.uom_code'])->groupBy('combined_item_id');
        $manuals = DB::table('cycle_combined_manual_allocations as m')
            ->join('cycle_combined_items as i','i.id','=','m.combined_item_id')
            ->leftJoin('users as u','u.id','=','m.updated_by')
            ->where('i.cycle_id',$cycle->id)->whereIn('m.combined_item_id',$ids)
            ->get(['m.*','u.name as updated_by_name'])->keyBy('combined_item_id');
        $result = [];
        foreach ($rows as $row) {
            $physical = (float)$row->final_physical_qty;
            $itemStocks = $row->row_type === 'ERP' ? $stocks->get($row->item_id,collect())->keyBy('combined_source_id') : collect();
            $weights = $itemStocks->mapWithKeys(fn($stock)=>[$stock->combined_source_id=>(float)$stock->opening_qty])->all();
            $positive = array_sum(array_map(fn($qty)=>max(0,$qty),$weights));
            $allocations = $this->allocator->allocate($physical,$weights);
            $manual = $row->row_type === 'ERP' ? $manuals->get($row->item_id) : null;
            $stale = $manual && abs((float)$manual->physical_qty - $physical) > 0.00005;
            $active = $manual && !$stale;
            if ($active) {
                $saved = json_decode($manual->allocations,true,512,JSON_THROW_ON_ERROR);
                // A closing retry may add zero-stock sources. Preserve saved values, default new sources to zero.
                foreach ($allocations as $id => $ignored) $allocations[$id] = (float)($saved[$id] ?? 0);
            }
            $details = []; $editable = [];
            foreach ($sources as $source) {
                $stock = $itemStocks->get($source->id);
                if (!$stock) continue; // No ERP master, never eligible.
                $allocated = $allocations[$source->id] ?? 0;
                $d = [
                    'source_id'=>(int)$source->id,
                    'database'=>$source->source_database,'warehouse_code'=>$source->warehouse_code,'warehouse_name'=>$source->warehouse_name,
                    'opening'=>(float)$stock->opening_qty,
                    'closing'=>$stock->closing_qty===null?null:(float)$stock->closing_qty,
                    'weight'=>$active ? ($physical>0?$allocated/$physical:0) : ($positive>0 ? max(0,(float)$stock->opening_qty)/$positive : ($source->id===array_key_first($allocations)?1:0)),
                    'allocated'=>$allocated,'variance'=>round($allocated-(float)$stock->opening_qty,4),
                    'note'=>$active?'Manual: '.$manual->note:($stale?'Otomatis sementara — alokasi manual perlu diperbarui':($positive>0?'Proporsi opening':'Opening positif nol; alokasi ke gudang pertama yang memiliki master')),
                ];
                $editable[] = $d;
                // Hide zero-balance rows only when they contain no closing or allocated quantity.
                if ($d['opening'] != 0 || ($d['closing'] !== null && $d['closing'] != 0) || $allocated != 0) $details[] = $d;
            }
            if (!$editable && ($row->row_type === 'NON_SYSTEM' || $physical != 0)) {
                $details[] = ['source_id'=>null,'database'=>'UNALLOCATED','warehouse_code'=>'-','warehouse_name'=>'Belum ada gudang ERP yang valid',
                    'opening'=>0,'closing'=>null,'weight'=>null,'allocated'=>$physical,'variance'=>$physical,
                    'note'=>'Non-System / belum memiliki master sumber. Tidak dialokasikan ke ERP.'];
            }
            $result[] = ['row'=>$row,'uom'=>$itemStocks->first()?->uom_code ?? $row->smallest_uom_code,'details'=>$details,
                'editable'=>$editable,'manual'=>$manual,'stale'=>$stale,'active'=>$active];
        }
        return $result;
    }
}
