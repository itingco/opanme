<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameCycle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CycleSamplingItemHistoryController extends Controller
{
    public function photo(StockOpnameCycle $cycle, int $scanId): BinaryFileResponse
    {
        $evidence = DB::table('stock_opname_manual_photo_evidence')
            ->where('cycle_id', $cycle->id)->where('scan_transaction_id', $scanId)->first();
        abort_unless($evidence && Storage::disk('local')->exists($evidence->file_path), 404);
        return response()->file(Storage::disk('local')->path($evidence->file_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(Request $request, StockOpnameCycle $cycle, int $warehouseId, int $itemId): View
    {
        $warehouse = $cycle->warehouses()->findOrFail($warehouseId);
        $item = DB::table('stock_snapshots')
            ->where('cycle_id', $cycle->id)->where('warehouse_id', $warehouse->id)
            ->where('item_id', $itemId)->first(['item_code', 'item_name']);
        if (! $item) {
            $item = DB::table('scan_transactions')
                ->where('cycle_id', $cycle->id)->where('warehouse_id', $warehouse->id)
                ->where('item_id', $itemId)->first(['item_code', 'item_name']);
        }
        abort_unless($item !== null, 404, 'Barang tidak ditemukan pada cycle ini.');

        $dates = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $today = CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'));
        $custom = $request->exists('from') || $request->exists('to');
        $dateFrom = $custom
            ? ['from' => $dates['from'] ?? '', 'to' => $dates['to'] ?? '']
            : ['from' => $today->subMonthsNoOverflow(3)->toDateString(), 'to' => $today->toDateString()];
        if ($dateFrom['from'] !== '' && $dateFrom['to'] !== '' && $dateFrom['from'] > $dateFrom['to']) {
            throw ValidationException::withMessages(['from' => 'Tanggal mulai tidak boleh melebihi tanggal akhir.']);
        }

        // Sampling Gudang: include OPEN, DRAFT and CLOSED. Use actual checked/validated
        // timestamp where closed_at is NULL, rather than filtering solely on closed_at.
        $gudang = DB::table('sample_cycle_item_stocks as stock')
            ->join('sample_cycle_items as sci', 'sci.id', '=', 'stock.sample_cycle_item_id')
            ->join('sample_cycles as sc', 'sc.id', '=', 'sci.sample_cycle_id')
            ->join('sample_cycle_warehouses as sw', 'sw.id', '=', 'stock.sample_cycle_warehouse_id')
            ->leftJoin('users as checked_user', 'checked_user.id', '=', 'sci.checked_by')
            ->leftJoin('users as validated_user', 'validated_user.id', '=', 'sci.validated_by')
            ->where('sc.cycle_type', 'WAREHOUSE')
            ->where('sw.source_database', $cycle->source_database)
            ->where('sw.erp_warehouse_id', $warehouse->erp_warehouse_id)
            ->where('sci.item_code', $item->item_code)
            ->selectRaw("COALESCE(sci.checked_at, sci.validated_at, sc.closed_at, sc.started_at, sc.created_at) as event_at")
            ->selectRaw("'SAMPLING GUDANG' as sampling_type")
            ->addSelect('sc.cycle_no', 'sc.status as cycle_status', 'sc.location',
                'sci.checked_at', 'sci.validated_at', 'sci.checker_comment', 'sci.validation_note',
                'checked_user.name as checker_name', 'validated_user.name as validator_name',
                'stock.system_qty', 'stock.validation_system_qty', 'stock.allocated_physical_qty as physical_qty', 'stock.result');

        // Sampling Gerai (sample_checks) is separate from the Warehouse Sampling workflow.
        // Include it because historical check results must not vanish just because
        // they are not linked to a CLOSED warehouse sampling period.
        $gerai = DB::table('sample_checks as chk')
            ->leftJoin('sample_cycles as sc', 'sc.id', '=', 'chk.sample_cycle_id')
            ->leftJoin('users as checker', 'checker.id', '=', 'chk.user_id')
            ->where('chk.source_database', $cycle->source_database)
            ->where('chk.erp_warehouse_id', $warehouse->erp_warehouse_id)
            ->where('chk.item_code', $item->item_code)
            ->selectRaw('chk.scanned_at as event_at')
            ->selectRaw("'SAMPLING GERAI' as sampling_type")
            ->addSelect('sc.cycle_no', 'sc.status as cycle_status', 'chk.location')
            ->selectRaw('chk.scanned_at as checked_at')
            ->selectRaw('NULL as validated_at, NULL as checker_comment, NULL as validation_note')
            ->addSelect('checker.name as checker_name')
            ->selectRaw('NULL as validator_name')
            ->addSelect('chk.system_qty')
            ->selectRaw('chk.system_qty as validation_system_qty')
            ->addSelect('chk.physical_qty', 'chk.result');

        $union = $gudang->unionAll($gerai);
        $history = DB::query()->fromSub($union, 'h')
            ->when($dateFrom['from'] !== '', fn ($q) => $q->where('event_at', '>=', $dateFrom['from'].' 00:00:00'))
            ->when($dateFrom['to'] !== '', fn ($q) => $q->where('event_at', '<', CarbonImmutable::parse($dateFrom['to'])->addDay()->toDateString().' 00:00:00'))
            ->orderByDesc('event_at')->paginate(40)->withQueryString();

        return view('admin.cycles.sampling-item-history', compact('cycle', 'warehouse', 'item', 'history', 'dateFrom'));
    }
}
