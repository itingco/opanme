<?php

namespace App\Http\Controllers\Admin;

use App\Models\SampleCycle;
use App\Models\SampleCycleItem;
use App\Services\WarehouseSamplingPendingTransferService;
use App\Services\WarehouseSamplingSalesInvoiceService;
use App\Services\WarehouseSamplingValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseSamplingEnhancedController extends WarehouseSamplingController
{
    public function show(
        Request $request,
        SampleCycle $sampleCycle,
        WarehouseSamplingSalesInvoiceService $salesInvoices,
        WarehouseSamplingPendingTransferService $pendingTransfers
    ): View {
        $original = parent::show($request, $sampleCycle, $salesInvoices, $pendingTransfers);
        return view('warehouse_sampling.admin.show-enhanced', $original->getData());
    }

    public function validateItem(
        Request $request,
        SampleCycle $sampleCycle,
        SampleCycleItem $sampleCycleItem,
        WarehouseSamplingSalesInvoiceService $salesInvoices,
        WarehouseSamplingPendingTransferService $pendingTransfers
    ): RedirectResponse {
        $this->guardCycle($request, $sampleCycle);
        abort_unless((int)$sampleCycleItem->sample_cycle_id === (int)$sampleCycle->id, 404);
        $data = $request->validate($this->validationRules());
        DB::transaction(fn () => app(WarehouseSamplingValidationService::class)->apply($sampleCycleItem, $data, $request->user()->id));
        return back()->with('success', 'Item '.$sampleCycleItem->item_code.' tervalidasi. Qty Fisik Checker dipertahankan; Qty Final Admin digunakan jika override diisi.');
    }

    public function validateBulk(Request $request, SampleCycle $sampleCycle): RedirectResponse
    {
        $this->guardCycle($request, $sampleCycle);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.allocation_qty' => ['nullable', 'array'],
            'items.*.allocation_qty.*' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'items.*.admin_override_qty' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'items.*.validation_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $ids = array_keys($data['items']);
        if (count($ids) !== count(array_filter($ids, fn ($id) => ctype_digit((string)$id) && (int)$id > 0))) {
            return back()->withErrors(['items' => 'ID item tidak valid.']);
        }
        sort($ids, SORT_NUMERIC);
        $service = app(WarehouseSamplingValidationService::class);
        DB::transaction(function () use ($ids, $data, $sampleCycle, $request, $service): void {
            foreach ($ids as $id) {
                $item = SampleCycleItem::query()->where('sample_cycle_id', $sampleCycle->id)->findOrFail((int)$id);
                $service->apply($item, $data['items'][$id], $request->user()->id);
            }
        });
        return back()->with('success', count($ids).' item berhasil divalidasi sekaligus.');
    }

    private function validationRules(): array
    {
        return [
            'validation_note' => ['nullable', 'string', 'max:2000'],
            'allocation_qty' => ['nullable', 'array'],
            'allocation_qty.*' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'admin_override_qty' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    private function guardCycle(Request $request, SampleCycle $cycle): void
    {
        abort_unless($cycle->isWarehouseSampling(), 404);
        abort_if($cycle->isClosed() || $cycle->isDraft(), 422, 'Periode belum siap atau sudah ditutup.');
        if ($request->user()->isAdmin()) return;
        abort_unless($request->user()->isAdminGudang() && (int)$cycle->created_by === (int)$request->user()->id, 403);
    }
}
