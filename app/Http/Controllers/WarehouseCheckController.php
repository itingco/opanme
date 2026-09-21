<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WarehouseCheckDetail;
use App\Models\WarehouseCheckHeader;
use App\Services\GoodTransferCheckService;
use App\Services\WarehouseCheckService;
use Illuminate\Http\Request;

class WarehouseCheckController extends Controller
{
    public function __construct(
        private readonly WarehouseCheckService $service,
        private readonly GoodTransferCheckService $goodTransferService
    ) {
    }

    public function create()
    {
        $pickers = User::query()
            ->where('role', 'picker')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('checks.create', compact('pickers'));
    }

    public function loadDocument(Request $request)
    {
        $data = $request->validate([
            'document_type' => ['required', 'in:invoice,good_transfer'],
            'company' => ['required', 'in:INGCO,SMI'],
            'document_number' => ['required', 'string', 'max:100'],
            'picker_user_id' => ['required', 'integer'],
        ]);

        $picker = User::query()->findOrFail($data['picker_user_id']);

        try {
            if ($data['document_type'] === 'good_transfer') {
                $header = $this->goodTransferService->start(
                    $data['company'],
                    $data['document_number'],
                    $picker,
                    $request->user()
                );

                return redirect()->route('good-transfers.show', $header);
            }

            $header = $this->service->start(
                $data['company'],
                $data['document_number'],
                $picker,
                $request->user()
            );

            return redirect()->route('checks.show', $header);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['document_number' => $e->getMessage()]);
        }
    }

    public function loadInvoice(Request $request)
    {
        $data = $request->validate([
            'company' => ['required', 'in:INGCO,SMI'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'picker_user_id' => ['required', 'integer'],
        ]);

        $picker = User::query()->findOrFail($data['picker_user_id']);

        try {
            $header = $this->service->start(
                $data['company'],
                $data['invoice_number'],
                $picker,
                $request->user()
            );

            return redirect()->route('checks.show', $header);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['invoice_number' => $e->getMessage()]);
        }
    }

    public function show(Request $request, WarehouseCheckHeader $check)
    {
        $check->load([
            'details.overrideRecord.admin',
            'picker',
            'checker',
        ]);

        $user = $request->user();

        // Draft lama bisa masih tercatat atas admin dari versi sebelumnya.
        // Checker yang membuka draft tersebut mengambil ownership agar scanner aktif.
        // Draft yang sudah dimiliki checker lain tidak akan diambil alih.
        $this->claimLegacyDraftForChecker($check, $user);

        $ready = $this->service->isReadyToFinalize($check);

        $canOperate =
            $check->status === 'DRAFT'
            && $user->role === 'checker'
            && (int) $check->checker_user_id === (int) $user->id;

        $canAdminOverrideQty =
            $check->status === 'DRAFT'
            && $user->role === 'admin';

        return view(
            'checks.show',
            compact('check', 'ready', 'canOperate', 'canAdminOverrideQty')
        );
    }

    public function scan(Request $request, WarehouseCheckHeader $check)
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:50'],
        ]);

        try {
            $result = $this->service->scan(
                $check,
                $data['barcode'],
                $request->user()
            );

            $line = $result['line'];

            return response()->json([
                'ok' => true,
                'accepted' => $result['accepted'],
                'requires_override' => $result['requires_override'],
                'message' => $result['message'],
                'line' => $line ? [
                    'id' => $line->id,
                    'scanned_base_qty' => (float) $line->scanned_base_qty,
                    'expected_base_qty' => $line->expected_base_qty !== null
                        ? (float) $line->expected_base_qty
                        : null,
                    'status' => $line->status,
                ] : null,
                'session_complete' => $result['complete'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function override(
        Request $request,
        WarehouseCheckHeader $check,
        WarehouseCheckDetail $detail
    ) {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
            'reason_code' => [
                'required',
                'in:NO_BARCODE,SPECIAL_ITEM,DAMAGED_BARCODE,NO_RATIO,OTHER',
            ],
            'reason_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->override(
                $check,
                $detail,
                $data['otp'],
                $data['reason_code'],
                $data['reason_note'] ?? null,
                $request->user()
            );

            return back()->with('success', 'Baris berhasil di-override.');
        } catch (\Throwable $e) {
            return back()->withErrors(['override' => $e->getMessage()]);
        }
    }

    public function finalize(Request $request, WarehouseCheckHeader $check)
    {
        try {
            $this->service->finalize($check, $request->user());

            return redirect()
                ->route('dashboard')
                ->with(
                    'success',
                    'Checking invoice berhasil disimpan. CheckedBy dan CheckedDateTime pada AR_Invoices sudah di-update.'
                );
        } catch (\Throwable $e) {
            return back()->withErrors(['finalize' => $e->getMessage()]);
        }
    }
    private function claimLegacyDraftForChecker(
        WarehouseCheckHeader $check,
        User $user
    ): void {
        if (
            $check->status !== 'DRAFT'
            || $user->role !== 'checker'
            || (int) $check->checker_user_id === (int) $user->id
        ) {
            return;
        }

        $currentOwner = $check->checker;

        // Jangan mengambil session milik checker aktif lain.
        if ($currentOwner && $currentOwner->role === 'checker') {
            return;
        }

        $updated = WarehouseCheckHeader::query()
            ->whereKey($check->id)
            ->where('status', 'DRAFT')
            ->where('checker_user_id', $check->checker_user_id)
            ->update([
                'checker_user_id' => $user->id,
                'updated_at' => now(),
            ]);

        if ($updated) {
            $check->checker_user_id = $user->id;
            $check->setRelation('checker', $user);
        }
    }

}
