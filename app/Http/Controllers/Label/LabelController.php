<?php

namespace App\Http\Controllers\Label;

use App\Http\Controllers\Controller;
use App\Http\Requests\Label\GenerateLabelRequest;
use App\Label\Repositories\SqlServerItemRepository;
use App\Label\Support\QrPayload;
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

        $qty = (int) $data['qty'];
        $qrValue = QrPayload::make($item->code, $qty);
        $labelSize = $data['label_size'];
        $printedAt = now();
        $user = $request->user();
        $printedBy = $user?->name ?: $user?->username ?: '-';

        return view('label.print', compact('item', 'qty', 'qrValue', 'labelSize', 'printedAt', 'printedBy'));
    }
}
