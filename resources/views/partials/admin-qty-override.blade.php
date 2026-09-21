@if($canAdminOverrideQty)
    <div id="adminQtyOverrideModal" class="app-modal" aria-hidden="true">
        <div class="app-modal-backdrop" data-close-admin-qty></div>

        <div class="app-modal-panel override-modal-panel">
            <div class="modal-head">
                <div>
                    <div class="eyebrow">ADMIN APPROVAL</div>
                    <h2>Qty Override</h2>
                </div>

                <button
                    type="button"
                    class="modal-close"
                    data-close-admin-qty
                    aria-label="Tutup"
                >×</button>
            </div>

            <div class="override-item-box">
                <span>Item</span>
                <strong id="adminQtyItemCode">-</strong>
                <div id="adminQtyItemName" class="muted small">-</div>
                <div class="muted small" style="margin-top:6px;">
                    Qty Dokumen:
                    <strong id="adminQtyExpected">-</strong>
                    <span id="adminQtyUom"></span>
                </div>
            </div>

            <div class="otp-help">
                <strong>Qty Override khusus Admin</strong>
                <p>
                    Masukkan qty aktual dalam UOM dokumen dan kode OTP aktif.
                    OTP dapat dibuat oleh role Supervisor atau Admin melalui menu
                    <b>Supervisor OTP</b>.
                </p>
            </div>

            <form id="adminQtyOverrideForm" method="POST" class="stack">
                @csrf

                <label>
                    Qty Aktual / Qty Override
                    <input
                        id="adminOverrideQty"
                        name="override_qty"
                        type="number"
                        min="0"
                        max="999999999"
                        step="0.0001"
                        inputmode="decimal"
                        required
                    >
                    <span class="muted small">
                        Gunakan UOM yang sama dengan baris dokumen.
                    </span>
                </label>

                <label>
                    OTP
                    <input
                        id="adminOverrideOtp"
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
                    Catatan Admin
                    <textarea
                        name="reason_note"
                        rows="3"
                        maxlength="500"
                        placeholder="Alasan perubahan qty / catatan (opsional)"
                    ></textarea>
                </label>

                <div class="alert alert-warning-soft">
                    Qty ini hanya mengubah hasil checking dan status item menjadi
                    <strong>OVERRIDE</strong>. Qty pada dokumen ERP tidak diubah.
                </div>

                <button type="submit" class="btn btn-warning btn-lg btn-block">
                    Verifikasi OTP & Override Qty
                </button>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    (() => {
        const modal = document.getElementById('adminQtyOverrideModal');
        const form = document.getElementById('adminQtyOverrideForm');
        const itemCode = document.getElementById('adminQtyItemCode');
        const itemName = document.getElementById('adminQtyItemName');
        const expected = document.getElementById('adminQtyExpected');
        const uom = document.getElementById('adminQtyUom');
        const qtyInput = document.getElementById('adminOverrideQty');
        const otpInput = document.getElementById('adminOverrideOtp');

        function open(button) {
            form.action = button.dataset.overrideUrl;
            form.reset();

            itemCode.textContent = button.dataset.itemCode || '-';
            itemName.textContent = button.dataset.itemName || '-';
            expected.textContent = button.dataset.expectedQty || '-';
            uom.textContent = button.dataset.uom ? ' ' + button.dataset.uom : '';

            qtyInput.value = button.dataset.currentQty || button.dataset.expectedQty || '';

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');

            setTimeout(() => qtyInput.focus(), 100);
        }

        function close() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        document.querySelectorAll('.js-admin-qty-override').forEach(button => {
            button.addEventListener('click', () => open(button));
        });

        document.querySelectorAll('[data-close-admin-qty]').forEach(element => {
            element.addEventListener('click', close);
        });

        otpInput.addEventListener('input', () => {
            otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                close();
            }
        });
    })();
    </script>
    @endpush
@endif
