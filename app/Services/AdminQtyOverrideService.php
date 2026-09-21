<?php

namespace App\Services;

use App\Models\GoodTransferCheckDetail;
use App\Models\GoodTransferCheckHeader;
use App\Models\GoodTransferCheckOverride;
use App\Models\User;
use App\Models\WarehouseCheckDetail;
use App\Models\WarehouseCheckHeader;
use App\Models\WarehouseCheckOverride;
use Illuminate\Support\Facades\DB;

class AdminQtyOverrideService
{
    public function __construct(
        private readonly SupervisorOtpService $otpService,
    ) {
    }

    public function adminInvoiceQtyOverride(
        WarehouseCheckHeader $header,
        WarehouseCheckDetail $detail,
        string $otpCode,
        float $overrideQty,
        ?string $reasonNote,
        User $admin
    ): WarehouseCheckDetail {
        $this->assertAdmin($admin);
        $this->assertDraft($header->status, 'Invoice');

        if ((int) $detail->check_header_id !== (int) $header->id) {
            throw new \RuntimeException('Detail bukan milik invoice ini.');
        }

        $otp = $this->otpService->validateCode(trim($otpCode));
        $overrideQty = $this->normalizeQty($overrideQty);

        return DB::connection('apphub')->transaction(
            function () use (
                $header,
                $detail,
                $otp,
                $overrideQty,
                $reasonNote,
                $admin
            ) {
                $locked = WarehouseCheckDetail::query()
                    ->whereKey($detail->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertDraft(
                    WarehouseCheckHeader::query()->whereKey($header->id)->value('status'),
                    'Invoice'
                );

                $ratio = $locked->invoice_uom_ratio !== null
                    ? (float) $locked->invoice_uom_ratio
                    : null;

                $overrideBaseQty = $ratio !== null
                    ? $overrideQty * $ratio
                    : null;

                $lineUpdate = [
                    'status' => 'OVERRIDE',
                    'completed_at' => now(),
                ];

                // Jika ratio dokumen tersedia, progress base ikut disesuaikan.
                // Jika NO RATIO, qty override tetap tersimpan dalam UOM dokumen
                // pada record override dan baris tetap dapat difinalisasi.
                if ($overrideBaseQty !== null) {
                    $lineUpdate['scanned_base_qty'] = $overrideBaseQty;
                }

                $locked->update($lineUpdate);

                WarehouseCheckOverride::query()->updateOrCreate(
                    ['check_detail_id' => $locked->id],
                    [
                        'check_header_id' => $header->id,
                        'supervisor_user_id' => $otp->supervisor_user_id,
                        'checker_user_id' => $header->checker_user_id,
                        'admin_user_id' => $admin->id,
                        'otp_id' => $otp->id,
                        'reason_code' => 'ADMIN_QTY_OVERRIDE',
                        'reason_note' => $reasonNote,
                        'override_qty' => $overrideQty,
                        'override_base_qty' => $overrideBaseQty,
                        'overridden_at' => now(),
                    ]
                );

                return $locked->fresh();
            }
        );
    }

    public function adminGoodTransferQtyOverride(
        GoodTransferCheckHeader $header,
        GoodTransferCheckDetail $detail,
        string $otpCode,
        float $overrideQty,
        ?string $reasonNote,
        User $admin
    ): GoodTransferCheckDetail {
        $this->assertAdmin($admin);
        $this->assertDraft($header->status, 'Good Transfer');

        if ((int) $detail->check_header_id !== (int) $header->id) {
            throw new \RuntimeException('Detail bukan milik Good Transfer ini.');
        }

        $otp = $this->otpService->validateCode(trim($otpCode));
        $overrideQty = $this->normalizeQty($overrideQty);

        return DB::connection('apphub')->transaction(
            function () use (
                $header,
                $detail,
                $otp,
                $overrideQty,
                $reasonNote,
                $admin
            ) {
                $locked = GoodTransferCheckDetail::query()
                    ->whereKey($detail->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertDraft(
                    GoodTransferCheckHeader::query()->whereKey($header->id)->value('status'),
                    'Good Transfer'
                );

                $ratio = $locked->transfer_uom_ratio !== null
                    ? (float) $locked->transfer_uom_ratio
                    : null;

                $overrideBaseQty = $ratio !== null
                    ? $overrideQty * $ratio
                    : null;

                $lineUpdate = [
                    'status' => 'OVERRIDE',
                    'completed_at' => now(),
                ];

                if ($overrideBaseQty !== null) {
                    $lineUpdate['scanned_base_qty'] = $overrideBaseQty;
                }

                $locked->update($lineUpdate);

                GoodTransferCheckOverride::query()->updateOrCreate(
                    ['check_detail_id' => $locked->id],
                    [
                        'check_header_id' => $header->id,
                        'supervisor_user_id' => $otp->supervisor_user_id,
                        'checker_user_id' => $header->checker_user_id,
                        'admin_user_id' => $admin->id,
                        'otp_id' => $otp->id,
                        'reason_code' => 'ADMIN_QTY_OVERRIDE',
                        'reason_note' => $reasonNote,
                        'override_qty' => $overrideQty,
                        'override_base_qty' => $overrideBaseQty,
                        'overridden_at' => now(),
                    ]
                );

                return $locked->fresh();
            }
        );
    }

    private function assertAdmin(User $admin): void
    {
        if ($admin->role !== 'admin' || !$admin->is_active) {
            throw new \RuntimeException(
                'Hanya admin aktif yang dapat melakukan Qty Override.'
            );
        }
    }

    private function assertDraft(?string $status, string $document): void
    {
        if ($status !== 'DRAFT') {
            throw new \RuntimeException(
                $document . ' harus berstatus DRAFT untuk Qty Override.'
            );
        }
    }

    private function normalizeQty(float $qty): float
    {
        if (!is_finite($qty) || $qty < 0) {
            throw new \RuntimeException('Qty Override harus 0 atau lebih besar.');
        }

        return round($qty, 4);
    }
}
