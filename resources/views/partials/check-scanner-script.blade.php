<script
    src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"
    integrity="sha512-r6rDA7W6ZeQhvl8S7yRVQUKVHdexq+GAlNkNNqVC7YyIV+NwqCTJe2hDWCiffTyRNOeGEzRRJ9ifvRm/HCzGYg=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
></script>

<script>
(() => {
    const barcodeInput = document.getElementById('barcodeInput');
    const scanMessage = document.getElementById('scanMessage');
    const finalizeBtn = document.getElementById('finalizeBtn');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const scanUrl = @json($scanUrl);

    const overrideModal = document.getElementById('overrideModal');
    const overrideForm = document.getElementById('overrideForm');
    const overrideOtp = document.getElementById('overrideOtp');
    const overrideItemCode = document.getElementById('overrideItemCode');
    const overrideItemName = document.getElementById('overrideItemName');

    const cameraModal = document.getElementById('cameraModal');
    const openCameraBtn = document.getElementById('openCameraBtn');
    const startCameraBtn = document.getElementById('startCameraBtn');
    const stopCameraBtn = document.getElementById('stopCameraBtn');
    const cameraStatus = document.getElementById('cameraStatus');
    const cameraSecureWarning = document.getElementById('cameraSecureWarning');

    let submitBusy = false;
    let html5QrCode = null;
    let cameraRunning = false;
    let cameraSubmitting = false;

    // IMPORTANT:
    // Setelah satu barcode berhasil/ditolak, scanner TIDAK boleh membaca lagi
    // sampai barcode benar-benar keluar dari frame selama beberapa saat.
    // Ini mencegah kasus video: scan pertama masuk, barcode masih terlihat,
    // lalu scan kedua terkirim dan menghasilkan OVER_QTY/error.
    let cameraNeedsClear = false;
    let clearStartedAt = null;
    const CAMERA_CLEAR_MS = 700;

    function setScanMessage(text, type = '') {
        scanMessage.textContent = text;
        scanMessage.className = 'scan-message ' + type;
    }

    function setCameraStatus(text, type = '') {
        cameraStatus.textContent = text;
        cameraStatus.className = 'camera-status ' + type;
    }

    function focusManualScanner() {
        setTimeout(() => {
            if (!submitBusy && barcodeInput && !barcodeInput.disabled) {
                barcodeInput.focus();
            }
        }, 100);
    }

    function updateLineUI(line) {
        if (!line) return;

        const row = document.getElementById('line-' + line.id);
        if (!row) return;

        const progress = row.querySelector('.progress-text');
        if (progress && line.expected_base_qty !== null) {
            progress.textContent =
                Number(line.scanned_base_qty).toFixed(4)
                + ' / '
                + Number(line.expected_base_qty).toFixed(4);
        }

        const badge = row.querySelector('.line-status');
        if (badge) {
            badge.textContent = line.status;
            badge.className =
                'badge line-status badge-' + String(line.status).toLowerCase();
        }

        if (line.status === 'OK') {
            const overrideBtn = row.querySelector('.js-open-override');
            if (overrideBtn) overrideBtn.remove();
        }
    }

    async function parseResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            return await response.json();
        }

        if (response.status === 419) {
            return {
                ok: false,
                message: 'Session login / CSRF sudah kedaluwarsa. Refresh halaman lalu login kembali.'
            };
        }

        return {
            ok: false,
            message: 'Server mengembalikan HTTP ' + response.status + '. Cek laravel.log untuk detail.'
        };
    }

    async function submitBarcode(barcode, source = 'manual') {
        barcode = String(barcode || '').trim();

        if (submitBusy || barcode === '') {
            return { ok: false, ignored: true };
        }

        submitBusy = true;
        barcodeInput.disabled = true;
        setScanMessage('Memproses barcode ' + barcode + '...');

        try {
            const response = await fetch(scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ barcode })
            });

            const data = await parseResponse(response);

            if (!response.ok || !data.ok) {
                setScanMessage(data.message || 'Scan gagal.', 'error');
                return { ok: false, data };
            }

            updateLineUI(data.line);

            if (data.session_complete && finalizeBtn) {
                finalizeBtn.disabled = false;
            }

            if (data.requires_override) {
                setScanMessage(data.message, 'warning');

                if (data.line) {
                    await stopCamera();
                    await closeCameraModal();
                    openOverrideByLine(data.line.id);
                }

                return {
                    ok: true,
                    accepted: false,
                    requiresOverride: true,
                    data
                };
            }

            if (navigator.vibrate && source === 'camera') {
                navigator.vibrate(80);
            }

            setScanMessage(data.message || 'Scan diterima.', 'success');

            return {
                ok: true,
                accepted: data.accepted !== false,
                requiresOverride: false,
                data
            };
        } catch (error) {
            console.error('Barcode submit error:', error);
            setScanMessage('Koneksi / response server gagal. Coba scan ulang.', 'error');
            return { ok: false, error };
        } finally {
            barcodeInput.value = '';
            barcodeInput.disabled = false;
            submitBusy = false;

            if (!cameraModal.classList.contains('is-open')) {
                focusManualScanner();
            }
        }
    }

    barcodeInput.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        submitBarcode(barcodeInput.value, 'manual');
    });

    function openOverrideModal(button) {
        overrideForm.action = button.dataset.overrideUrl;
        overrideItemCode.textContent = button.dataset.itemCode || '-';
        overrideItemName.textContent = button.dataset.itemName || '-';
        overrideForm.reset();
        overrideModal.classList.add('is-open');
        overrideModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        setTimeout(() => overrideOtp.focus(), 150);
    }

    function openOverrideByLine(lineId) {
        const row = document.getElementById('line-' + lineId);
        if (!row) return;
        const button = row.querySelector('.js-open-override');
        if (button) openOverrideModal(button);
    }

    function closeOverrideModal() {
        overrideModal.classList.remove('is-open');
        overrideModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        focusManualScanner();
    }

    document.querySelectorAll('.js-open-override').forEach(button => {
        button.addEventListener('click', () => openOverrideModal(button));
    });

    document.querySelectorAll('[data-close-override]').forEach(element => {
        element.addEventListener('click', closeOverrideModal);
    });

    overrideOtp.addEventListener('input', () => {
        overrideOtp.value = overrideOtp.value.replace(/\D/g, '').slice(0, 6);
    });

    function openCameraModal() {
        cameraModal.classList.add('is-open');
        cameraModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        cameraSecureWarning.hidden = window.isSecureContext;
        cameraNeedsClear = false;
        clearStartedAt = null;
        setCameraStatus('Tekan "Mulai Kamera".');
    }

    async function closeCameraModal() {
        await stopCamera();
        cameraModal.classList.remove('is-open');
        cameraModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        focusManualScanner();
    }

    async function startCamera() {
        if (cameraRunning) return;

        if (typeof Html5Qrcode === 'undefined') {
            setCameraStatus(
                'Library scanner gagal dimuat. Pastikan koneksi internet tersedia.',
                'error'
            );
            return;
        }

        setCameraStatus(
            window.isSecureContext
                ? 'Meminta izin kamera...'
                : 'Browser HP dapat menolak kamera karena halaman belum HTTPS.',
            window.isSecureContext ? '' : 'warning'
        );

        try {
            html5QrCode = html5QrCode || new Html5Qrcode('cameraReader');

            await html5QrCode.start(
                { facingMode: 'environment' },
                {
                    fps: 12,
                    qrbox: (viewfinderWidth, viewfinderHeight) => {
                        const width = Math.floor(
                            Math.min(viewfinderWidth * 0.88, 420)
                        );
                        const height = Math.floor(
                            Math.min(viewfinderHeight * 0.34, 180)
                        );
                        return { width, height };
                    },
                    aspectRatio: 1.777778
                },
                async decodedText => {
                    // Selama barcode lama belum keluar dari frame, setiap decode
                    // berikutnya diabaikan. Success callback juga me-reset timer
                    // clear sehingga dibutuhkan gap tanpa barcode yang nyata.
                    if (cameraNeedsClear) {
                        clearStartedAt = null;
                        return;
                    }

                    if (cameraSubmitting) return;
                    cameraSubmitting = true;

                    setCameraStatus(
                        'Barcode: ' + decodedText + ' — memproses...'
                    );

                    const result = await submitBarcode(decodedText, 'camera');

                    // Tidak peduli accepted/error, tunggu barcode keluar dari frame
                    // sebelum mengizinkan decode selanjutnya agar tidak double scan.
                    cameraNeedsClear = true;
                    clearStartedAt = null;
                    cameraSubmitting = false;

                    if (result.ok && !result.requiresOverride) {
                        setCameraStatus(
                            'Berhasil: ' + decodedText
                            + '. Jauhkan barcode sebentar untuk scan berikutnya.',
                            'success'
                        );
                    } else if (!result.ok && !result.ignored) {
                        setCameraStatus(
                            'Scan terbaca tetapi ditolak: '
                            + (result.data?.message || 'lihat pesan di halaman.'),
                            'error'
                        );
                    }
                },
                () => {
                    if (!cameraNeedsClear || cameraSubmitting) return;

                    if (clearStartedAt === null) {
                        clearStartedAt = Date.now();
                        return;
                    }

                    if (Date.now() - clearStartedAt >= CAMERA_CLEAR_MS) {
                        cameraNeedsClear = false;
                        clearStartedAt = null;
                        setCameraStatus(
                            'Siap scan berikutnya.',
                            'success'
                        );
                    }
                }
            );

            cameraRunning = true;
            startCameraBtn.disabled = true;
            stopCameraBtn.disabled = false;
            setCameraStatus(
                'Kamera aktif. Arahkan barcode ke kotak scan.',
                'success'
            );
        } catch (error) {
            cameraRunning = false;
            startCameraBtn.disabled = false;
            stopCameraBtn.disabled = true;
            setCameraStatus(
                'Tidak bisa membuka kamera: ' + (error?.message || String(error)),
                'error'
            );
        }
    }

    async function stopCamera() {
        if (!html5QrCode || !cameraRunning) return;

        try {
            await html5QrCode.stop();
            await html5QrCode.clear();
        } catch (error) {
            console.debug('Camera stop ignored:', error);
        }

        cameraRunning = false;
        cameraSubmitting = false;
        cameraNeedsClear = false;
        clearStartedAt = null;
        startCameraBtn.disabled = false;
        stopCameraBtn.disabled = true;
        setCameraStatus('Kamera dihentikan.');
    }

    openCameraBtn.addEventListener('click', openCameraModal);
    startCameraBtn.addEventListener('click', startCamera);
    stopCameraBtn.addEventListener('click', stopCamera);

    document.querySelectorAll('[data-close-camera]').forEach(element => {
        element.addEventListener('click', closeCameraModal);
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;

        if (overrideModal.classList.contains('is-open')) {
            closeOverrideModal();
            return;
        }

        if (cameraModal.classList.contains('is-open')) {
            closeCameraModal();
        }
    });

    focusManualScanner();
})();
</script>
