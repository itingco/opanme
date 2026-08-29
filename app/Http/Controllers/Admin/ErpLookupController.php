<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\StockOpnameCycle; use App\Services\ErpCatalogService; use Illuminate\Http\JsonResponse; use Illuminate\Http\Request; use Illuminate\Validation\Rule; use UnexpectedValueException;
class ErpLookupController extends Controller {
    public function warehouses(Request $request, ErpCatalogService $erp): JsonResponse { $data=$request->validate(['source_database'=>['required',Rule::in([StockOpnameCycle::DB_INGCO,StockOpnameCycle::DB_SMI])]]); return response()->json(['data'=>$erp->warehouses($data['source_database'])]); }
    public function barcode(Request $request, ErpCatalogService $erp): JsonResponse { $data=$request->validate(['source_database'=>['required',Rule::in([StockOpnameCycle::DB_INGCO,StockOpnameCycle::DB_SMI])],'barcode'=>['required','string','max:150']]); try{$item=$erp->findBarcode($data['source_database'],$data['barcode']);}catch(UnexpectedValueException $e){return response()->json(['message'=>$e->getMessage()],422);} if(!$item) return response()->json(['message'=>'Barcode tidak ditemukan.'],404); return response()->json(['data'=>$item]); }
}
