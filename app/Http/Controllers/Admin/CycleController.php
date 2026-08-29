<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScanSession;
use App\Models\ScanTransaction;
use App\Models\StockOpnameCycle;
use App\Models\StockOpnameOverride;
use App\Models\StockSnapshot;
use App\Services\CycleNumberService;
use App\Services\CycleSnapshotService;
use App\Services\CycleSummaryExcelService;
use App\Services\CycleSummaryService;
use App\Services\ErpCatalogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CycleController extends Controller
{
    public function index(): View
    {
        return view('admin.cycles.index', [
            'cycles' => StockOpnameCycle::withCount(['warehouses', 'assignments'])->latest()->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.cycles.create', [
            'databases' => [StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI],
        ]);
    }

    public function store(Request $request, CycleNumberService $numbers, ErpCatalogService $erp): RedirectResponse
    {
        $data = $request->validate([
            'source_database' => ['required', Rule::in([StockOpnameCycle::DB_INGCO, StockOpnameCycle::DB_SMI])],
            'cutoff_date' => ['required', 'date'],
            'warehouse_ids' => ['required', 'array', 'min:1'],
            'warehouse_ids.*' => ['integer'],
        ]);

        $available = collect($erp->warehouses($data['source_database']))->keyBy('warehouse_id');
        $selected = collect($data['warehouse_ids'])
            ->map(fn ($id) => $available->get((int) $id))
            ->filter()
            ->values();

        if ($selected->count() !== count($data['warehouse_ids'])) {
            return back()->withErrors(['warehouse_ids' => 'Ada warehouse yang tidak valid/nonaktif.'])->withInput();
        }

        $cycle = DB::transaction(function () use ($data, $selected, $numbers, $request) {
            $today = CarbonImmutable::today();
            $cycle = StockOpnameCycle::create([
                'cycle_no' => $numbers->next($today),
                'source_database' => $data['source_database'],
                'cutoff_date' => $data['cutoff_date'],
                'status' => StockOpnameCycle::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            foreach ($selected as $warehouse) {
                $cycle->warehouses()->create([
                    'erp_warehouse_id' => $warehouse['warehouse_id'],
                    'warehouse_code' => $warehouse['warehouse_code'],
                    'warehouse_name' => $warehouse['warehouse_name'],
                    'created_at' => now(),
                ]);
            }

            return $cycle;
        });

        return redirect()
            ->route('admin.cycles.assignments.edit', $cycle)
            ->with('success', 'Cycle dibuat. Assign checker ke warehouse.');
    }

    public function show(StockOpnameCycle $cycle): View
    {
        $cycle->load(['warehouses', 'assignments.checker']);
        $scanCount = ScanTransaction::where('cycle_id', $cycle->id)->count();
        $activeSessions = ScanSession::where('cycle_id', $cycle->id)
            ->whereNull('ended_at')
            ->with(['checker', 'warehouse'])
            ->get();

        return view('admin.cycles.show', compact('cycle', 'scanCount', 'activeSessions'));
    }

    public function start(StockOpnameCycle $cycle, CycleSnapshotService $snapshots): RedirectResponse
    {
        if ($cycle->status !== StockOpnameCycle::STATUS_DRAFT) {
            return back()->withErrors(['cycle' => 'Cycle bukan DRAFT.']);
        }
        if (! $cycle->assignments()->exists()) {
            return back()->withErrors(['cycle' => 'Assign minimal satu checker sebelum memulai.']);
        }

        $startedAt = now();
        try {
            $count = $snapshots->captureOpening($cycle, $startedAt);
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['cycle' => 'Gagal mengambil snapshot ERP: '.$e->getMessage()]);
        }

        $cycle->update([
            'status' => StockOpnameCycle::STATUS_OPEN,
            'started_at' => $startedAt,
        ]);

        return back()->with('success', "Cycle dibuka. {$count} baris snapshot stok tersimpan.");
    }

    public function close(StockOpnameCycle $cycle, CycleSnapshotService $snapshots): RedirectResponse
    {
        if ($cycle->status !== StockOpnameCycle::STATUS_OPEN) {
            return back()->withErrors(['cycle' => 'Cycle tidak sedang OPEN.']);
        }

        $completedAt = now();
        DB::transaction(function () use ($cycle, $completedAt) {
            $cycle->update([
                'status' => StockOpnameCycle::STATUS_CLOSED,
                'completed_at' => $completedAt,
            ]);
            ScanSession::where('cycle_id', $cycle->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $completedAt]);
        });

        try {
            $snapshots->captureClosing($cycle->fresh(), $completedAt);
            $cycle->update([
                'closing_snapshot_at' => $completedAt,
                'closing_snapshot_error' => null,
            ]);

            return redirect()
                ->route('admin.cycles.summary', $cycle)
                ->with('success', 'Cycle ditutup dan closing snapshot berhasil diambil.');
        } catch (Throwable $e) {
            report($e);
            $cycle->update(['closing_snapshot_error' => $e->getMessage()]);

            return redirect()
                ->route('admin.cycles.summary', $cycle)
                ->withErrors(['cycle' => 'Cycle sudah ditutup, tetapi closing snapshot gagal. Gunakan tombol Retry Closing Snapshot.']);
        }
    }

    public function retryClosing(StockOpnameCycle $cycle, CycleSnapshotService $snapshots): RedirectResponse
    {
        if ($cycle->status !== StockOpnameCycle::STATUS_CLOSED) {
            return back()->withErrors(['cycle' => 'Cycle belum CLOSED.']);
        }

        $at = $cycle->completed_at ?? now();
        try {
            $snapshots->captureClosing($cycle, $at);
            $cycle->update([
                'closing_snapshot_at' => now(),
                'closing_snapshot_error' => null,
            ]);

            return back()->with('success', 'Closing snapshot berhasil diperbarui.');
        } catch (Throwable $e) {
            report($e);
            $cycle->update(['closing_snapshot_error' => $e->getMessage()]);

            return back()->withErrors(['cycle' => 'Closing snapshot masih gagal: '.$e->getMessage()]);
        }
    }

    public function summary(Request $request, StockOpnameCycle $cycle, CycleSummaryService $summary): View
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $varianceOnly = $request->boolean('variance_only');
        $rows = $summary->query($cycle, $warehouseId, $varianceOnly)
            ->paginate(60)
            ->withQueryString();

        return view('admin.cycles.summary', [
            'cycle' => $cycle->load('warehouses'),
            'rows' => $rows,
            'warehouseId' => $warehouseId,
            'varianceOnly' => $varianceOnly,
        ]);
    }

    public function exportSummary(
        Request $request,
        StockOpnameCycle $cycle,
        CycleSummaryService $summary,
        CycleSummaryExcelService $excel
    ): BinaryFileResponse {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $varianceOnly = $request->boolean('variance_only');
        $warehouse = $warehouseId ? $cycle->warehouses()->findOrFail($warehouseId) : null;

        $filename = $cycle->cycle_no.'-summary-'.now()->format('Ymd-His').'.xlsx';
        $path = storage_path('app/exports/'.$filename);

        $excel->export(
            $path,
            $cycle,
            $summary->query($cycle, $warehouseId, $varianceOnly)->cursor(),
            $warehouse ? $warehouse->warehouse_code.' - '.$warehouse->warehouse_name : null,
            $varianceOnly
        );

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function saveOverride(
        Request $request,
        StockOpnameCycle $cycle,
        int $warehouseId,
        int $itemId
    ): RedirectResponse {
        if ($cycle->status !== StockOpnameCycle::STATUS_CLOSED) {
            return back()->withErrors([
                'override' => 'Override hanya dapat dilakukan setelah cycle CLOSED agar hasil scan tidak berubah lagi.',
            ]);
        }

        $warehouse = $cycle->warehouses()->findOrFail($warehouseId);
        $this->ensureSummaryItemExists($cycle, $warehouse->id, $itemId);

        $data = $request->validate([
            'override_qty' => ['required', 'numeric', 'min:0'],
            'comment' => ['required', 'string', 'max:2000'],
        ], [
            'comment.required' => 'Comment wajib diisi untuk setiap override.',
        ]);

        StockOpnameOverride::updateOrCreate(
            [
                'cycle_id' => $cycle->id,
                'warehouse_id' => $warehouse->id,
                'item_id' => $itemId,
            ],
            [
                'override_qty' => $data['override_qty'],
                'comment' => trim($data['comment']),
                'updated_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Override fisik berhasil disimpan. Hasil scan asli tidak diubah.');
    }

    public function deleteOverride(
        StockOpnameCycle $cycle,
        int $warehouseId,
        int $itemId
    ): RedirectResponse {
        if ($cycle->status !== StockOpnameCycle::STATUS_CLOSED) {
            return back()->withErrors([
                'override' => 'Override hanya dapat dihapus setelah cycle CLOSED.',
            ]);
        }

        $warehouse = $cycle->warehouses()->findOrFail($warehouseId);
        StockOpnameOverride::query()
            ->where('cycle_id', $cycle->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $itemId)
            ->delete();

        return back()->with('success', 'Override dihapus. Final fisik kembali memakai hasil scan asli.');
    }

    public function scanDetail(StockOpnameCycle $cycle, int $warehouseId, int $itemId): View
    {
        $warehouse = $cycle->warehouses()->findOrFail($warehouseId);
        $scans = ScanTransaction::query()
            ->where('cycle_id', $cycle->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $itemId)
            ->with('checker')
            ->orderBy('scanned_at')
            ->get();

        abort_if($scans->isEmpty(), 404);

        return view('admin.cycles.scan-detail', compact('cycle', 'warehouse', 'scans'));
    }

    private function ensureSummaryItemExists(StockOpnameCycle $cycle, int $warehouseId, int $itemId): void
    {
        $existsInSnapshot = StockSnapshot::query()
            ->where('cycle_id', $cycle->id)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->exists();

        $existsInScan = ScanTransaction::query()
            ->where('cycle_id', $cycle->id)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->exists();

        abort_unless($existsInSnapshot || $existsInScan, 404);
    }
}
