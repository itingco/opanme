<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\SampleCheck;
use App\Models\SampleCycle;
use App\Models\SampleCycleItem;
use App\Models\SampleCycleItemStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        // Semua item dimuat dalam satu form supaya checker dapat menyimpan seluruh Qty sekaligus.
        $items = $sampleCycle->items()
            ->withCount('stocks')
            ->orderBy('line_no')
            ->get();

        return view('warehouse_sampling.checker.show', [
            'period' => $sampleCycle,
            'items' => $items,
        ]);
    }

    /**
     * Simpan seluruh Qty Fisik Total dalam satu kali submit.
     * mode=draft : boleh diedit kembali.
     * mode=final : seluruh item wajib terisi dan sesudahnya dikunci.
     */
    public function saveBatch(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->assertAssigned($request, $sampleCycle);
        abort_unless($sampleCycle->isOpen(), 422, 'Periode sampling sudah ditutup.');
        abort_if($this->isFinalized($sampleCycle), 422, 'Hasil checker sudah difinalisasi dan tidak dapat diedit lagi.');

        $mode = strtolower(trim((string) $request->input('mode', 'draft')));
        if (! in_array($mode, ['draft', 'final'], true)) {
            $mode = 'draft';
        }

        $data = $request->validate([
            'physical_qty' => ['present', 'array'],
            'physical_qty.*' => ['nullable', 'numeric', 'min:0', 'max:99999999999999999999'],
            'checker_comment' => ['nullable', 'array'],
            'checker_comment.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $items = $sampleCycle->items()->orderBy('line_no')->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['physical_qty' => 'Periode ini belum memiliki item sampling.']);
        }

        $values = $this->normalizeQuantities($items, $data['physical_qty'] ?? [], $mode === 'final');
        $comments = $this->normalizeComments($items, $data['checker_comment'] ?? []);
        $filled = collect($values)->filter(fn ($value) => $value !== null)->count();

        DB::transaction(function () use ($request, $sampleCycle, $items, $values, $comments, $mode): void {
            $lockedCycle = SampleCycle::query()->lockForUpdate()->findOrFail($sampleCycle->id);
            abort_unless($lockedCycle->isOpen(), 422, 'Periode sampling sudah ditutup.');
            abort_if($this->isFinalized($lockedCycle), 422, 'Hasil checker sudah difinalisasi dan tidak dapat diedit lagi.');

            $lockedItems = SampleCycleItem::query()
                ->where('sample_cycle_id', $lockedCycle->id)
                ->orderBy('line_no')
                ->lockForUpdate()
                ->get();

            $checkedAt = $mode === 'final' ? now() : null;
            $itemIds = [];

            foreach ($lockedItems as $item) {
                $physical = $values[(int) $item->id] ?? null;
                $system = round((float) $item->system_qty, 4);

                $item->update([
                    'physical_qty' => $physical,
                    'checker_comment' => $comments[(int) $item->id] ?? null,
                    // Draft belum dianggap selesai. Result/check timestamp baru resmi saat finalisasi.
                    'result' => $mode === 'final'
                        ? (abs((float) $physical - $system) < 0.0001
                            ? SampleCheck::RESULT_MATCH
                            : SampleCheck::RESULT_MISMATCH)
                        : null,
                    'checked_by' => $physical !== null ? $request->user()->id : null,
                    'checked_at' => $checkedAt,
                    // Perubahan checker selalu membatalkan alokasi/validasi admin sebelumnya.
                    'validated_by' => null,
                    'validated_at' => null,
                    'validation_note' => null,
                ]);

                $itemIds[] = (int) $item->id;
            }

            if ($itemIds !== []) {
                SampleCycleItemStock::query()
                    ->whereIn('sample_cycle_item_id', $itemIds)
                    ->update([
                        'allocated_physical_qty' => null,
                        'result' => null,
                        'updated_at' => now(),
                    ]);
            }
        });

        if ($mode === 'final') {
            return back()->with('success', $items->count().' Qty Fisik Total berhasil disimpan dan DIFINALISASI. Data checker sekarang terkunci dan tidak dapat diedit lagi.');
        }

        return back()->with('success', $filled.' dari '.$items->count().' Qty Fisik Total berhasil disimpan sebagai DRAFT. Anda masih dapat mengubah nilainya sebelum finalisasi.');
    }

    /**
     * Endpoint lama dipertahankan supaya route lama tidak rusak, tetapi proses per-item
     * sudah dinonaktifkan. Halaman checker sekarang wajib menggunakan batch save.
     */
    public function check(
        Request $request,
        SampleCycle $sampleCycle,
        SampleCycleItem $sampleCycleItem
    ): RedirectResponse {
        $this->assertAssigned($request, $sampleCycle);
        abort_unless((int) $sampleCycleItem->sample_cycle_id === (int) $sampleCycle->id, 404);

        throw ValidationException::withMessages([
            'physical_qty' => 'Penyimpanan per item sudah dinonaktifkan. Gunakan tombol Simpan Draft Semua atau Simpan Semua & Finalisasi.',
        ]);
    }

    /** @return array<int, float|null> */
    private function normalizeQuantities(Collection $items, array $input, bool $requireAll): array
    {
        $values = [];
        $missing = [];

        foreach ($items as $item) {
            $raw = $input[(string) $item->id] ?? $input[$item->id] ?? null;

            if ($raw === '' || $raw === null) {
                $values[(int) $item->id] = null;
                if ($requireAll) {
                    $missing[] = '#'.$item->line_no.' '.$item->item_code;
                }
                continue;
            }

            if (! is_numeric($raw) || (float) $raw < 0) {
                throw ValidationException::withMessages([
                    'physical_qty' => 'Qty fisik pada item #'.$item->line_no.' '.$item->item_code.' tidak valid.',
                ]);
            }

            $values[(int) $item->id] = round((float) $raw, 4);
        }

        if ($requireAll && $missing !== []) {
            $preview = implode(', ', array_slice($missing, 0, 5));
            if (count($missing) > 5) {
                $preview .= ' dan '.(count($missing) - 5).' item lainnya';
            }

            throw ValidationException::withMessages([
                'physical_qty' => 'Finalisasi belum dapat dilakukan. Isi Qty Fisik Total untuk seluruh item. Belum terisi: '.$preview.'.',
            ]);
        }

        return $values;
    }


    /** @return array<int, string|null> */
    private function normalizeComments(Collection $items, array $input): array
    {
        $values = [];

        foreach ($items as $item) {
            $raw = $input[(string) $item->id] ?? $input[$item->id] ?? null;
            if ($raw === null) {
                $values[(int) $item->id] = null;
                continue;
            }

            $comment = trim((string) $raw);
            $values[(int) $item->id] = $comment === '' ? null : $comment;
        }

        return $values;
    }

    private function isFinalized(SampleCycle $cycle): bool
    {
        $total = $cycle->items()->count();
        if ($total === 0) {
            return false;
        }

        return ! $cycle->items()->whereNull('checked_at')->exists();
    }

    private function assertAssigned(Request $request, SampleCycle $cycle): void
    {
        abort_unless($cycle->isWarehouseSampling(), 404);
        abort_unless((int) $cycle->assigned_checker_id === (int) $request->user()->id, 403);
    }
}
