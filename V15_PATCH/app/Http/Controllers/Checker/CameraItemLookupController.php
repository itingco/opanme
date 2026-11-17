<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\CheckerAssignment;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CameraItemLookupController extends Controller
{
    public function __invoke(Request $request, ScanSession $session): JsonResponse
    {
        $validated = $request->validate(['item_code' => ['required', 'string', 'max:100']]);
        $session->loadMissing('cycle');
        if ((int) $session->checker_id !== (int) $request->user()->id ||
            $session->ended_at !== null ||
            $session->cycle->status !== StockOpnameCycle::STATUS_OPEN) {
            throw ValidationException::withMessages(['item_code' => 'Sesi checker tidak aktif atau cycle sudah ditutup.']);
        }
        abort_unless(CheckerAssignment::query()
            ->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)
            ->where('checker_id', $request->user()->id)->exists(), 403);

        $code = strtoupper(trim($validated['item_code']));
        $matches = DB::table('stock_snapshots')
            ->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$code])
            ->distinct()
            ->limit(2)
            ->get(['item_id', 'item_code', 'item_name']);

        if ($matches->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => "Kode barang {$code} tidak ditemukan pada snapshot gudang dan cycle aktif (database {$session->cycle->source_database}).",
            ], 404);
        }
        if ($matches->count() > 1) {
            return response()->json(['ok' => false, 'message' => 'Kode barang memiliki lebih dari satu item. Hubungi admin.'], 422);
        }
        $item = $matches->first();
        return response()->json([
            'ok' => true,
            'item_code' => $item->item_code,
            'item_name' => $item->item_name,
            'item_id' => (int) $item->item_id,
            'source_database' => $session->cycle->source_database,
        ]);
    }
}
