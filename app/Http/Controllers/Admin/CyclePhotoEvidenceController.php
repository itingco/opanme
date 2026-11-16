<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CyclePhotoEvidenceController extends Controller
{
    /**
     * Show every scan for one item in the selected cycle and warehouse,
     * with evidence from either of the existing manual-photo implementations.
     */
    public function index(StockOpnameCycle $cycle, int $warehouseId, int $itemId): View
    {
        $warehouse = $cycle->warehouses()->findOrFail($warehouseId);
        $item = DB::table('stock_snapshots')
            ->where('cycle_id', $cycle->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $itemId)
            ->first(['item_id', 'item_code', 'item_name']);

        $scans = DB::table('scan_transactions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.checker_id')
            ->where('s.cycle_id', $cycle->id)
            ->where('s.warehouse_id', $warehouse->id)
            ->where('s.item_id', $itemId)
            ->select('s.*', 'u.name as checker_name')
            ->orderByDesc('s.scanned_at')
            ->orderByDesc('s.id')
            ->get();

        abort_unless($item || $scans->isNotEmpty(), 404);
        if (! $item) {
            $item = (object) ['item_id' => $itemId, 'item_code' => $scans->first()->item_code, 'item_name' => $scans->first()->item_name];
        }

        $evidence = $this->evidenceForScans($scans->pluck('id')->all(), (int) $cycle->id, (int) $warehouse->id);
        foreach ($scans as $scan) {
            $scan->evidence = $evidence[(int) $scan->id] ?? null;
        }

        return view('admin.cycles.photo-evidence', compact('cycle', 'warehouse', 'item', 'scans'));
    }

    /** Images are private: only accessed through the admin-authenticated route. */
    public function photo(Request $request, StockOpnameCycle $cycle, int $warehouseId, int $itemId, int $scanId): BinaryFileResponse
    {
        $warehouse = $cycle->warehouses()->findOrFail($warehouseId);
        $scanExists = DB::table('scan_transactions')
            ->where('id', $scanId)
            ->where('cycle_id', $cycle->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $itemId)
            ->exists();
        abort_unless($scanExists, 404);

        $evidence = $this->evidenceForScans([$scanId], (int) $cycle->id, (int) $warehouse->id);
        $row = $evidence[$scanId] ?? null;
        abort_unless($row, 404);

        $path = (string) $row->path;
        // Explicit allowlist for evidence files, not arbitrary local-disk reads.
        abort_unless(str_starts_with($path, 'opname/manual-photos/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);
        $absolute = Storage::disk('local')->path($path);
        $mime = @mime_content_type($absolute);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /** @return array<int,object> */
    private function evidenceForScans(array $scanIds, int $cycleId, int $warehouseId): array
    {
        if ($scanIds === []) return [];

        $result = [];
        // V12 / V13 image data-URI upload table.
        if (Schema::hasTable('opname_manual_scan_photos')) {
            foreach (DB::table('opname_manual_scan_photos')
                ->where('cycle_id', $cycleId)->where('warehouse_id', $warehouseId)
                ->whereIn('scan_transaction_id', $scanIds)
                ->get(['scan_transaction_id', 'photo_path as path', 'created_at']) as $row) {
                $result[(int) $row->scan_transaction_id] = $row;
            }
        }
        // V9 / V11 multipart upload table (fallback for older evidence).
        if (Schema::hasTable('stock_opname_manual_photo_evidence')) {
            foreach (DB::table('stock_opname_manual_photo_evidence')
                ->where('cycle_id', $cycleId)->where('warehouse_id', $warehouseId)
                ->whereIn('scan_transaction_id', $scanIds)
                ->get(['scan_transaction_id', 'file_path as path', 'created_at']) as $row) {
                $key = (int) $row->scan_transaction_id;
                if (! isset($result[$key])) $result[$key] = $row;
            }
        }
        return $result;
    }
}
