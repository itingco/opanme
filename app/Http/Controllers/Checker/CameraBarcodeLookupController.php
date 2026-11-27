<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\ScanSession;
use App\Services\CycleBarcodeScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CameraBarcodeLookupController extends Controller
{
    public function __invoke(Request $request, ScanSession $session, CycleBarcodeScanService $service): JsonResponse
    {
        $data = $request->validate(['barcode' => ['required', 'string', 'max:150']]);
        return response()->json($service->preview($request->user(), $session, $data['barcode']));
    }
}
