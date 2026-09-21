@extends('layouts.app')

@section('title', 'Good Transfer ' . $transfer->mutation_number)

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">GOOD TRANSFER · {{ $transfer->company }} · {{ $transfer->source_database }}</div>
        <h1>{{ $transfer->mutation_number }}</h1>
        <p class="muted">
            {{ optional($transfer->mutation_date)->format('d-m-Y') }}
            · {{ $transfer->source_warehouse_name ?: '-' }}
            → {{ $transfer->destination_warehouse_name ?: '-' }}
        </p>
    </div>

    <span class="badge badge-{{ strtolower($transfer->status) }}">
        {{ $transfer->status }}
    </span>
</div>

<div class="summary-grid">
    <div class="stat">
        <span>Picker</span>
        <strong>{{ $transfer->picker->name }}</strong>
    </div>
    <div class="stat">
        <span>Checker</span>
        <strong>{{ $transfer->checker->name }}</strong>
    </div>
    <div class="stat">
        <span>Perpindahan</span>
        <strong>
            {{ $transfer->source_warehouse_name ?: '-' }}
            → {{ $transfer->destination_warehouse_name ?: '-' }}
        </strong>
    </div>
</div>

@if($canAdminOverrideQty)
    <div class="alert alert-warning-soft">
        Mode Admin: scan dan final save tetap hanya untuk Checker pemilik session.
        Admin dapat menggunakan <strong>Qty Override</strong> dengan OTP pada item di bawah.
    </div>
@elseif(!$canOperate && $transfer->status === 'DRAFT')
    <div class="alert alert-warning-soft">
        Mode lihat saja. Hanya user role <strong>checker</strong> yang memiliki session ini
        yang dapat melakukan scan, Supervisor Override, dan final save.
    </div>
@endif

@if($canOperate)
    <div class="scanner-card">
        <div>
            <div class="eyebrow">GOOD TRANSFER BARCODE CHECKING</div>
            <h2>Scan barang transfer</h2>
            <p class="muted">
                UOM diambil dari MutationDetails.UOMLevel → IC_Items.UOMID1..4 → IC_UOM.UOMCode.
            </p>
        </div>

        <div class="scanner-actions">
            <div class="scanner-input-wrap">
                <input
                    id="barcodeInput"
                    class="scanner-input"
                    autocomplete="off"
                    placeholder="Scan barcode + Enter"
                    inputmode="text"
                    autofocus
                >
                <div id="scanMessage" class="scan-message">Menunggu scan...</div>
            </div>

            <button type="button" id="openCameraBtn" class="btn btn-camera btn-lg">
                <span aria-hidden="true">📷</span> Scan via Kamera
            </button>
        </div>
    </div>
@endif

