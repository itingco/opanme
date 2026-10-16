<?php

namespace App\Services;

use App\Models\SampleCheck;
use App\Models\SampleCycleItem;
use App\Models\SampleCycleItemStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Validasi satu item; dapat dipakai baik oleh tombol tunggal maupun bulk. */
class WarehouseSamplingValidationService
{
    public function __construct(
        private readonly WarehouseSamplingSalesInvoiceService $salesInvoices,
        private readonly WarehouseSamplingPendingTransferService $pendingTransfers
    ) {}

    /**
     * @param array<string, mixed> $data
     * Called in an outer transaction for bulk operations; standalone uses the same lock.
     */
    public function apply(SampleCycleItem $item, array $data, int $adminId): void
    {
        $locked = SampleCycleItem::query()->lockForUpdate()->findOrFail($item->id);
        $locked->loadMissing('cycle');
        if (! $locked->cycle->isWarehouseSampling() || $locked->cycle->isClosed()) {
            throw ValidationException::withMessages(['items' => 'Periode bukan Sampling Gudang aktif.']);
        }
        if ($locked->checked_at === null || $locked->physical_qty === null) {
            throw ValidationException::withMessages(['items' => 'Item '.$locked->item_code.' belum difinalisasi Checker.']);
        }
        if ($locked->validated_at !== null) {
            throw ValidationException::withMessages(['items' => 'Item '.$locked->item_code.' telah divalidasi oleh Admin lain. Muat ulang halaman.']);
        }

        $physicalRaw = (float) $locked->physical_qty;
        $rawOverride = $data['admin_override_qty'] ?? null;
        $hasOverride = $rawOverride !== null && $rawOverride !== '';
        $effective = $hasOverride ? (int) $rawOverride : (int) round($physicalRaw);
        if (! $hasOverride && abs($physicalRaw - $effective) > 0.0001) {
            throw ValidationException::withMessages(['admin_override_qty' => 'Qty checker desimal. Isi Qty Override Admin dengan bilangan bulat.']);
        }
        if ($effective < 0 || $effective > 999999999999) {
            throw ValidationException::withMessages(['admin_override_qty' => 'Qty final di luar rentang yang diizinkan.']);
        }

        $stocks = SampleCycleItemStock::query()
            ->where('sample_cycle_item_id', $locked->id)
            ->with('warehouse')->orderBy('id')->lockForUpdate()->get();
        $locked->setRelation('stocks', $stocks);
        $sales = $this->salesInvoices->forItems(collect([$locked]));
        $pending = $this->pendingTransfers->forItems(collect([$locked]), $sales);

        $systems = [];
        foreach ($stocks as $stock) {
            $sale = $sales[(int)$stock->id] ?? [
                'sales_date' => $locked->checked_at?->format('Y-m-d'),
                'sales_qty' => 0, 'adjusted_system_qty' => round((float)$stock->system_qty, 4), 'invoices' => [],
            ];
            $afterSale = round((float)$sale['adjusted_system_qty'], 4);
            $transfer = $pending[(int)$stock->id] ?? [
                'pending_transfer_qty' => 0, 'validation_system_qty' => $afterSale, 'transfers' => [],
            ];
            $system = round((float)($transfer['validation_system_qty'] ?? $afterSale), 4);
            $systems[(int)$stock->id] = $system;
            $stock->sales_invoice_date = $sale['sales_date'];
            $stock->sales_invoice_qty = round((float)$sale['sales_qty'], 4);
            $stock->adjusted_system_qty = $afterSale;
            $stock->sales_invoice_details = $sale['invoices'];
            $stock->pending_transfer_qty = round((float)($transfer['pending_transfer_qty'] ?? 0), 4);
            $stock->pending_transfer_details = $transfer['transfers'] ?? [];
            $stock->validation_system_qty = $system;
        }

        $allocatable = $stocks->filter(fn ($s) => $s->item_id !== null)->values();
        if ($allocatable->isEmpty()) {
            throw ValidationException::withMessages(['allocation_qty' => 'Item tidak memiliki gudang tujuan alokasi yang valid.']);
        }
        $submitted = $data['allocation_qty'] ?? [];
        $allocations = [];
        if (is_array($submitted) && count($submitted) > 0) {
            foreach ($allocatable as $stock) {
                $id = (int)$stock->id;
                if (! array_key_exists($id, $submitted) && ! array_key_exists((string)$id, $submitted)) {
                    throw ValidationException::withMessages(['allocation_qty' => 'Alokasi gudang item '.$locked->item_code.' tidak lengkap.']);
                }
                $allocations[$id] = (int)($submitted[$id] ?? $submitted[(string)$id]);
                if ($allocations[$id] < 0) {
                    throw ValidationException::withMessages(['allocation_qty' => 'Alokasi tidak boleh negatif.']);
                }
            }
        } else {
            // Largest remainder; all allocation totals are whole numbers.
            $weighted = $allocatable->filter(fn ($s) => ($systems[(int)$s->id] ?? 0) > 0)->values();
            $sum = (float)$weighted->sum(fn ($s) => $systems[(int)$s->id]);
            if ($weighted->isEmpty()) {
                $allocations[(int)$allocatable->first()->id] = $effective;
            } else {
                $fractions = [];
                $used = 0;
                foreach ($weighted as $stock) {
                    $id = (int)$stock->id;
                    $exact = $effective * ($systems[$id] / $sum);
                    $base = (int)floor($exact);
                    $allocations[$id] = $base;
                    $used += $base;
                    $fractions[] = ['id' => $id, 'fraction' => $exact - $base];
                }
                usort($fractions, static fn ($a, $b) => ($b['fraction'] <=> $a['fraction']) ?: ($a['id'] <=> $b['id']));
                for ($n = $effective - $used, $i = 0; $i < $n; $i++) {
                    $allocations[$fractions[$i % count($fractions)]['id']]++;
                }
            }
        }

        if (array_sum($allocations) !== $effective) {
            throw ValidationException::withMessages([
                'allocation_qty' => 'Total alokasi '.array_sum($allocations).' tidak sama dengan Qty Final Admin '.$effective.'. Qty Fisik Checker tidak wajib sama dengan Qty Final Admin.',
            ]);
        }
        foreach ($stocks as $stock) {
            $allocated = $allocations[(int)$stock->id] ?? 0;
            $system = $systems[(int)$stock->id];
            $stock->allocated_physical_qty = $allocated;
            $stock->result = abs($allocated - $system) < 0.0001
                ? SampleCheck::RESULT_MATCH : SampleCheck::RESULT_MISMATCH;
            $stock->save();
        }
        $locked->validated_by = $adminId;
        $locked->validated_at = now();
        $locked->validation_note = trim((string)($data['validation_note'] ?? '')) ?: null;
        $locked->admin_override_qty = $hasOverride ? $effective : null;
        $locked->save();
    }
}
