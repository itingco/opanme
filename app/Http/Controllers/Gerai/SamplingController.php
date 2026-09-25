<?php

namespace App\Http\Controllers\Gerai;

use App\Http\Controllers\Controller;
use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\StockOpnameCycle;
use App\Models\User;
use App\Models\UserWarehouseAssignment;
use App\Services\ErpCatalogService;
use App\Services\SampleCheckingService;
use App\Services\SampleCycleNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SamplingController extends Controller
{
    /** Admin Gerai: create periods and assign them to Checker Gerai. */
    public function home(Request $request, ErpCatalogService $erp): View
    {
        $user = $request->user();
        $assignments = $this->adminAssignments($user, $erp);

        $cycles = SampleCycle::query()
            ->where('cycle_type', SampleCycle::TYPE_GERAI)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('created_by', $user->id))
            ->with(['checker:id,name,username'])
            ->withCount('checks')
            ->latest('id')
            ->paginate(20);

        $checkers = User::query()
            ->where('role', User::ROLE_CHECKER_GERAI)
            ->where('is_active', true)
            ->with('warehouseAssignments')
            ->orderBy('name')
            ->get(['id','name','username','role']);

        return view('gerai.home', [
            'user' => $user,
            'cycles' => $cycles,
            'assignments' => $assignments,
            'checkers' => $checkers,
        ]);
    }

    /** Legacy endpoint retained; now returns only warehouses assigned to Admin Gerai. */
    public function warehouses(Request $request, ErpCatalogService $erp): JsonResponse
    {
        $data = $request->validate([
            'source_database' => ['required', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
        ]);

        $assignments = collect($this->adminAssignments($request->user(), $erp))
            ->where('source_database', $data['source_database'])
            ->values()
            ->all();

        return response()->json(['data' => $assignments]);
    }

    public function create(Request $request, ErpCatalogService $erp, SampleCycleNumberService $numbers): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'warehouse_key' => ['required','string','max:100'],
            'assigned_checker_id' => ['required','integer'],
            'location' => ['required','string','max:255'],
        ]);

        $warehouse = $this->resolveAdminWarehouse($user, $data['warehouse_key'], $erp);
        $checker = User::query()
            ->where('id', (int) $data['assigned_checker_id'])
            ->where('role', User::ROLE_CHECKER_GERAI)
            ->where('is_active', true)
            ->with('warehouseAssignments')
            ->first();

        if (! $checker) {
            throw ValidationException::withMessages(['assigned_checker_id' => 'Checker Gerai tidak valid atau nonaktif.']);
        }
        if (! $checker->hasWarehouseAssignment($warehouse['source_database'], (int) $warehouse['warehouse_id'])) {
            throw ValidationException::withMessages([
                'assigned_checker_id' => 'Checker Gerai tersebut tidak di-assign ke gudang yang dipilih.',
            ]);
        }

        $cycle = DB::transaction(function () use ($user, $data, $warehouse, $checker, $numbers): SampleCycle {
            return SampleCycle::create([
                'cycle_no' => $numbers->next(now()),
                'created_by' => $user->id,
                'source_database' => $warehouse['source_database'],
                'erp_warehouse_id' => (int) $warehouse['warehouse_id'],
                'warehouse_code' => $warehouse['warehouse_code'],
                'warehouse_name' => $warehouse['warehouse_name'],
                'location' => trim($data['location']),
                'status' => SampleCycle::STATUS_OPEN,
                'started_at' => now(),
                'cycle_type' => SampleCycle::TYPE_GERAI,
                'assigned_checker_id' => $checker->id,
                'target_percentage' => 100,
            ]);
        });

        return redirect()->route('gerai.admin.home')->with(
            'success',
            "{$cycle->cycle_no} dibuat dan ditugaskan ke {$checker->name}."
        );
    }

    /** Checker Gerai: task list across all assigned warehouses/databases. */
    public function checkerHome(Request $request): View
    {
        $cycles = SampleCycle::query()
            ->where('cycle_type', SampleCycle::TYPE_GERAI)
            ->where('assigned_checker_id', $request->user()->id)
            ->whereIn('status', [SampleCycle::STATUS_OPEN, SampleCycle::STATUS_CLOSED])
            ->with(['creator:id,name'])
            ->withCount('checks')
            ->orderByRaw("CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(20);

        return view('gerai.checker_home', compact('cycles'));
    }

    public function scan(Request $request, SampleCycle $sampleCycle): View
    {
        $this->assertChecker($request, $sampleCycle);
        return view('gerai.scan', [
            'cycle' => $sampleCycle,
            'checks' => $sampleCycle->checks()->latest('scanned_at')->limit(25)->get(),
            'checkCount' => $sampleCycle->checks()->count(),
        ]);
    }

    public function lookup(Request $request, SampleCycle $sampleCycle, SampleCheckingService $service): JsonResponse
    {
        $this->assertChecker($request, $sampleCycle);
        $data = $request->validate(['barcode' => ['required','string','max:150']]);
        return response()->json($service->lookup($request->user(), $sampleCycle, $data['barcode']));
    }

    public function confirm(Request $request, SampleCycle $sampleCycle, SampleCheckingService $service): JsonResponse
    {
        $this->assertChecker($request, $sampleCycle);
        $data = $request->validate([
            'token' => ['required','string'],
            'result' => ['required', Rule::in([SampleCheck::RESULT_MATCH, SampleCheck::RESULT_MISMATCH])],
            'physical_qty' => ['nullable','numeric','min:0'],
        ]);
        $check = $service->confirm($request->user(), $sampleCycle, $data['token'], $data['result'], $data['physical_qty'] ?? null);

        return response()->json([
            'ok' => true,
            'id' => $check->id,
            'item_code' => $check->item_code,
            'item_name' => $check->item_name,
            'barcode' => $check->barcode,
            'location' => $check->location,
            'system_qty' => number_format((float) $check->system_qty, 4, '.', ''),
            'physical_qty' => number_format((float) $check->physical_qty, 4, '.', ''),
            'result' => $check->result,
            'scanned_at' => $check->scanned_at?->format('d/m/Y H:i:s'),
            'count' => $sampleCycle->checks()->count(),
        ]);
    }

    public function updateLocation(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertChecker($request, $sampleCycle);
        $data = $request->validate(['location' => ['required','string','max:255']]);
        abort_unless($sampleCycle->isOpen(), 422, 'Sample cycle sudah ditutup.');
        $sampleCycle->update(['location' => trim($data['location'])]);
        return back()->with('success', 'Lokasi / Rak berhasil diganti. Scan berikutnya memakai lokasi baru.');
    }

    public function close(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertChecker($request, $sampleCycle);
        if ($sampleCycle->isOpen()) {
            $sampleCycle->update(['status'=>SampleCycle::STATUS_CLOSED, 'closed_at'=>now()]);
        }
        return redirect()->route('gerai.checker.home')->with('success', 'Sample cycle ditutup. Hasil sampling sudah tersimpan.');
    }

    /**
     * @return array<int,array{source_database:string,warehouse_id:int,warehouse_code:string,warehouse_name:string}>
     */
    private function adminAssignments(User $user, ErpCatalogService $erp): array
    {
        if ($user->isAdmin()) {
            $rows = [];
            foreach ([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI] as $source) {
                foreach ($erp->warehouses($source) as $warehouse) {
                    $rows[] = [
                        'source_database' => $source,
                        'warehouse_id' => (int) $warehouse['warehouse_id'],
                        'warehouse_code' => (string) $warehouse['warehouse_code'],
                        'warehouse_name' => (string) $warehouse['warehouse_name'],
                    ];
                }
            }
            return $rows;
        }

        return $user->warehouseAssignments()->get()->map(fn (UserWarehouseAssignment $assignment): array => [
            'source_database' => $assignment->source_database,
            'warehouse_id' => (int) $assignment->erp_warehouse_id,
            'warehouse_code' => $assignment->warehouse_code,
            'warehouse_name' => $assignment->warehouse_name,
        ])->all();
    }

    /** @return array{source_database:string,warehouse_id:int,warehouse_code:string,warehouse_name:string} */
    private function resolveAdminWarehouse(User $user, string $key, ErpCatalogService $erp): array
    {
        [$source, $warehouseId] = array_pad(explode('|', trim($key), 2), 2, null);
        $source = strtoupper(trim((string) $source));
        $warehouseId = (int) $warehouseId;
        if (! in_array($source, [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI], true) || $warehouseId <= 0) {
            throw ValidationException::withMessages(['warehouse_key' => 'Gudang yang dipilih tidak valid.']);
        }

        if (! $user->isAdmin() && ! $user->hasWarehouseAssignment($source, $warehouseId)) {
            abort(403, 'Admin Gerai tidak memiliki assignment ke gudang tersebut.');
        }

        $warehouse = collect($erp->warehouses($source))->firstWhere('warehouse_id', $warehouseId);
        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_key' => 'Gudang tidak ditemukan atau sudah nonaktif di ERP.']);
        }

        return [
            'source_database' => $source,
            'warehouse_id' => (int) $warehouse['warehouse_id'],
            'warehouse_code' => (string) $warehouse['warehouse_code'],
            'warehouse_name' => (string) $warehouse['warehouse_name'],
        ];
    }

    private function assertChecker(Request $request, SampleCycle $cycle): void
    {
        abort_unless($cycle->cycle_type === SampleCycle::TYPE_GERAI, 404);
        abort_unless((int) $cycle->assigned_checker_id === (int) $request->user()->id, 403);
        abort_unless(
            $request->user()->hasWarehouseAssignment((string) $cycle->source_database, (int) $cycle->erp_warehouse_id),
            403,
            'User tidak lagi memiliki assignment ke gudang pada cycle ini.'
        );
    }
}
