<?php

namespace App\Http\Controllers;

use App\Models\GoodTransferCheckDetail;
use App\Models\GoodTransferCheckHeader;
use App\Models\User;
use App\Services\GoodTransferCheckService;
use Illuminate\Http\Request;

class GoodTransferCheckController extends Controller
{
    public function __construct(
        private readonly GoodTransferCheckService $service
    ) {
    }

    public function index(Request $request)
    {
        $query = GoodTransferCheckHeader::query()
            ->with(['picker', 'checker'])
            ->withCount('scanErrors')
            ->latest('id');

        if ($request->filled('mutation')) {
            $query->where(
                'mutation_number',
                'like',
                '%' . trim($request->mutation) . '%'
            );
        }

        if ($request->filled('company')) {
            $query->where('company', strtoupper($request->company));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }

        $rows = $query->paginate(25)->withQueryString();

        return view('good_transfers.index', compact('rows'));
    }

    public function create()
    {
        $pickers = User::query()
            ->where('role', 'picker')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('good_transfers.create', compact('pickers'));
    }

    public function loadTransfer(Request $request)
    {
        $data = $request->validate([
            'company' => ['required', 'in:INGCO,SMI'],
            'mutation_number' => ['required', 'string', 'max:100'],
            'picker_user_id' => ['required', 'integer'],
        ]);

        $picker = User::query()->findOrFail($data['picker_user_id']);

        try {
            $header = $this->service->start(
                $data['company'],
                $data['mutation_number'],
                $picker,
                $request->user()
            );

            return redirect()->route('good-transfers.show', $header);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['mutation_number' => $e->getMessage()]);
        }
    }

    public function show(
        Request $request,
        GoodTransferCheckHeader $transfer
    ) {
        $transfer->load([
            'details.overrideRecord.admin',
            'picker',
            'checker',
        ]);

        $user = $request->user();

        $this->claimLegacyDraftForChecker($transfer, $user);

        $ready = $this->service->isReadyToFinalize($transfer);

        $canOperate =
            $transfer->status === 'DRAFT'
            && $user->role === 'checker'
            && (int) $transfer->checker_user_id === (int) $user->id;

        $canAdminOverrideQty =
            $transfer->status === 'DRAFT'
            && $user->role === 'admin';

        return view(
            'good_transfers.show',
            compact('transfer', 'ready', 'canOperate', 'canAdminOverrideQty')
        );
    }

    public function scan(
        Request $request,
        GoodTransferCheckHeader $transfer
    ) {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:50'],
        ]);

        try {
            $result = $this->service->scan(
                $transfer,
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
        GoodTransferCheckHeader $transfer,
        GoodTransferCheckDetail $detail
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
                $transfer,
                $detail,
                $data['otp'],
                $data['reason_code'],
                $data['reason_note'] ?? null,
                $request->user()
            );

            return back()->with('success', 'Baris Good Transfer berhasil di-override.');
        } catch (\Throwable $e) {
            return back()->withErrors(['override' => $e->getMessage()]);
        }
    }

    public function finalize(
        Request $request,
        GoodTransferCheckHeader $transfer
    ) {
        try {
            $this->service->finalize($transfer, $request->user());

            return redirect()
                ->route('dashboard')
                ->with(
                    'success',
                    'Good Transfer berhasil disimpan. CheckedBy dan CheckedDateTime pada IC_Mutations sudah di-update.'
                );
        } catch (\Throwable $e) {
            return back()->withErrors(['finalize' => $e->getMessage()]);
        }
    }
    private function claimLegacyDraftForChecker(
        GoodTransferCheckHeader $transfer,
        User $user
    ): void {
        if (
            $transfer->status !== 'DRAFT'
            || $user->role !== 'checker'
            || (int) $transfer->checker_user_id === (int) $user->id
        ) {
            return;
        }

        $currentOwner = $transfer->checker;

        if ($currentOwner && $currentOwner->role === 'checker') {
            return;
        }

        $updated = GoodTransferCheckHeader::query()
            ->whereKey($transfer->id)
            ->where('status', 'DRAFT')
            ->where('checker_user_id', $transfer->checker_user_id)
            ->update([
                'checker_user_id' => $user->id,
                'updated_at' => now(),
            ]);

        if ($updated) {
            $transfer->checker_user_id = $user->id;
            $transfer->setRelation('checker', $user);
        }
    }

}
