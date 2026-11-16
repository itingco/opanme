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

class CameraManualController extends Controller
{
    public function store(Request $request, ScanSession $session): JsonResponse
    {
        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:100'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'photo' => ['required', 'string', 'max:11000000'],
        ]);
        $this->assertSession($request, $session);
        $code = strtoupper(trim($data['item_code']));
        $photo = $this->decodePhoto($data['photo']);
        $path = null;
        try {
            // Transaction ties evidence to an actual scan. No partial scan on errors.
            $scan = DB::transaction(function () use ($request, $session, $code, $data, $photo, &$path) {
                $cycle = StockOpnameCycle::query()->lockForUpdate()->findOrFail($session->cycle_id);
                if ($cycle->status !== StockOpnameCycle::STATUS_OPEN) {
                    throw ValidationException::withMessages(['item_code' => 'Cycle tidak OPEN.']);
                }
                $item = DB::table('stock_snapshots')
                    ->where('cycle_id', $session->cycle_id)
                    ->where('warehouse_id', $session->warehouse_id)
                    ->whereRaw('UPPER(TRIM(item_code)) = ?', [$code])
                    ->first(['item_id', 'item_code', 'item_name']);
                if (! $item) {
                    throw ValidationException::withMessages(['item_code' => 'Item Code tidak ditemukan pada snapshot warehouse dan cycle aktif.']);
                }
                $qty = number_format((float)$data['qty'], 4, '.', '');
                $scan = ScanTransaction::create([
                    'cycle_id' => $session->cycle_id,
                    'scan_session_id' => $session->id,
                    'warehouse_id' => $session->warehouse_id,
                    'checker_id' => $request->user()->id,
                    'location' => $session->location,
                    'alias_code' => 'MANUAL-PHOTO:'.$code,
                    'item_id' => $item->item_id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'uom_level' => 1,
                    'uom_code' => 'PCS', // Qty provided in smallest UOM, as QR KODE~QTY.
                    'ratio_used' => 1,
                    'physical_qty' => $qty,
                    'scanned_at' => now(),
                ]);
                $path = 'opname/manual-photos/'.$session->cycle_id.'/'.$scan->id.'-'.bin2hex(random_bytes(8)).'.'.$photo['ext'];
                if (! Storage::disk('local')->put($path, $photo['bytes'])) {
                    throw new \RuntimeException('Foto bukti gagal disimpan.');
                }
                DB::table('opname_manual_scan_photos')->insert([
                    'scan_transaction_id' => $scan->id,
                    'cycle_id' => $session->cycle_id,
                    'warehouse_id' => $session->warehouse_id,
                    'checker_id' => $request->user()->id,
                    'photo_path' => $path,
                    'created_at' => now(),
                ]);
                return $scan;
            });
        } catch (\Throwable $e) {
            if ($path !== null) Storage::disk('local')->delete($path);
            throw $e;
        }
        return response()->json([
            'ok' => true,
            'item_code' => $scan->item_code,
            'item_name' => $scan->item_name,
            'qty' => $scan->physical_qty,
            'message' => 'Qty dan foto berhasil disimpan.',
        ]);
    }

    public function photo(Request $request, ScanSession $session, int $scanId)
    {
        abort_unless($request->user()->isAdmin() || (int)$session->checker_id === (int)$request->user()->id, 403);
        $photo = DB::table('opname_manual_scan_photos')
            ->where('scan_transaction_id', $scanId)
            ->where('cycle_id', $session->cycle_id)
            ->first();
        abort_unless($photo && Storage::disk('local')->exists($photo->photo_path), 404);
        return response()->file(Storage::disk('local')->path($photo->photo_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function adminPhoto(Request $request, \App\Models\StockOpnameCycle $cycle, int $warehouseId, int $scanId)
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

    private function assertSession(Request $request, ScanSession $session): void
    {
        $session->loadMissing('cycle');
        if ((int)$session->checker_id !== (int)$request->user()->id || $session->ended_at || $session->cycle->status !== StockOpnameCycle::STATUS_OPEN) {
            throw ValidationException::withMessages(['item_code' => 'Sesi checker tidak aktif.']);
        }
        if (! CheckerAssignment::query()->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)
            ->where('checker_id', $request->user()->id)->exists()) {
            abort(403);
        }
    }

    private function decodePhoto(string $dataUri): array
    {
        if (! preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#D', $dataUri, $m)) {
            throw ValidationException::withMessages(['photo' => 'Foto harus diambil dari kamera.']);
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) < 1000 || strlen($bytes) > 6*1024*1024) {
            throw ValidationException::withMessages(['photo' => 'Ukuran foto tidak valid (maksimal 6 MB).']);
        }
        $info = @getimagesizefromstring($bytes);
        $expected = ['jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP];
        if (! $info || $info[2] !== $expected[$m[1]] || $info[0] < 200 || $info[1] < 200) {
            throw ValidationException::withMessages(['photo' => 'Foto tidak valid atau resolusi terlalu kecil.']);
        }
        return ['bytes' => $bytes, 'ext' => $m[1] === 'jpeg' ? 'jpg' : $m[1]];
    }
}
