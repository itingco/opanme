<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\SampleCycleItem;
use App\Models\SampleCycleItemStock;
use App\Models\SampleCycleWarehouse;
use App\Models\StockOpnameCycle;
use App\Models\User;
use App\Services\ErpCatalogService;
use App\Services\SampleCycleNumberService;
use App\Services\WarehouseSamplingMultiStockService;
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
            ->with(['creator:id,name', 'checker:id,name', 'warehouses:id,sample_cycle_id,source_database,warehouse_code,warehouse_name'])
            ->withCount([
                'items',
                'items as checked_items_count' => fn ($q) => $q->whereNotNull('checked_at'),
                'items as validated_items_count' => fn ($q) => $q->whereNotNull('validated_at'),
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
            'warehouse_keys' => ['required', 'array', 'min:1', 'max:50'],
            'warehouse_keys.*' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
            'target_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            'assigned_checker_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $checker = $this->checker((int) $data['assigned_checker_id']);
        $warehouses = $this->resolveWarehouses($data['warehouse_keys'], $erp);
        $first = $warehouses[0];

        $cycle = DB::transaction(function () use ($request, $data, $checker, $warehouses, $first, $numbers): SampleCycle {
            $cycle = SampleCycle::create([
                'cycle_no' => $numbers->next(now()),
                'created_by' => $request->user()->id,
                // Legacy required columns retain the first selected warehouse for compatibility.
                'source_database' => $first['source_database'],
                'erp_warehouse_id' => $first['warehouse_id'],
                'warehouse_code' => $first['warehouse_code'],
                'warehouse_name' => $first['warehouse_name'],
                'location' => trim($data['location']),
                'status' => SampleCycle::STATUS_DRAFT,
                'started_at' => now(),
                'cycle_type' => SampleCycle::TYPE_WAREHOUSE,
                'target_percentage' => round((float) $data['target_percentage'], 2),
                'assigned_checker_id' => $checker->id,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]);

            foreach ($warehouses as $warehouse) {
                $cycle->warehouses()->create([
                    'source_database' => $warehouse['source_database'],
                    'erp_warehouse_id' => $warehouse['warehouse_id'],
                    'warehouse_code' => $warehouse['warehouse_code'],
                    'warehouse_name' => $warehouse['warehouse_name'],
                ]);
            }

            return $cycle;
        });

        return redirect()->route('warehouse.admin.show', $cycle)
            ->with('success', 'Periode multi-gudang dibuat. Pilih item yang akan dihitung secara gabungan, lalu serahkan ke Checker Gudang.');
    }

    public function show(Request $request, SampleCycle $sampleCycle): View
    {
        $this->assertManage($request, $sampleCycle);

        $sampleCycle->load([
            'creator:id,name',
            'checker:id,name,username',
            'warehouses' => fn ($q) => $q->orderBy('source_database')->orderBy('warehouse_code'),
        ])->loadCount([
            'items',
            'items as checked_items_count' => fn ($q) => $q->whereNotNull('checked_at'),
            'items as validated_items_count' => fn ($q) => $q->whereNotNull('validated_at'),
        ]);

        $items = $sampleCycle->items()
            ->with([
                'checker:id,name',
                'validator:id,name',
                'stocks.warehouse:id,sample_cycle_id,source_database,warehouse_code,warehouse_name',
            ])
            ->orderBy('line_no')
            ->paginate(50);

        return view('warehouse_sampling.admin.show', [
            'period' => $sampleCycle,
            'items' => $items,
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

        return back()->with('success', 'Pengaturan periode diperbarui. Daftar gudang tidak diubah setelah periode dibuat agar snapshot stok tetap konsisten.');
    }

    public function availableItems(
        Request $request,
        SampleCycle $sampleCycle,
        WarehouseSamplingMultiStockService $multiStock
    ): JsonResponse {
        $this->assertManage($request, $sampleCycle);
        abort_unless($sampleCycle->isDraft(), 422, 'Daftar item sudah diserahkan ke checker dan tidak dapat diubah.');

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = trim((string) ($data['q'] ?? ''));
        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = max(10, min(100, (int) ($data['per_page'] ?? 50)));

        // IMPORTANT: the admin chooses a combined item, not an item per warehouse.
        // availableItems() has already merged the same ItemCode + UOM across every
        // database/warehouse in the period and summed its system quantity.
        $rows = $multiStock->availableItems($sampleCycle);

        if ($query !== '') {
            $needle = mb_strtoupper($query);
            $rows = $rows->filter(function (array $row) use ($needle): bool {
                return str_contains(mb_strtoupper($row['item_code']), $needle)
                    || str_contains(mb_strtoupper($row['item_name']), $needle);
            })->values();
        }

        $selectedCounts = SampleCycleItem::query()
            ->where('sample_cycle_id', $sampleCycle->id)
            ->get(['item_code', 'uom_code'])
            ->groupBy(fn (SampleCycleItem $item): string => mb_strtoupper(trim($item->item_code)).'|'.mb_strtoupper(trim($item->uom_code)))
            ->map->count();

        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $pageRows = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $dataRows = $pageRows->map(function (array $row) use ($selectedCounts): array {
            return [
                'key' => $row['key'],
                'item_code' => $row['item_code'],
                'item_name' => $row['item_name'],
                'uom_code' => $row['uom_code'],
                'system_qty' => number_format((float) $row['total_system_qty'], 4, '.', ''),
                'selected_count' => (int) ($selectedCounts[$row['key']] ?? 0),
            ];
        })->all();

        return response()->json([
            'data' => $dataRows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    public function addItems(
        Request $request,
        SampleCycle $sampleCycle,
        WarehouseSamplingMultiStockService $multiStock
    ): RedirectResponse {
        $this->assertManage($request, $sampleCycle);
        abort_unless($sampleCycle->isDraft(), 422, 'Daftar item sudah dikunci setelah diserahkan ke checker.');

        $data = $request->validate([
            'item_keys' => ['required', 'array', 'min:1', 'max:500'],
            'item_keys.*' => ['required', 'string', 'max:220'],
        ]);

        $materialized = $multiStock->materialize($sampleCycle, $data['item_keys']);
        if ($materialized === []) {
            throw ValidationException::withMessages([
                'item_keys' => 'Item yang dipilih sudah tidak memiliki stok positif pada gudang periode atau tidak ditemukan di ERP.',
            ]);
        }

        $inserted = DB::transaction(function () use ($sampleCycle, $materialized): int {
            $locked = SampleCycle::query()->lockForUpdate()->findOrFail($sampleCycle->id);
            abort_unless($locked->isDraft(), 422, 'Daftar item sudah dikunci.');

            $lineNo = (int) SampleCycleItem::query()
                ->where('sample_cycle_id', $locked->id)
                ->max('line_no');
            $count = 0;

            foreach ($materialized as $row) {
                $lineNo++;
                $item = SampleCycleItem::create([
                    'sample_cycle_id' => $locked->id,
                    'line_no' => $lineNo,
                    'item_id' => $row['item_id'],
                    'item_code' => $row['item_code'],
                    'item_name' => $row['item_name'],
                    'uom_code' => $row['uom_code'],
                    'system_qty' => $row['system_qty'],
                ]);

                foreach ($row['stocks'] as $stock) {
                    $item->stocks()->create($stock);
                }
                $count++;
            }

            return $count;
        });

        return back()->with('success', $inserted.' item berhasil ditambahkan. Stok sistem sudah disnapshot terpisah untuk setiap database/gudang. Item yang sama tetap dapat dipilih lagi pada penyimpanan berikutnya.');
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
        return back()->with('success', 'Daftar dikunci. Checker Gudang sekarang mengisi satu Qty Fisik Total untuk setiap item.');
    }

    public function validateItem(
        Request $request,
        SampleCycle $sampleCycle,
        SampleCycleItem $sampleCycleItem
    ): RedirectResponse {
        $this->assertManage($request, $sampleCycle);
        abort_unless((int) $sampleCycleItem->sample_cycle_id === (int) $sampleCycle->id, 404);
        abort_unless($sampleCycleItem->checked_at !== null && $sampleCycleItem->physical_qty !== null, 422, 'Checker belum mengisi Qty Fisik Total untuk item ini.');

        $data = $request->validate([
            'validation_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $sampleCycleItem, $data): void {
            $item = SampleCycleItem::query()->lockForUpdate()->findOrFail($sampleCycleItem->id);
            $stocks = SampleCycleItemStock::query()
                ->where('sample_cycle_item_id', $item->id)
                ->with('warehouse')
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $physicalTotal = round((float) $item->physical_qty, 4);
            $eligible = $stocks->filter(
                fn (SampleCycleItemStock $stock): bool => $stock->item_id !== null && (float) $stock->system_qty > 0
            )->values();
            $systemTotal = round((float) $eligible->sum(fn (SampleCycleItemStock $stock): float => (float) $stock->system_qty), 4);

            if ($systemTotal <= 0 && $physicalTotal > 0) {
                throw ValidationException::withMessages([
                    'validation_note' => 'Distribusi proporsional tidak dapat dihitung karena total stok sistem gudang adalah 0.',
                ]);
            }

            $allocations = [];
            $allocated = 0.0;
            foreach ($eligible as $index => $stock) {
                $isLast = $index === $eligible->count() - 1;
                $value = $isLast
                    ? round($physicalTotal - $allocated, 4)
                    : round($physicalTotal * ((float) $stock->system_qty / $systemTotal), 4);

                // Protect against tiny negative values created by floating-point rounding.
                if (abs($value) < 0.0001) {
                    $value = 0.0;
                }

                $allocations[(int) $stock->id] = $value;
                $allocated = round($allocated + $value, 4);
            }

            foreach ($stocks as $stock) {
                $physical = round((float) ($allocations[(int) $stock->id] ?? 0), 4);
                $system = round((float) $stock->system_qty, 4);
                $stock->update([
                    'allocated_physical_qty' => $physical,
                    'result' => abs($physical - $system) < 0.0001
                        ? SampleCheck::RESULT_MATCH
                        : SampleCheck::RESULT_MISMATCH,
                ]);
            }

            $item->update([
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
                'validation_note' => trim((string) ($data['validation_note'] ?? '')) ?: null,
            ]);
        });

        return back()->with('success', 'Validasi '.$sampleCycleItem->item_code.' berhasil. Qty fisik checker sudah didistribusikan proporsional ke seluruh gudang berdasarkan snapshot stok sistem.');
    }

    public function close(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertManage($request, $sampleCycle);

        if ($sampleCycle->isClosed()) {
            return back()->with('success', 'Periode sampling sudah ditutup.');
        }

        $total = $sampleCycle->items()->count();
        abort_if($total === 0, 422, 'Periode belum memiliki item sampling.');
        abort_if(
            $sampleCycle->items()->whereNull('checked_at')->exists(),
            422,
            'Periode belum dapat ditutup. Checker Gudang harus finalisasi seluruh Qty Fisik terlebih dahulu.'
        );
        abort_if(
            $sampleCycle->items()->whereNull('validated_at')->exists(),
            422,
            'Periode belum dapat ditutup. Admin Gudang harus menyelesaikan validasi seluruh item terlebih dahulu.'
        );

        $sampleCycle->update(['status' => SampleCycle::STATUS_CLOSED, 'closed_at' => now()]);

        return redirect()->route('warehouse.history.index')
            ->with('success', 'Periode sampling ditutup dan sudah masuk ke History Sampling.');
    }

    private function resolveWarehouses(array $keys, ErpCatalogService $erp): array
    {
        $allowedDatabases = [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI];
        $requested = collect($keys)->map(function ($key) use ($allowedDatabases): array {
            $parts = explode('|', (string) $key, 2);
            $source = strtoupper(trim($parts[0] ?? ''));
            $warehouseId = (int) ($parts[1] ?? 0);
            if (! in_array($source, $allowedDatabases, true) || $warehouseId <= 0) {
                throw ValidationException::withMessages(['warehouse_keys' => 'Pilihan database/gudang tidak valid.']);
            }
            return ['source_database' => $source, 'warehouse_id' => $warehouseId];
        })->unique(fn (array $row) => $row['source_database'].'|'.$row['warehouse_id'])->values();

        $catalogs = [];
        foreach ($requested->pluck('source_database')->unique() as $source) {
            $catalogs[$source] = collect($erp->warehouses($source))->keyBy('warehouse_id');
        }

        $result = [];
        foreach ($requested as $row) {
            $warehouse = $catalogs[$row['source_database']]->get($row['warehouse_id']);
            if (! $warehouse) {
                throw ValidationException::withMessages([
                    'warehouse_keys' => 'Salah satu gudang tidak valid atau sudah nonaktif di ERP '.$row['source_database'].'.',
                ]);
            }
            $result[] = [
                'source_database' => $row['source_database'],
                'warehouse_id' => (int) $warehouse['warehouse_id'],
                'warehouse_code' => (string) $warehouse['warehouse_code'],
                'warehouse_name' => (string) $warehouse['warehouse_name'],
            ];
        }

        return $result;
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
