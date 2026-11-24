<?php

namespace App\Http\Controllers\Admin;

use App\Models\StockOpnameCycle;
use App\Services\BulkOverrideExcelReader;
use App\Services\CombinedCycleSnapshotService;
use App\Services\ErpConnectionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CombinedBulkCycleOverrideController extends BulkCycleOverrideController
{
    public function store(Request $request, StockOpnameCycle $cycle, BulkOverrideExcelReader $reader, ErpConnectionResolver $resolver): RedirectResponse
    {
        if ($cycle->source_database !== CombinedCycleSnapshotService::MODE) return parent::store($request,$cycle,$reader,$resolver);
        $data = $request->validate([
            'bulk_warehouse_id'=>['required','integer'], 'override_mode'=>['required','in:add,replace'],
            'bulk_data'=>['nullable','string','max:600000','required_without:bulk_file'],
            'bulk_file'=>['nullable','file','mimes:xlsx','max:10240','required_without:bulk_data'],
        ]);
        $warehouseId = (int)$data['bulk_warehouse_id'];
        $cycle->warehouses()->findOrFail($warehouseId);
        $mode = $data['override_mode'];
        try {
            if ($request->hasFile('bulk_file')) {
                $rows = $reader->read($request->file('bulk_file')->getRealPath());
            } else {
                $rows=[];
                foreach (preg_split('/\r\n|\n|\r/',(string)($data['bulk_data']??'')) as $index=>$line) {
                    if (trim($line)==='') continue;
                    $rows[]=['line'=>$index+1,'cols'=>str_contains($line,"\t")?explode("\t",$line):str_getcsv($line,';')];
                    if (count($rows)>2002) throw ValidationException::withMessages(['bulk_data'=>'Maksimal 2.000 item.']);
                }
            }
            $entries=$this->parseCombinedRows($rows);
            DB::transaction(function () use ($cycle,$warehouseId,$request,$entries,$mode) {
                $locked=StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
                if (!in_array($locked->status,[StockOpnameCycle::STATUS_OPEN,StockOpnameCycle::STATUS_CLOSED],true)) {
                    throw ValidationException::withMessages(['bulk_data'=>'Override hanya boleh pada cycle OPEN atau CLOSED.']);
                }
                $locked->warehouses()->findOrFail($warehouseId);
                // Combined IDs are local and intentionally independent of either ERP ItemID.
                $items=app(CombinedCycleSnapshotService::class)->ensureCodes($locked,array_map('strval',array_keys($entries)));
                $now=now(); $batch=(string)Str::uuid(); $overrides=[]; $details=[]; $history=[];
                $previous=DB::table('stock_opname_overrides')->where('cycle_id',$locked->id)->where('warehouse_id',$warehouseId)
                    ->whereIn('item_id',array_map(fn($item)=>$item->id,$items))->lockForUpdate()->pluck('override_qty','item_id');
                foreach ($entries as $code=>$entry) {
                    $item=$items[$code]??null;
                    if (!$item) throw ValidationException::withMessages(['bulk_data'=>"Kode {$code} tidak ditemukan di database sumber."]);
                    $before=$previous->get($item->id);
                    $qty=$entry['qty'];
                    $final=$mode==='add'?number_format(round((float)($before??0)+(float)$qty,4),4,'.',''):$qty;
                    $key=['cycle_id'=>$locked->id,'warehouse_id'=>$warehouseId,'item_id'=>$item->id];
                    $overrides[]=array_merge($key,['override_qty'=>$final,'comment'=>$entry['remark'],'updated_by'=>$request->user()->id,'created_at'=>$now,'updated_at'=>$now]);
                    $details[]=array_merge($key,['item_code'=>$item->item_code,'item_name'=>$item->item_name,'created_at'=>$now,'updated_at'=>$now]);
                    $history[]=array_merge($key,['action'=>$mode,'source'=>'bulk','batch_id'=>$batch,'previous_qty'=>$before,'input_qty'=>$qty,'result_qty'=>$final,'comment'=>$entry['remark'],'updated_by'=>$request->user()->id,'created_at'=>$now]);
                }
                foreach(array_chunk($details,100) as $part) DB::table('stock_opname_override_item_details')->upsert($part,['cycle_id','warehouse_id','item_id'],['item_code','item_name','updated_at']);
                foreach(array_chunk($overrides,100) as $part) DB::table('stock_opname_overrides')->upsert($part,['cycle_id','warehouse_id','item_id'],['override_qty','comment','updated_by','updated_at']);
                foreach(array_chunk($history,100) as $part) DB::table('stock_opname_override_history')->insert($part);
            },3);
            return redirect()->route('admin.cycles.summary',['cycle'=>$cycle->id,'warehouse_id'=>$warehouseId])->with('success',count($entries).' item gabungan berhasil di-override. Proporsi dihitung ulang dari qty final.');
        } catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['bulk_data'=>'Bulk override gabungan gagal; seluruh perubahan dibatalkan. '.$e->getMessage()]);
        }
    }

    private function parseCombinedRows(array $rows): array
    {
        $entries=[]; $errors=[];
        foreach ($rows as $row) {
            $line=(int)$row['line']; $cols=array_map(fn($s)=>trim((string)$s),$row['cols']);
            $header=strtolower(preg_replace('/[^a-z0-9]/i','',$cols[0]??''));
            if ($line<=2 && in_array($header,['itemcode','qrcode','qr','kodebarang','kodeitem'],true)) continue;
            $first=$cols[0]??'';
            if (str_contains($first,'~')) {
                if (substr_count($first,'~')!==1) {$errors[]="Baris {$line}: format QR harus KODE~QTY.";continue;}
                [$code,$qty]=array_map('trim',explode('~',$first,2));
                $remark=($cols[2]??'')!==''?$cols[2]:($cols[1]??'');
                if (($cols[2]??'')!=='' && ($cols[1]??'')!=='' && $cols[1]!==$qty) {$errors[]="Baris {$line}: Qty QR berbeda.";continue;}
            } else {[$code,$qty,$remark]=array_pad($cols,3,'');}
            $code=mb_strtoupper(trim($code));
            if ($code==='' || mb_strlen($code)>100 || !preg_match('/^\d+(?:\.\d{1,4})?$/',$qty) || (float)$qty>999999999999 || trim($remark)==='' || mb_strlen($remark)>2000) {
                $errors[]="Baris {$line}: Item Code, Qty atau Remarks tidak valid."; continue;
            }
            if (isset($entries[$code])) {$errors[]="Baris {$line}: kode {$code} duplikat.";continue;}
            $entries[$code]=['qty'=>$qty,'remark'=>trim($remark)];
            if(count($entries)>2000){$errors[]='Maksimal 2.000 item.';break;}
        }
        if (!$entries) $errors[]='Tidak ada item valid.';
        if ($errors) throw ValidationException::withMessages(['bulk_data'=>implode(' ',array_slice($errors,0,12))]);
        return $entries;
    }
}
