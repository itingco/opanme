<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\SampleCycleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckerSamplingController extends Controller
{
    public function index(Request $request): View
    {
        $periods = SampleCycle::query()
            ->where('cycle_type', SampleCycle::TYPE_WAREHOUSE)
            ->where('assigned_checker_id', $request->user()->id)
            ->whereIn('status', [SampleCycle::STATUS_OPEN, SampleCycle::STATUS_CLOSED])
            ->withCount([
                'items',
                'items as checked_items_count' => fn ($q) => $q->whereNotNull('checked_at'),
            ])
            ->orderByRaw("CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(20);

        return view('warehouse_sampling.checker.index', compact('periods'));
    }

    public function show(Request $request, SampleCycle $sampleCycle): View
    {
        $this->assertAssigned($request, $sampleCycle);
        $sampleCycle->loadCount([
            'items',
            'items as checked_items_count' => fn ($q) => $q->whereNotNull('checked_at'),
        ]);

        $items = $sampleCycle->items()
            ->orderByRaw('CASE WHEN checked_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('line_no')
            ->paginate(100);

        return view('warehouse_sampling.checker.show', [
            'period' => $sampleCycle,
            'items' => $items,
        ]);
    }

    public function check(
        Request $request,
        SampleCycle $sampleCycle,
        SampleCycleItem $sampleCycleItem
    ): RedirectResponse {
        $this->assertAssigned($request, $sampleCycle);
        abort_unless($sampleCycle->isOpen(), 422, 'Periode sampling sudah ditutup.');
        abort_unless((int) $sampleCycleItem->sample_cycle_id === (int) $sampleCycle->id, 404);

        $data = $request->validate([
            'physical_qty' => ['required', 'numeric', 'min:0', 'max:99999999999999999999'],
        ]);

        DB::transaction(function () use ($request, $sampleCycleItem, $data) {
            $item = SampleCycleItem::query()->lockForUpdate()->findOrFail($sampleCycleItem->id);
            $physical = round((float) $data['physical_qty'], 4);
            $system = round((float) $item->system_qty, 4);
            $result = abs($physical - $system) < 0.0001
                ? SampleCheck::RESULT_MATCH
                : SampleCheck::RESULT_MISMATCH;

            $item->update([
                'physical_qty' => $physical,
                'result' => $result,
                'checked_by' => $request->user()->id,
                'checked_at' => now(),
            ]);
        });

        return back()->with('success', 'Qty fisik baris #'.$sampleCycleItem->line_no.' berhasil disimpan.');
    }

    private function assertAssigned(Request $request, SampleCycle $cycle): void
    {
        abort_unless($cycle->isWarehouseSampling(), 404);
        abort_unless((int) $cycle->assigned_checker_id === (int) $request->user()->id, 403);
    }
}
