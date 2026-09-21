<?php

namespace App\Http\Controllers;

use App\Models\GoodTransferCheckDetail;
use App\Models\GoodTransferCheckHeader;
use App\Models\WarehouseCheckDetail;
use App\Models\WarehouseCheckHeader;
use App\Services\AdminQtyOverrideService;
use Illuminate\Http\Request;

class AdminQtyOverrideController extends Controller
{
    public function __construct(
        private readonly AdminQtyOverrideService $service
    ) {
    }

    public function index()
    {
        $invoiceDrafts = WarehouseCheckHeader::query()
            ->with(['picker', 'checker'])
            ->where('status', 'DRAFT')
            ->latest('id')
            ->limit(50)
            ->get();

        $transferDrafts = GoodTransferCheckHeader::query()
            ->with(['picker', 'checker'])
            ->where('status', 'DRAFT')
            ->latest('id')
            ->limit(50)
            ->get();

        return view(
            'admin.qty_overrides',
            compact('invoiceDrafts', 'transferDrafts')
        );
    }

    public function invoice(
        Request $request,
        WarehouseCheckHeader $check,
        WarehouseCheckDetail $detail
    ) {
        $data = $this->validatePayload($request);

        try {
            $this->service->adminInvoiceQtyOverride(
                $check,
                $detail,
                $data['otp'],
                (float) $data['override_qty'],
                $data['reason_note'] ?? null,
                $request->user()
            );

            return back()->with(
                'success',
                'Qty Invoice berhasil di-override oleh Admin.'
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'admin_qty_override' => $e->getMessage(),
            ]);
        }
    }

    public function goodTransfer(
        Request $request,
        GoodTransferCheckHeader $transfer,
        GoodTransferCheckDetail $detail
    ) {
        $data = $this->validatePayload($request);

        try {
            $this->service->adminGoodTransferQtyOverride(
                $transfer,
                $detail,
                $data['otp'],
                (float) $data['override_qty'],
                $data['reason_note'] ?? null,
                $request->user()
            );

            return back()->with(
                'success',
                'Qty Good Transfer berhasil di-override oleh Admin.'
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'admin_qty_override' => $e->getMessage(),
            ]);
        }
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'otp' => ['required', 'digits:6'],
            'override_qty' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999',
            ],
            'reason_note' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
