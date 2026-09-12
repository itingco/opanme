<?php

namespace App\Http\Controllers\Label;

use App\Http\Controllers\Controller;
use App\Label\Repositories\SqlServerItemRepository;
use App\Http\Requests\Label\GenerateLabelRequest;
use App\Label\Support\QrPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Throwable;

class LabelController extends Controller
{
    public function __construct(private readonly SqlServerItemRepository $items)
    {
    }

    public function index(): View
    {
        return view('label.index');
    }

    public function preview(GenerateLabelRequest $request): View
    {
        $validated = $request->validated();

        try {
            $item = $this->items->findByCode($validated['item_code']);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'item_code' => 'Koneksi ke database barang gagal. Periksa koneksi SQL Server dan coba kembali.',
            ]);
        }

        if ($item === null) {
            throw ValidationException::withMessages([
                'item_code' => 'Kode barang tidak ditemukan pada IC_Items.',
            ]);
        }

        $qty = (int) $validated['qty'];
        $qrValue = QrPayload::make($item->code, $qty);

        $printedAt = now();
        $user = $request->user();
        $printedBy = $user?->name ?: $user?->username ?: '-';

        return view('label.print', compact('item', 'qty', 'qrValue', 'printedAt', 'printedBy'));
    }
}
