<?php

namespace App\Http\Controllers\Label;

use App\Http\Controllers\Controller;
use App\Http\Requests\Label\GenerateLabelRequest;
use App\Label\Repositories\SqlServerItemRepository;
use App\Label\Support\QrPayload;
use App\Models\ItemBarcode;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Throwable;

class LabelController extends Controller
{
    public function __construct(private readonly SqlServerItemRepository $items) {}

    public function index(): View { return view('label.index'); }

    public function preview(GenerateLabelRequest $request): View
    {
        $data = $request->validated();
        try {
            $item = $this->items->findByCode($data['item_code']);
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['item_code' => 'Gagal membaca database barang.']);
        }
        if ($item === null) {
            throw ValidationException::withMessages(['item_code' => 'Kode barang tidak ditemukan di IC_Items.']);
        }

        try {
            $available = ItemBarcode::query()->where('item_code', $item->code)->get();
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['barcode_id' => 'Gagal membaca master barcode DB_AppHub.']);
        }

        $selected = null;
        if ($available->isNotEmpty()) {
            if (! isset($data['barcode_id'])) {
                throw ValidationException::withMessages(['barcode_id' => 'Pilih satuan/barcode sebelum membuat label.']);
            }
            $selected = $available->first(static fn ($row): bool => (int) $row->id === (int) $data['barcode_id']);
            if (! $selected) {
                throw ValidationException::withMessages(['barcode_id' => 'Barcode tidak sesuai dengan barang yang dipilih. Pilih ulang satuan.']);
            }
        } elseif (isset($data['barcode_id'])) {
            throw ValidationException::withMessages(['barcode_id' => 'Barang ini tidak memiliki barcode master. Muat ulang form.']);
        }

        $qty = (int) $data['qty'];
        $barcode = $selected ? trim((string) $selected->barcode) : null;
        $uom = $selected ? trim((string) $selected->uom_code) : 'PCS';
        // Barcode master harus dicetak apa adanya: jangan diberi suffix qty.
        $qrValue = $barcode ?: QrPayload::make($item->code, $qty);
        $labelSize = $data['label_size'];
        $printedAt = now();
        $user = $request->user();
        $printedBy = $user?->name ?: $user?->username ?: '-';

        return view('label.print', compact('item', 'qty', 'barcode', 'uom', 'qrValue', 'labelSize', 'printedAt', 'printedBy'));
    }
}