<div class="card table-card">
    <div class="table-scroll">
        <table class="responsive-check-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>UOM</th>
                <th>Qty Transfer</th>
                <th>Ratio Transfer</th>
                <th>Progress Base</th>
                <th>Status</th>
                @if($canOperate)<th>Supervisor Override</th>@endif
                @if($canAdminOverrideQty)<th>Admin Qty Override</th>@endif
            </tr>
            </thead>
            <tbody>
            @foreach($transfer->details as $line)
                <tr
                    id="line-{{ $line->id }}"
                    data-line-id="{{ $line->id }}"
                    data-item-code="{{ $line->item_code }}"
                    data-item-name="{{ $line->item_name }}"
                >
                    <td data-label="#">{{ $loop->iteration }}</td>
                    <td data-label="Item">
                        <strong>{{ $line->item_code }}</strong>
                        <div class="muted small">{{ $line->item_name }}</div>
                    </td>
                    <td data-label="UOM">{{ $line->uom_code ?: '-' }}</td>
                    <td data-label="Qty Transfer">{{ number_format((float) $line->expected_qty, 4) }}</td>
                    <td data-label="Ratio">
                        @if($line->transfer_uom_ratio === null)
                            <span class="badge badge-warning">NO RATIO</span>
                        @else
                            {{ $line->transfer_uom_ratio }}
                        @endif
                    </td>
                    <td data-label="Progress">
                        <strong class="progress-text">
                            @if($line->expected_base_qty === null)
                                N/A
                            @else
                                {{ number_format((float) $line->scanned_base_qty, 4) }}
                                / {{ number_format((float) $line->expected_base_qty, 4) }}
                            @endif
                        </strong>
                    </td>
                    <td data-label="Status">
                        <span class="badge line-status badge-{{ strtolower($line->status) }}">
                            {{ $line->status }}
                        </span>

                        @if($line->status === 'OVERRIDE' && $line->overrideRecord)
                            @php
                                $overrideReasonLabels = [
                                    'NO_BARCODE' => 'Tidak punya barcode',
                                    'SPECIAL_ITEM' => 'Special item',
                                    'DAMAGED_BARCODE' => 'Barcode rusak',
                                    'NO_RATIO' => 'Ratio tidak tersedia',
                                    'OTHER' => 'Lainnya',
                                    'ADMIN_QTY_OVERRIDE' => 'Admin Qty Override',
                                ];
                                $overrideReason = $overrideReasonLabels[$line->overrideRecord->reason_code]
                                    ?? $line->overrideRecord->reason_code;
                            @endphp
                            <div style="margin-top:7px;font-size:.78rem;line-height:1.4;color:#6b28a8;">
                                <strong>Alasan:</strong> {{ $overrideReason }}
                                @if($line->overrideRecord->override_qty !== null)
                                    <div style="margin-top:2px;color:#6b7280;">
                                        <strong>Qty Override:</strong>
                                        {{ number_format((float) $line->overrideRecord->override_qty, 4) }}
                                        {{ $line->uom_code ?: '' }}
                                    </div>
                                @endif
                                @if($line->overrideRecord->admin)
                                    <div style="margin-top:2px;color:#6b7280;">
                                        <strong>Admin:</strong> {{ $line->overrideRecord->admin->name }}
                                    </div>
                                @endif
                                @if($line->overrideRecord->reason_note)
                                    <div style="margin-top:2px;color:#6b7280;">
                                        {{ $line->overrideRecord->reason_note }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </td>

                    @if($canOperate)
                        <td data-label="Override">
                            @if($line->status === 'PENDING')
                                <button
                                    type="button"
                                    class="btn btn-warning btn-sm js-open-override"
                                    data-detail-id="{{ $line->id }}"
                                    data-item-code="{{ $line->item_code }}"
                                    data-item-name="{{ $line->item_name }}"
                                    data-override-url="{{ route('good-transfers.override', [$transfer, $line]) }}"
                                >
                                    Supervisor Override
                                </button>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    @endif

                    @if($canAdminOverrideQty)
                        <td data-label="Admin Qty Override">
                            <button
                                type="button"
                                class="btn btn-warning btn-sm js-admin-qty-override"
                                data-item-code="{{ $line->item_code }}"
                                data-item-name="{{ $line->item_name }}"
                                data-expected-qty="{{ (float) $line->expected_qty }}"
                                data-current-qty="{{ $line->overrideRecord?->override_qty !== null ? (float) $line->overrideRecord->override_qty : '' }}"
                                data-uom="{{ $line->uom_code }}"
                                data-override-url="{{ route('admin.good-transfer.qty-override', [$transfer, $line]) }}"
                            >
                                Qty Override
                            </button>
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($canOperate)
    <div class="save-bar">
        <div>
            <strong>Final Save</strong>
            <div class="muted small">
                Aktif setelah semua baris OK / OVERRIDE. Final save akan mengisi
                IC_Mutations.CheckedBy dan IC_Mutations.CheckedDateTime.
            </div>
        </div>

        <form method="POST" action="{{ route('good-transfers.finalize', $transfer) }}">
            @csrf
            <button id="finalizeBtn" class="btn btn-success btn-lg" @disabled(!$ready)>
                Save & Update IC_Mutations
            </button>
        </form>
    </div>

    <div id="cameraModal" class="app-modal" aria-hidden="true">
        <div class="app-modal-backdrop" data-close-camera></div>
        <div class="app-modal-panel camera-modal-panel">
            <div class="modal-head">
                <div>
                    <div class="eyebrow">MOBILE CAMERA</div>
                    <h2>Scan Barcode</h2>
                </div>
                <button type="button" class="modal-close" data-close-camera aria-label="Tutup">×</button>
            </div>

            <div id="cameraSecureWarning" class="alert alert-warning-soft" hidden>
                Kamera browser membutuhkan HTTPS pada HP.
            </div>

            <div class="camera-reader-shell">
                <div id="cameraReader"></div>
                <div class="camera-guide"><div class="camera-guide-line"></div></div>
            </div>

            <div id="cameraStatus" class="camera-status">Tekan "Mulai Kamera".</div>

            <div class="camera-controls">
                <button type="button" id="startCameraBtn" class="btn btn-primary">Mulai Kamera</button>
                <button type="button" id="stopCameraBtn" class="btn btn-light" disabled>Stop Kamera</button>
            </div>

            <p class="muted small camera-note">
                Setelah barcode diproses, jauhkan barcode dari frame sebentar sebelum
                scan berikutnya agar tidak terjadi double-scan.
            </p>
        </div>
    </div>

    <div id="overrideModal" class="app-modal" aria-hidden="true">
        <div class="app-modal-backdrop" data-close-override></div>
        <div class="app-modal-panel override-modal-panel">
            <div class="modal-head">
                <div>
                    <div class="eyebrow">SUPERVISOR APPROVAL</div>
                    <h2>Override Item</h2>
                </div>
                <button type="button" class="modal-close" data-close-override aria-label="Tutup">×</button>
            </div>

            <div class="override-item-box">
                <span>Item</span>
                <strong id="overrideItemCode">-</strong>
                <div id="overrideItemName" class="muted small">-</div>
            </div>

            <div class="otp-help">
                <strong>OTP Supervisor</strong>
                <p>Supervisor generate OTP dari menu Supervisor OTP, lalu masukkan kode 6 digit di bawah.</p>
            </div>

            <form id="overrideForm" method="POST" class="stack">
                @csrf
                <label>
                    OTP Supervisor
                    <input
                        id="overrideOtp"
                        name="otp"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        autocomplete="one-time-code"
                        placeholder="6 digit OTP"
                        required
                    >
                </label>

                <label>
                    Alasan Override
                    <select name="reason_code" required>
                        <option value="">-- Pilih alasan --</option>
                        <option value="NO_BARCODE">Tidak punya barcode</option>
                        <option value="SPECIAL_ITEM">Special item</option>
                        <option value="DAMAGED_BARCODE">Barcode rusak</option>
                        <option value="NO_RATIO">Ratio tidak tersedia</option>
                        <option value="OTHER">Lainnya</option>
                    </select>
                </label>

                <label>
                    Catatan
                    <textarea
                        name="reason_note"
                        rows="3"
                        maxlength="500"
                        placeholder="Catatan tambahan (opsional)"
                    ></textarea>
                </label>

                <button type="submit" class="btn btn-warning btn-lg btn-block">
                    Verifikasi OTP & Override
                </button>
            </form>
        </div>
    </div>
@endif

@include('partials.admin-qty-override')
@endsection

@push('scripts')
@if($canOperate)
    @include('partials.check-scanner-script', [
        'scanUrl' => route('good-transfers.scan', $transfer),
    ])
@endif
@endpush
