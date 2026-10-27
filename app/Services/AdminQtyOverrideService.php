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
use Illuminate\Support\Facades\Schema;

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

                if ($overrideBaseQty !== null) {
                    $lineUpdate['scanned_base_qty'] = $overrideBaseQty;
                }

                $locked->update($lineUpdate);

                $values = $this->compatibleOverrideValues(
                    'warehouse_check_overrides',
                    [
                        'check_header_id' => $header->id,
                        'supervisor_user_id' => $otp->supervisor_user_id,
                        'checker_user_id' => $header->checker_user_id,
                        'admin_user_id' => $admin->id,
                        'otp_id' => $otp->id,
                        'reason_code' => 'ADMIN_QTY_OVERRIDE',
                        'reason_note' => $this->legacyAdminAuditNote(
                            'warehouse_check_overrides',
                            $reasonNote,
                            $admin
                        ),
                        'override_qty' => $overrideQty,
                        'override_base_qty' => $overrideBaseQty,
                        'overridden_at' => now(),
                    ]
                );

                WarehouseCheckOverride::query()->updateOrCreate(
                    ['check_detail_id' => $locked->id],
                    $values
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

                $values = $this->compatibleOverrideValues(
                    'good_transfer_check_overrides',
                    [
                        'check_header_id' => $header->id,
                        'supervisor_user_id' => $otp->supervisor_user_id,
                        'checker_user_id' => $header->checker_user_id,
                        'admin_user_id' => $admin->id,
                        'otp_id' => $otp->id,
                        'reason_code' => 'ADMIN_QTY_OVERRIDE',
                        'reason_note' => $this->legacyAdminAuditNote(
                            'good_transfer_check_overrides',
                            $reasonNote,
                            $admin
                        ),
                        'override_qty' => $overrideQty,
                        'override_base_qty' => $overrideBaseQty,
                        'overridden_at' => now(),
                    ]
                );

                GoodTransferCheckOverride::query()->updateOrCreate(
                    ['check_detail_id' => $locked->id],
                    $values
                );

                return $locked->fresh();
            }
        );
    }

    /**
     * Old DB_AppHub installations do not always have the three newer columns.
     * Do not include a missing column in INSERT/UPDATE, otherwise SQL Server
     * raises "Invalid column name 'admin_user_id'" as seen in production.
     */
    private function compatibleOverrideValues(string $table, array $values): array
    {
        foreach (['admin_user_id', 'override_qty', 'override_base_qty'] as $column) {
            if (! Schema::connection('apphub')->hasColumn($table, $column)) {
                unset($values[$column]);
            }
        }

        return $values;
    }

    /**
     * If legacy schema has no admin_user_id, preserve the admin identity in the
     * note so audit information is not completely lost.
     */
    private function legacyAdminAuditNote(string $table, ?string $reasonNote, User $admin): ?string
    {
        if (Schema::connection('apphub')->hasColumn($table, 'admin_user_id')) {
            return $reasonNote;
        }

        $audit = '[Admin '.$admin->name.' #'.$admin->id.']';
        $note = trim($audit.' '.trim((string) $reasonNote));

        return substr($note, 0, 500);
    }

    private function assertAdmin(User $admin): void
    {
        if ($admin->role !== 'admin' || ! $admin->is_active) {
            throw new \RuntimeException(
                'Hanya admin aktif yang dapat melakukan Qty Override.'
            );
        }
    }

    private function assertDraft(?string $status, string $document): void
    {
        if ($status !== 'DRAFT') {
            throw new \RuntimeException(
                $document.' harus berstatus DRAFT untuk Qty Override.'
            );
        }
    }

    private function normalizeQty(float $qty): float
    {
        if (! is_finite($qty) || $qty < 0) {
            throw new \RuntimeException('Qty Override harus 0 atau lebih besar.');
        }

        return round($qty, 4);
    }
}
