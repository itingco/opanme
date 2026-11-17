<?php

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\Controller;
use App\Models\CheckerAssignment;
use App\Models\ItemBarcode;
use App\Models\ScanSession;
use App\Models\StockOpnameCycle;
use App\Models\StockOpnameDiscoveredItem;
use App\Models\UomRatio;
use App\Support\ScanCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CameraBarcodeLookupController extends Controller
{
    public function __invoke(Request $request, ScanSession $session): JsonResponse
    {
        $data = $request->validate(['barcode' => ['required', 'string', 'max:150']]);
        $code = trim($data['barcode']);
        if (preg_match('~^https?://~i', $code)) {
            return response()->json(['ok' => false, 'ignored_url' => true, 'message' => 'QR website diabaikan.'], 422);
        }

        $session->loadMissing('cycle');
        abort_unless(
            (int) $session->checker_id === (int) $request->user()->id
            && $session->ended_at === null
            && $session->cycle->status === StockOpnameCycle::STATUS_OPEN,
            403
        );
        abort_unless(CheckerAssignment::query()
            ->where('cycle_id', $session->cycle_id)
            ->where('warehouse_id', $session->warehouse_id)
            ->where('checker_id', $request->user()->id)
            ->exists(), 403);

        $mapping = ItemBarcode::query()->where('barcode', $code)->first();
        $qr = null;
        if (! $mapping) {
            try {
                $qr = ScanCode::parseQrPayload($code);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['barcode' => $e->getMessage()]);
            }
        }

        if ($qr !== null) {
            $itemCode = strtoupper(trim($qr['item_code']));
            $qty = (float) $qr['qty'];
            $uom = 'PCS';
            $ratio = 1.0;
            $kind = 'QR KODE~QTY';
        } elseif ($mapping) {
            $itemCode = strtoupper(trim((string) $mapping->item_code));
            $uom = strtoupper(trim((string) $mapping->uom_code));
            $ratioRow = UomRatio::query()->where('item_code', $itemCode)->where('uom_code', $uom)->first();
            if (! $ratioRow || ! is_numeric($ratioRow->ratio) || (float) $ratioRow->ratio <= 0) {
                throw ValidationException::withMessages(['barcode' => 'Ratio belum valid untuk '.$itemCode.' / '.$uom.'.']);
            }
            $ratio = (float) $ratioRow->ratio;
            $qty = 1.0;
            $kind = 'BARCODE MASTER';
        } else {
            $known = StockOpnameDiscoveredItem::query()
                ->where('cycle_id', $session->cycle_id)->where('alias_code', $code)->first();
            if ($known) {
                $ratio = (float) $known->default_ratio_to_smallest;
                if ($ratio <= 0) $ratio = 1.0;
                return response()->json([
                    'ok' => true, 'barcode' => $code, 'item_code' => $known->alias_code,
                    'item_name' => $known->item_name, 'uom_code' => $known->default_uom_code,
                    'ratio' => $ratio, 'qty' => 1, 'physical_qty' => $ratio,
                    'kind' => 'NON-SYSTEM CYCLE', 'source_database' => $session->cycle->source_database,
                ]);
            }
            return response()->json(['ok' => false, 'message' => 'Barcode tidak ditemukan di master maupun daftar Non-System cycle ini.'], 404);
        }

        // Align with ScanService V6+: do not approve a barcode which would fail its cycle snapshot lookup.
        $items = DB::table('stock_snapshots')
            ->where('cycle_id', $session->cycle_id)
            ->whereRaw('UPPER(TRIM(item_code)) = ?', [$itemCode])
            ->select('item_id', 'item_code', 'item_name')
            ->distinct()->limit(2)->get();
        if ($items->isEmpty()) {
            $items = DB::table('scan_transactions')
                ->where('cycle_id', $session->cycle_id)
                ->whereRaw('UPPER(TRIM(item_code)) = ?', [$itemCode])
                ->select('item_id', 'item_code', 'item_name')->distinct()->limit(2)->get();
        }
        if ($items->count() !== 1) {
            return response()->json(['ok' => false, 'message' => $items->isEmpty()
                ? 'Kode '.$itemCode.' tidak ada pada snapshot/riwayat scan cycle aktif.'
                : 'Kode '.$itemCode.' ambigu pada cycle ini.'], 422);
        }
        $item = $items->first();
        return response()->json([
            'ok' => true, 'barcode' => $code, 'item_code' => $item->item_code,
            'item_name' => $item->item_name, 'uom_code' => $uom,
            'ratio' => $ratio, 'qty' => $qty, 'physical_qty' => $qty * $ratio,
            'kind' => $kind, 'source_database' => $session->cycle->source_database,
        ]);
    }
}
