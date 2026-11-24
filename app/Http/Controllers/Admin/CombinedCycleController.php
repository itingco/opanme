<?php

namespace App\Http\Controllers\Admin;

use App\Models\StockOpnameCycle;
use App\Services\CombinedCycleSnapshotService;
use App\Services\CycleNumberService;
use App\Services\CycleSnapshotService;
use App\Services\CycleSummaryService;
use App\Services\ErpCatalogService;
use App\Services\ErpConnectionResolver;
use App\Services\NonSystemQtyService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Keeps the legacy controller and its scan/override/finalization workflow intact. */
class CombinedCycleController extends CycleController
{
    public function store(Request $request, CycleNumberService $numbers, ErpCatalogService $erp): RedirectResponse
    {
        if ($request->input('source_database') !== CombinedCycleSnapshotService::MODE) {
            return parent::store($request, $numbers, $erp);
        }
        $data = $request->validate([
            'cutoff_date' => ['required','date'],
            'combined_warehouses' => ['required','array','min:2','max:100'],
            'combined_warehouses.*' => ['required','string','distinct','regex:/^(AS_INGCO|AS_SMI):[1-9][0-9]*$/'],
        ]);
        $selected = [];
        $available = [];
        foreach ($data['combined_warehouses'] as $key) {
            [$db, $id] = explode(':', $key, 2);
            if (!isset($available[$db])) {
                try {
                    $available[$db] = collect($erp->warehouses($db))->keyBy('warehouse_id');
                } catch (\Throwable $e) {
                    report($e);
                    throw ValidationException::withMessages(['combined_warehouses'=>"Gagal membaca warehouse {$db}. Periksa koneksi ERP."]);
                }
            }
            $warehouse = $available[$db]->get((int)$id);
            if (!$warehouse) throw ValidationException::withMessages(['combined_warehouses'=>"Warehouse {$key} tidak valid/nonaktif."]);
            if ($request->user()->isAdminGerai() && !$request->user()->hasWarehouseAssignment($db,(int)$id)) abort(403);
            $selected[] = ['source_database'=>$db,'erp_warehouse_id'=>(int)$id,
                'warehouse_code'=>$warehouse['warehouse_code'],'warehouse_name'=>$warehouse['warehouse_name']];
        }
        $cycle = DB::transaction(function () use ($data,$selected,$numbers,$request) {
            $cycle = StockOpnameCycle::create([
                'cycle_no'=>$numbers->next(CarbonImmutable::today()),'source_database'=>CombinedCycleSnapshotService::MODE,
                'cutoff_date'=>$data['cutoff_date'],'status'=>StockOpnameCycle::STATUS_DRAFT,'created_by'=>$request->user()->id,
            ]);
            $cycle->warehouses()->create([
                'erp_warehouse_id'=>0,'warehouse_code'=>'COMBINED','warehouse_name'=>'Area Fisik Gabungan ('.count($selected).' gudang)', 'created_at'=>now(),
            ]);
            foreach ($selected as $source) DB::table('cycle_combined_sources')->insert(array_merge(['cycle_id'=>$cycle->id],$source));
            return $cycle;
        });
        return redirect()->route('admin.cycles.assignments.edit',$cycle)->with('success','Cycle gabungan dibuat. Assign checker ke satu area fisik COMBINED.');
    }

    public function start(StockOpnameCycle $cycle, CycleSnapshotService $snapshots): RedirectResponse
    {
        if ($cycle->source_database !== CombinedCycleSnapshotService::MODE) return parent::start($cycle,$snapshots);
        try {
            $count = DB::transaction(function () use ($cycle,$snapshots): int {
                $locked = StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
                if ($locked->status !== StockOpnameCycle::STATUS_DRAFT) throw ValidationException::withMessages(['cycle'=>'Cycle bukan DRAFT.']);
                if (!$locked->assignments()->exists()) throw ValidationException::withMessages(['cycle'=>'Assign minimal satu checker sebelum memulai.']);
                $at = now();
                $count = $snapshots->captureOpening($locked,$at);
                $locked->update(['status'=>StockOpnameCycle::STATUS_OPEN,'started_at'=>$at]);
                return $count;
            });
        } catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['cycle'=>'Gagal mengambil snapshot gabungan: '.$e->getMessage()]);
        }
        return back()->with('success',"Cycle gabungan dibuka. {$count} Item Code unik tersimpan.");
    }

    public function show(StockOpnameCycle $cycle): View
    {
        $view = parent::show($cycle);
        if ($cycle->source_database !== CombinedCycleSnapshotService::MODE) return $view;
        return view('admin.cycles.combined-show', $view->getData());
    }

    public function summary(Request $request, StockOpnameCycle $cycle, CycleSummaryService $summary): View
    {
        $view = parent::summary($request,$cycle,$summary);
        if ($cycle->source_database !== CombinedCycleSnapshotService::MODE) return $view;
        return view('admin.cycles.combined-summary', $view->getData());
    }

    public function finalize(Request $request, StockOpnameCycle $cycle): RedirectResponse
    {
        if ($cycle->source_database !== CombinedCycleSnapshotService::MODE) return parent::finalize($request,$cycle);
        return DB::transaction(function () use ($request,$cycle) {
            $locked = StockOpnameCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            if ($locked->status === StockOpnameCycle::STATUS_CLOSED) {
                $stale = app(\App\Services\CombinedManualAllocationService::class)->staleItems($locked,app(CycleSummaryService::class));
                if ($stale) throw ValidationException::withMessages(['finalize'=>'Alokasi manual tidak sesuai Final Fisik: '.implode(', ',$stale).'. Simpan ulang atau reset alokasi pada halaman Proporsi per Gudang.']);
            }
            return parent::finalize($request,$locked);
        });
    }

    public function createDiscoveredOverride(Request $request, StockOpnameCycle $cycle, NonSystemQtyService $qtyService, ErpCatalogService $erp): RedirectResponse
    {
        if ($cycle->source_database !== CombinedCycleSnapshotService::MODE) return parent::createDiscoveredOverride($request,$cycle,$qtyService,$erp);
        $databases = DB::table('cycle_combined_sources')->where('cycle_id',$cycle->id)->distinct()->pluck('source_database')->all();
        // Parent already handles validation, audit and locking. Only resolve the barcode across real databases.
        $catalog = new class(app(ErpConnectionResolver::class), $erp, $databases) extends ErpCatalogService {
            public function __construct(ErpConnectionResolver $resolver, private ErpCatalogService $delegate, private array $databases) { parent::__construct($resolver); }
            public function findBarcode(string $sourceDatabase, string $barcode): ?array {
                foreach ($this->databases as $db) {
                    $item = $this->delegate->findBarcode($db,$barcode);
                    if ($item) return $item;
                }
                return null;
            }
        };
        return parent::createDiscoveredOverride($request,$cycle,$qtyService,$catalog);
    }
}
