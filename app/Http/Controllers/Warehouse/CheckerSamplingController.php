<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\SampleCycleItem;
use App\Models\SampleCycleItemStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
                'warehouses',
                'items',
                'items as filled_items_count' => fn ($q) => $q->whereNotNull('physical_qty'),
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
        $sampleCycle->load([
            'warehouses:id,sample_cycle_id,source_database,warehouse_code,warehouse_name',
        ])->loadCount([
            'items',
            'items as filled_items_count' => fn ($q) => $q->whereNotNull('physical_qty'),
            'items as checked_items_count' => fn ($q) => $q->whereNotNull('checked_at'),
        ]);

        $items = $sampleCycle->items()
            ->withCount('stocks')
            ->orderBy('line_no')
            ->get();

        return view('warehouse_sampling.checker.show', [
            'period' => $sampleCycle,
            'items' => $items,
        ]);
    }

    /** Save one item as editable DRAFT from the modal. */
    public function check(Request $request, SampleCycle $sampleCycle, SampleCycleItem $sampleCycleItem): RedirectResponse
    {
        $this->assertAssigned($request, $sampleCycle);
        abort_unless((int) $sampleCycleItem->sample_cycle_id === (int) $sampleCycle->id, 404);
        abort_unless($sampleCycle->isOpen(), 422, 'Periode sampling sudah ditutup.');
        abort_if($this->isFinalized($sampleCycle), 422, 'Hasil checker sudah difinalisasi dan tidak dapat diedit lagi.');

        $data = $request->validate([
            'physical_qty' => ['required','numeric','min:0','max:99999999999999999999'],
            'checker_comment' => ['nullable','string','max:1000'],
        ]);

        DB::transaction(function () use ($request, $sampleCycle, $sampleCycleItem, $data): void {
            $cycle = SampleCycle::query()->lockForUpdate()->findOrFail($sampleCycle->id);
            abort_unless($cycle->isOpen(), 422, 'Periode sampling sudah ditutup.');
            abort_if($this->isFinalized($cycle), 422, 'Hasil checker sudah difinalisasi dan tidak dapat diedit lagi.');

            $item = SampleCycleItem::query()
                ->where('sample_cycle_id', $cycle->id)
                ->lockForUpdate()
                ->findOrFail($sampleCycleItem->id);

            $item->update([
                'physical_qty' => round((float) $data['physical_qty'], 4),
                'checker_comment' => trim((string) ($data['checker_comment'] ?? '')) ?: null,
                'result' => null,
                'checked_by' => $request->user()->id,
                'checked_at' => null,
                // Any new draft invalidates previous Admin validation/allocation.
                'validated_by' => null,
                'validated_at' => null,
                'validation_note' => null,
            ]);

            SampleCycleItemStock::query()
                ->where('sample_cycle_item_id', $item->id)
                ->update([
                    'allocated_physical_qty' => null,
                    'result' => null,
                    'updated_at' => now(),
                ]);
        });

        return back()->with('success', 'Item #'.$sampleCycleItem->line_no.' '.$sampleCycleItem->item_code.' disimpan sebagai DRAFT. Nilai masih dapat diedit sebelum finalisasi.');
    }

    /** Lock all item drafts after every item has a physical quantity. */
    public function finalize(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertAssigned($request, $sampleCycle);
        abort_unless($sampleCycle->isOpen(), 422, 'Periode sampling sudah ditutup.');
        abort_if($this->isFinalized($sampleCycle), 422, 'Hasil checker sudah difinalisasi.');

        $missing = $sampleCycle->items()
            ->whereNull('physical_qty')
            ->orderBy('line_no')
            ->limit(6)
            ->get(['line_no','item_code']);

        if ($missing->isNotEmpty()) {
            $preview = $missing->map(fn ($item) => '#'.$item->line_no.' '.$item->item_code)->implode(', ');
            throw ValidationException::withMessages([
                'physical_qty' => 'Finalisasi belum dapat dilakukan. Masih ada item tanpa Qty Fisik: '.$preview.'.',
            ]);
        }

        DB::transaction(function () use ($request, $sampleCycle): void {
            $cycle = SampleCycle::query()->lockForUpdate()->findOrFail($sampleCycle->id);
            abort_unless($cycle->isOpen(), 422, 'Periode sampling sudah ditutup.');
            abort_if($this->isFinalized($cycle), 422, 'Hasil checker sudah difinalisasi.');

            $items = SampleCycleItem::query()
                ->where('sample_cycle_id', $cycle->id)
                ->orderBy('line_no')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty() || $items->contains(fn ($item) => $item->physical_qty === null)) {
                throw ValidationException::withMessages(['physical_qty' => 'Semua item wajib memiliki Qty Fisik sebelum finalisasi.']);
            }

            $checkedAt = now();
            foreach ($items as $item) {
                $physical = round((float) $item->physical_qty, 4);
                $system = round((float) $item->system_qty, 4);
                $item->update([
                    'result' => abs($physical - $system) < 0.0001 ? SampleCheck::RESULT_MATCH : SampleCheck::RESULT_MISMATCH,
                    'checked_by' => $item->checked_by ?: $request->user()->id,
                    'checked_at' => $checkedAt,
                ]);
            }
        });

        return back()->with('success', 'Semua draft Qty Fisik sudah DIFINALISASI. Data checker sekarang terkunci dan tidak dapat diedit lagi.');
    }

    /** Old batch endpoint intentionally disabled after switching to modal per item. */
    public function saveBatch(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertAssigned($request, $sampleCycle);
        throw ValidationException::withMessages([
            'physical_qty' => 'Batch save sudah dinonaktifkan. Klik kode barang, isi modal, lalu Simpan Draft per item.',
        ]);
    }

    private function isFinalized(SampleCycle $cycle): bool
    {
        $total = $cycle->items()->count();
        return $total > 0 && ! $cycle->items()->whereNull('checked_at')->exists();
    }

    private function assertAssigned(Request $request, SampleCycle $cycle): void
    {
        abort_unless($cycle->isWarehouseSampling(), 404);
        abort_unless((int) $cycle->assigned_checker_id === (int) $request->user()->id, 403);
    }
}
