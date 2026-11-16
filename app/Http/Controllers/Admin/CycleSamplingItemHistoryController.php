<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CycleSamplingItemHistoryController extends Controller
{
    public function photo(StockOpnameCycle $cycle, int $scanId): BinaryFileResponse
    {
        $evidence = DB::table('stock_opname_manual_photo_evidence')
            ->where('cycle_id', $cycle->id)
            ->where('scan_transaction_id', $scanId)->first();
        abort_unless($evidence && Storage::disk('local')->exists($evidence->file_path), 404);
        // File hanya dapat diakses dari route admin yang sudah dilindungi middleware role.
        return response()->file(Storage::disk('local')->path($evidence->file_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(Request $request, StockOpnameCycle $cycle, int $warehouseId, int $itemId): View
    {
        $warehouse = $cycle->warehouses()->findOrFail($warehouseId);
        $item = DB::table('stock_snapshots')
            ->where('cycle_id', $cycle->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $itemId)
            ->first(['item_code', 'item_name']);

        if (! $item) {
            $item = DB::table('scan_transactions')
                ->where('cycle_id', $cycle->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('item_id', $itemId)
                ->first(['item_code', 'item_name']);
        }
        abort_unless($item !== null, 404, 'Barang tidak ada dalam cycle dan warehouse ini.');

        // Default: tampilkan seluruh riwayat sampling gudang selama 3 bulan terakhir.
        // Jika form dikirim, hormati tanggal pilihan user tanpa batas 3 bulan.
        $validatedDates = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $hasCustomDates = $request->exists('from') || $request->exists('to');
        $today = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));
        $dateFrom = $hasCustomDates
            ? [
                'from' => $validatedDates['from'] ?? '',
                'to' => $validatedDates['to'] ?? '',
            ]
            : [
                'from' => $today->subMonthsNoOverflow(3)->toDateString(),
                'to' => $today->toDateString(),
            ];
        if ($dateFrom['from'] !== '' && $dateFrom['to'] !== '' && $dateFrom['from'] > $dateFrom['to']) {
            throw ValidationException::withMessages([
                'from' => 'Tanggal mulai tidak boleh lebih besar daripada tanggal sampai.',
            ]);
        }

        $history = DB::table('sample_cycle_item_stocks as stock')
            ->join('sample_cycle_items as sci', 'sci.id', '=', 'stock.sample_cycle_item_id')
            ->join('sample_cycles as sc', 'sc.id', '=', 'sci.sample_cycle_id')
            ->join('sample_cycle_warehouses as sw', 'sw.id', '=', 'stock.sample_cycle_warehouse_id')
            ->leftJoin('users as checked_user', 'checked_user.id', '=', 'sci.checked_by')
            ->leftJoin('users as validated_user', 'validated_user.id', '=', 'sci.validated_by')
            ->where('sc.cycle_type', 'WAREHOUSE')
            ->where('sc.status', 'CLOSED')
            ->where('sw.source_database', $cycle->source_database)
            ->where('sw.erp_warehouse_id', $warehouse->erp_warehouse_id)
            ->where('sci.item_code', $item->item_code)
            ->when($dateFrom['from'] !== '', fn ($q) => $q->where('sc.closed_at', '>=', $dateFrom['from'].' 00:00:00'))
            ->when($dateFrom['to'] !== '', fn ($q) => $q->where('sc.closed_at', '<', CarbonImmutable::parse($dateFrom['to'])->addDay()->toDateString().' 00:00:00'))
            ->select([
                'sc.cycle_no', 'sc.closed_at', 'sc.location',
                'sci.checked_at', 'sci.validated_at', 'sci.checker_comment', 'sci.validation_note',
                'checked_user.name as checker_name', 'validated_user.name as validator_name',
                'stock.system_qty', 'stock.validation_system_qty', 'stock.allocated_physical_qty',
                'stock.result',
            ])
            ->orderByDesc('sc.closed_at')
            ->orderByDesc('sci.checked_at')
            ->paginate(40)->withQueryString();

        return view('admin.cycles.sampling-item-history', compact('cycle', 'warehouse', 'item', 'history', 'dateFrom'));
    }
}
