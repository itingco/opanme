<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SampleCycle;
use App\Models\SampleCycleItem;
use App\Models\StockOpnameCycle;
use App\Models\User;
use App\Services\ErpCatalogService;
use App\Services\ErpStockService;
use App\Services\SampleCycleNumberService;
use App\Services\WarehouseSamplingCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseSamplingController extends Controller
{
    public function index(Request $request): View
    {
        $query = SampleCycle::query()
            ->where('cycle_type', SampleCycle::TYPE_WAREHOUSE)
            ->with(['creator:id,name', 'checker:id,name'])
            ->withCount([
                'items',
                'items as checked_items_count' => fn ($q) => $q->whereNotNull('checked_at'),
            ])
            ->latest('id');

        if ($request->user()->isAdminGudang()) {
            $query->where('created_by', $request->user()->id);
        }

        return view('warehouse_sampling.admin.index', [
            'periods' => $query->paginate(20),
            'checkers' => User::query()
                ->where('role', User::ROLE_CHECKER_GUDANG)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'username']),
            'databases' => [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI],
        ]);
    }

    public function warehouses(Request $request, ErpCatalogService $erp): JsonResponse
    {
        $data = $request->validate([
            'source_database' => ['required', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
        ]);

        return response()->json(['data' => $erp->warehouses($data['source_database'])]);
    }

    public function store(
        Request $request,
        ErpCatalogService $erp,
        SampleCycleNumberService $numbers
    ): RedirectResponse {
        $data = $request->validate([
            'source_database' => ['required', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
            'erp_warehouse_id' => ['required', 'integer'],
            'location' => ['required', 'string', 'max:255'],
            'target_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            'assigned_checker_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $checker = $this->checker((int) $data['assigned_checker_id']);
        $warehouse = collect($erp->warehouses($data['source_database']))
            ->firstWhere('warehouse_id', (int) $data['erp_warehouse_id']);

        if (! $warehouse) {
            throw ValidationException::withMessages(['erp_warehouse_id' => 'Gudang tidak valid atau sudah nonaktif di ERP.']);
        }

        $cycle = SampleCycle::create([
            'cycle_no' => $numbers->next(now()),
            'created_by' => $request->user()->id,
            'source_database' => $data['source_database'],
            'erp_warehouse_id' => (int) $warehouse['warehouse_id'],
            'warehouse_code' => $warehouse['warehouse_code'],
            'warehouse_name' => $warehouse['warehouse_name'],
            'location' => trim($data['location']),
            'status' => SampleCycle::STATUS_DRAFT,
            'started_at' => now(),
            'cycle_type' => SampleCycle::TYPE_WAREHOUSE,
            'target_percentage' => round((float) $data['target_percentage'], 2),
            'assigned_checker_id' => $checker->id,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);

        return redirect()->route('warehouse.admin.show', $cycle)
            ->with('success', 'Periode sampling dibuat. Tambahkan item yang akan diperiksa, lalu serahkan ke Checker Gudang.');
    }

    public function show(Request $request, SampleCycle $sampleCycle): View
    {
        $this->assertManage($request, $sampleCycle);

        $sampleCycle->load(['creator:id,name', 'checker:id,name,username'])
            ->loadCount([
                'items',
                'items as checked_items_count' => fn ($q) => $q->whereNotNull('checked_at'),
            ]);

        return view('warehouse_sampling.admin.show', [
            'period' => $sampleCycle,
            'items' => $sampleCycle->items()->with('checker:id,name')->orderBy('line_no')->paginate(100),
            'checkers' => User::query()
                ->where('role', User::ROLE_CHECKER_GUDANG)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id','name','username']),
        ]);
    }

    public function update(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertManage($request, $sampleCycle);
        abort_if($sampleCycle->isClosed(), 422, 'Periode sudah ditutup.');

        $data = $request->validate([
            'target_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            'assigned_checker_id' => ['required', 'integer'],
            'location' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $checker = $this->checker((int) $data['assigned_checker_id']);

        $sampleCycle->update([
            'target_percentage' => round((float) $data['target_percentage'], 2),
            'assigned_checker_id' => $checker->id,
            'location' => trim($data['location']),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'Pengaturan periode diperbarui.');
    }

    public function itemSearch(
        Request $request,
        SampleCycle $sampleCycle,
        WarehouseSamplingCatalogService $catalog
    ): JsonResponse {
        $this->assertManage($request, $sampleCycle);
        abort_unless($sampleCycle->isDraft(), 422, 'Daftar item sudah diserahkan ke checker dan tidak dapat diubah.');

        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json([
            'data' => $catalog->searchItems($sampleCycle->source_database, $data['q']),
        ]);
    }

    public function addItem(
        Request $request,
        SampleCycle $sampleCycle,
        WarehouseSamplingCatalogService $catalog,
        ErpStockService $stock
    ): RedirectResponse {
        $this->assertManage($request, $sampleCycle);
        abort_unless($sampleCycle->isDraft(), 422, 'Daftar item sudah dikunci setelah diserahkan ke checker.');

        $data = $request->validate(['item_id' => ['required', 'integer', 'min:1']]);
        $item = $catalog->findItem($sampleCycle->source_database, (int) $data['item_id']);
        if (! $item) {
            throw ValidationException::withMessages(['item_id' => 'Item ERP tidak ditemukan.']);
        }

        $stockRow = $stock->findItemInWarehouse(
            $sampleCycle->source_database,
            now(),
            (int) $sampleCycle->erp_warehouse_id,
            (int) $item['item_id']
        );
        $systemQty = (float) ($stockRow['smallest_on_hand'] ?? 0);

        DB::transaction(function () use ($sampleCycle, $item, $systemQty) {
            $locked = SampleCycle::query()->lockForUpdate()->findOrFail($sampleCycle->id);
            abort_unless($locked->isDraft(), 422, 'Daftar item sudah dikunci.');

            $lineNo = ((int) SampleCycleItem::query()
                ->where('sample_cycle_id', $locked->id)
                ->max('line_no')) + 1;

            SampleCycleItem::create([
                'sample_cycle_id' => $locked->id,
                'line_no' => $lineNo,
                'item_id' => $item['item_id'],
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'],
                'uom_code' => $item['uom_code'],
                'system_qty' => round($systemQty, 4),
            ]);
        });

        return back()->with('success', $item['item_code'].' ditambahkan ke daftar sampling. Item yang sama boleh ditambahkan lagi.');
    }

    public function removeItem(Request $request, SampleCycle $sampleCycle, SampleCycleItem $sampleCycleItem): RedirectResponse
    {
        $this->assertManage($request, $sampleCycle);
        abort_unless($sampleCycle->isDraft(), 422, 'Daftar item sudah dikunci setelah diserahkan ke checker.');
        abort_unless((int) $sampleCycleItem->sample_cycle_id === (int) $sampleCycle->id, 404);

        $sampleCycleItem->delete();
        return back()->with('success', 'Baris item dihapus dari daftar sampling.');
    }

    public function release(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertManage($request, $sampleCycle);
        abort_unless($sampleCycle->isDraft(), 422, 'Periode ini bukan draft.');
        abort_if($sampleCycle->items()->count() === 0, 422, 'Tambahkan minimal satu item sebelum diserahkan ke checker.');
        $this->checker((int) $sampleCycle->assigned_checker_id);

        $sampleCycle->update(['status' => SampleCycle::STATUS_OPEN, 'started_at' => now()]);
        return back()->with('success', 'Daftar sudah dikunci dan diserahkan ke Checker Gudang.');
    }

    public function close(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertManage($request, $sampleCycle);
        if (! $sampleCycle->isClosed()) {
            $sampleCycle->update(['status' => SampleCycle::STATUS_CLOSED, 'closed_at' => now()]);
        }

        return back()->with('success', 'Periode sampling ditutup.');
    }

    private function checker(int $id): User
    {
        $checker = User::query()
            ->whereKey($id)
            ->where('role', User::ROLE_CHECKER_GUDANG)
            ->where('is_active', true)
            ->first();

        if (! $checker) {
            throw ValidationException::withMessages(['assigned_checker_id' => 'Checker Gudang tidak valid atau tidak aktif.']);
        }

        return $checker;
    }

    private function assertManage(Request $request, SampleCycle $cycle): void
    {
        abort_unless($cycle->isWarehouseSampling(), 404);
        if ($request->user()->isAdmin()) {
            return;
        }
        abort_unless($request->user()->isAdminGudang(), 403);
        abort_unless((int) $cycle->created_by === (int) $request->user()->id, 403);
    }
}
