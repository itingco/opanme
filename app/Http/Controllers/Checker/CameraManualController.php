<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use App\Services\CycleBarcodeScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CameraManualController extends Controller
{
    public function store(Request $request, ScanSession $session, CycleBarcodeScanService $service): JsonResponse
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'numeric', 'min:0.0001', 'max:999999999'],
            'photo' => ['required', 'string', 'max:8500000'],
        ]);
        $result = $service->scan($request->user(), $session, $data['item_code'], $data['qty'], $data['photo'], true);
        return response()->json($result + ['message' => 'Qty dan foto berhasil disimpan.']);
    }

    public function photo(Request $request, ScanSession $session, int $scanId)
    {
        abort_unless($request->user()->isAdmin() || (int)$session->checker_id === (int)$request->user()->id, 403);
        $exists = DB::table('scan_transactions')->where('id', $scanId)->where('scan_session_id', $session->id)->exists();
        abort_unless($exists, 404);
        $photo = DB::table('opname_manual_scan_photos')
            ->where('scan_transaction_id', $scanId)
            ->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)
            ->first();
        abort_unless($photo && Storage::disk('local')->exists($photo->photo_path), 404);
        return response()->file(Storage::disk('local')->path($photo->photo_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function adminPhoto(Request $request, StockOpnameCycle $cycle, int $warehouseId, int $scanId)
    {
        $photo = DB::table('opname_manual_scan_photos')
            ->where('scan_transaction_id', $scanId)
            ->where('cycle_id', $cycle->id)
            ->where('warehouse_id', $warehouseId)
            ->first();
        abort_unless($photo && Storage::disk('local')->exists($photo->photo_path), 404);
        return response()->file(Storage::disk('local')->path($photo->photo_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
