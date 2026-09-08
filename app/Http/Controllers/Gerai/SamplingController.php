<?php

namespace App\Http\Controllers\Gerai;

use App\Http\Controllers\Controller;
use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\StockOpnameCycle;
use App\Services\ErpCatalogService;
use App\Services\SampleCheckingService;
use App\Services\SampleCycleNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SamplingController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user();
        $cycles = SampleCycle::query()->where('created_by', $user->id)->withCount('checks')->latest('id')->paginate(20);

        return view('gerai.home', [
            'user' => $user,
            'cycles' => $cycles,
            'databases' => [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI],
        ]);
    }

    public function warehouses(Request $request, ErpCatalogService $erp): JsonResponse
    {
        $data = $request->validate(['source_database' => ['required', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])]]);
        return response()->json(['data' => $erp->warehouses($data['source_database'])]);
    }

    public function create(Request $request, ErpCatalogService $erp, SampleCycleNumberService $numbers): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'source_database' => ['nullable', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
            'erp_warehouse_id' => ['nullable', 'integer'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        if ($user->hasGeraiWarehouseBinding()) {
            $source = $user->source_database;
            $warehouse = [
                'warehouse_id' => $user->erp_warehouse_id,
                'warehouse_code' => $user->warehouse_code,
                'warehouse_name' => $user->warehouse_name,
            ];
        } else {
            $source = $data['source_database'] ?? null;
            $warehouseId = isset($data['erp_warehouse_id']) ? (int) $data['erp_warehouse_id'] : 0;
            if (! $source || ! $warehouseId) {
                return back()->withErrors(['erp_warehouse_id' => 'Pilih database ERP dan gudang sebelum mulai sampling.'])->withInput();
            }
            $warehouse = collect($erp->warehouses($source))->firstWhere('warehouse_id', $warehouseId);
            if (! $warehouse) {
                return back()->withErrors(['erp_warehouse_id' => 'Gudang tidak valid atau tidak aktif.'])->withInput();
            }
        }

        $cycle = DB::transaction(function () use ($request, $user, $data, $source, $warehouse, $numbers) {
            return SampleCycle::create([
                'cycle_no' => $numbers->next(now()),
                'created_by' => $user->id,
                'source_database' => $source,
                'erp_warehouse_id' => (int) $warehouse['warehouse_id'],
                'warehouse_code' => $warehouse['warehouse_code'],
                'warehouse_name' => $warehouse['warehouse_name'],
                'location' => trim($data['location']),
                'status' => SampleCycle::STATUS_OPEN,
                'started_at' => now(),
            ]);
        });

        return redirect()->route('gerai.sampling.scan', $cycle)->with('success', 'Sample cycle dibuat. Silakan mulai scan barang.');
    }

    public function scan(Request $request, SampleCycle $sampleCycle): View
    {
        $this->assertOwner($request, $sampleCycle);
        return view('gerai.scan', [
            'cycle' => $sampleCycle,
            'checks' => $sampleCycle->checks()->latest('scanned_at')->limit(25)->get(),
            'checkCount' => $sampleCycle->checks()->count(),
        ]);
    }

    public function lookup(Request $request, SampleCycle $sampleCycle, SampleCheckingService $service): JsonResponse
    {
        $this->assertOwner($request, $sampleCycle);
        $data = $request->validate(['barcode' => ['required','string','max:150']]);
        return response()->json($service->lookup($request->user(), $sampleCycle, $data['barcode']));
    }

    public function confirm(Request $request, SampleCycle $sampleCycle, SampleCheckingService $service): JsonResponse
    {
        $this->assertOwner($request, $sampleCycle);
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
        $this->assertOwner($request, $sampleCycle);
        $data = $request->validate(['location' => ['required','string','max:255']]);
        abort_unless($sampleCycle->isOpen(), 422, 'Sample cycle sudah ditutup.');
        $sampleCycle->update(['location' => trim($data['location'])]);
        return back()->with('success', 'Lokasi / Rak berhasil diganti. Scan berikutnya memakai lokasi baru.');
    }

    public function close(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertOwner($request, $sampleCycle);
        if ($sampleCycle->isOpen()) {
            $sampleCycle->update(['status'=>SampleCycle::STATUS_CLOSED, 'closed_at'=>now()]);
        }
        return redirect()->route('gerai.sampling.home')->with('success', 'Sample cycle ditutup. Hasil sampling sudah tersimpan.');
    }

    private function assertOwner(Request $request, SampleCycle $cycle): void
    {
        abort_unless((int) $cycle->created_by === (int) $request->user()->id, 403);
    }
}
