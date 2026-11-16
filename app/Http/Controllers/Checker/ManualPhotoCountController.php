<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\CheckerAssignment;
use App\Models\ScanSession;
use App\Models\ScanTransaction;
use App\Models\StockOpnameCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManualPhotoCountController extends Controller
{
    public function store(Request $request, ScanSession $session): JsonResponse
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);
        $itemCode = strtoupper(trim($data['item_code']));
        if ($itemCode === '' || ! is_finite((float) $data['qty'])) {
            throw ValidationException::withMessages(['item_code' => 'Kode barang atau qty tidak valid.']);
        }

        $session->loadMissing('cycle');
        if ((int) $session->checker_id !== (int) $request->user()->id || $session->ended_at) {
            abort(403, 'Sesi checker tidak aktif.');
        }
        if ($session->cycle->status !== StockOpnameCycle::STATUS_OPEN) {
            throw ValidationException::withMessages(['item_code' => 'Cycle tidak lagi OPEN.']);
        }
        if (! CheckerAssignment::query()->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)
            ->where('checker_id', $request->user()->id)->exists()) {
            abort(403, 'Anda tidak ditugaskan pada warehouse ini.');
        }

        // Identitas harus berasal dari cycle dan warehouse aktif, bukan query ERP.
        $item = DB::table('stock_snapshots')
            ->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)
            ->whereRaw('UPPER(item_code) = ?', [$itemCode])
            ->first(['item_id', 'item_code', 'item_name']);
        if (! $item) {
            throw ValidationException::withMessages([
                'item_code' => 'Kode barang tidak ada di snapshot warehouse cycle ini. Input manual hanya untuk item ERP tanpa barcode.',
            ]);
        }

        $path = $request->file('photo')->store('opname/manual-photos/'.$session->cycle_id, 'local');
        if (! $path) {
            throw ValidationException::withMessages(['photo' => 'Gagal menyimpan foto.']);
        }

        try {
            $scan = DB::transaction(function () use ($request, $session, $item, $data, $path) {
                $cycle = StockOpnameCycle::query()->lockForUpdate()->findOrFail($session->cycle_id);
                if ($cycle->status !== StockOpnameCycle::STATUS_OPEN) {
                    throw ValidationException::withMessages(['item_code' => 'Cycle sudah ditutup.']);
                }
                $scan = ScanTransaction::create([
                    'cycle_id' => $cycle->id,
                    'scan_session_id' => $session->id,
                    'warehouse_id' => $session->warehouse_id,
                    'checker_id' => $request->user()->id,
                    'location' => $session->location,
                    'alias_code' => 'MANUAL:'.$item->item_code,
                    'item_id' => $item->item_id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'uom_level' => 1,
                    'uom_code' => 'PCS',
                    'ratio_used' => 1,
                    'physical_qty' => round((float) $data['qty'], 4),
                    'scanned_at' => now(),
                ]);
                DB::table('stock_opname_manual_photo_evidence')->insert([
                    'scan_transaction_id' => $scan->id,
                    'cycle_id' => $cycle->id,
                    'warehouse_id' => $session->warehouse_id,
                    'checker_id' => $request->user()->id,
                    'file_path' => $path,
                    'created_at' => now(),
                ]);
                return $scan;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return response()->json([
            'ok' => true,
            'item_code' => $scan->item_code,
            'item_name' => $scan->item_name,
            'qty' => number_format((float) $scan->physical_qty, 4, '.', ''),
            'message' => 'Qty manual dan bukti foto berhasil disimpan.',
        ]);
    }
}
