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
            // Exact match first, then numeric-equivalent fallback (03176 <-> 3176).
            $available = ItemBarcode::forItemCode($item->code);
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
        // Encode quantity of the selected UOM, not base units.
        // WarehouseCheck resolves full barcode first, then parses the final -qty.
        $qrValue = QrPayload::make($barcode ?: $item->code, $qty);
        // Exact barcode has priority in WarehouseCheck. Avoid printing a generated
        // BARCODE-QTY that already belongs to a different master entry.
        try {
            if (ItemBarcode::query()->where('barcode', $qrValue)->exists()) {
                throw ValidationException::withMessages([
                    'qty' => 'QR '.$qrValue.' sudah terdaftar sebagai barcode master. Ubah qty atau barcode untuk menghindari salah scan.',
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['qty' => 'Gagal memeriksa benturan nilai QR pada master barcode.']);
        }
        $labelSize = $data['label_size'];
        $printedAt = now();
        $user = $request->user();
        $printedBy = $user?->name ?: $user?->username ?: '-';

        return view('label.print', compact('item', 'qty', 'barcode', 'uom', 'qrValue', 'labelSize', 'printedAt', 'printedBy'));
    }
}
