<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\ScanSession;
use App\Services\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function store(Request $request, ScanSession $session, ScanService $service): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:150'],
        ]);

        return response()->json($service->scan($request->user(), $session, $data['barcode']));
    }

    public function storeNonSystem(Request $request, ScanSession $session, ScanService $service): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:150'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'uom_code' => ['required', 'string', 'max:50'],
            'smallest_uom_code' => ['required', 'string', 'max:50'],
            'ratio_to_smallest' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json($service->storeNonSystemScan($request->user(), $session, $data));
    }
}
