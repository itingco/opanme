<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\ScanSession;
use App\Services\CycleBarcodeScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CameraItemLookupController extends Controller
{
    public function __invoke(Request $request, ScanSession $session, CycleBarcodeScanService $service): JsonResponse
    {
        $validated = $request->validate(['item_code' => ['required', 'string', 'max:100']]);
        return response()->json($service->preview($request->user(), $session, $validated['item_code'], true));
    }
}
