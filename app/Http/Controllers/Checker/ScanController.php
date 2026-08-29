<?php
namespace App\Http\Controllers\Checker;
use App\Http\Controllers\Controller; use App\Models\ScanSession; use App\Services\ScanService; use Illuminate\Http\JsonResponse; use Illuminate\Http\Request;
class ScanController extends Controller { public function store(Request $request, ScanSession $session, ScanService $service): JsonResponse { $data=$request->validate(['barcode'=>['required','string','max:150']]); return response()->json($service->scan($request->user(),$session,$data['barcode'])); } }
