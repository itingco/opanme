const BrowserMultiFormatReader = window.ZXingBrowser?.BrowserMultiFormatReader;

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

function feedback(type, title, message) {
    const box = document.getElementById('scan-feedback');
    if (!box) return;
    box.className = `scan-feedback ${type}`;
    box.querySelector('.feedback-icon').textContent = type === 'success' ? '✓' : type === 'error' ? '!' : '⌁';
    box.querySelector('strong').textContent = title;
    box.querySelector('span').textContent = message;
}

// Audio volume is limited by the browser and the phone's physical volume.
window.opanmeLoudBeep = function () {
    try {
        const A = window.AudioContext || window.webkitAudioContext;
        if (!A) return;
        const ctx = window.__opnameAudioCtx || (window.__opnameAudioCtx = new A());
        if (ctx.state === 'suspended') ctx.resume().catch(() => {});
        const t = ctx.currentTime;
        [0, 0.13].forEach((offset, index) => {
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'square'; o.frequency.value = index ? 1046 : 880;
            g.gain.setValueAtTime(0.001, t+offset);
            g.gain.exponentialRampToValueAtTime(0.24, t+offset+0.014);
            g.gain.exponentialRampToValueAtTime(0.001, t+offset+0.11);
            o.connect(g); g.connect(ctx.destination);
            o.start(t+offset); o.stop(t+offset+0.12);
        });
        if (navigator.vibrate) navigator.vibrate([100,50,100]);
    } catch(e) { console.debug('Audio not available', e); }
};
function signal(ok) {
    if (ok) { window.opanmeLoudBeep(); return; }
    if (navigator.vibrate) navigator.vibrate(ok ? 70 : [100, 50, 100]);
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.frequency.value = ok ? 880 : 220;
        gain.gain.value = 0.035;
        osc.connect(gain); gain.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + (ok ? 0.08 : 0.16));
    } catch (_) {}
}

function normalizeUom(value) {
    return String(value || '').trim().toUpperCase();
}

async function initScanner(root) {
    const url = root.dataset.scanUrl;
    const nonSystemUrl = root.dataset.nonSystemUrl;
    const video = document.getElementById('barcode-video');
    const startBtn = document.getElementById('start-camera');
    const form = document.getElementById('manual-scan-form');
    const input = document.getElementById('manual-barcode');
    const nonSystemModal = document.getElementById('non-system-modal');
    const nonSystemForm = document.getElementById('non-system-item-form');
    const nsBarcode = document.getElementById('non-system-barcode');
    const nsName = document.getElementById('non-system-name');
    const nsKnownNote = document.getElementById('non-system-known-note');
    const nsQty = document.getElementById('non-system-qty');
    const nsUom = document.getElementById('non-system-uom');
    const nsRatio = document.getElementById('non-system-ratio');
    const nsSmallest = document.getElementById('non-system-smallest-uom');
    const nsConversion = document.getElementById('non-system-conversion');
    let busy = false;
    let nonSystemOpen = false;
    let lastCode = '';
    let lastAcceptedAt = 0;
    let controls = null;
    // Feedback sukses hanya sesaat; lalu kembali ke kondisi siap scan.
    let successFeedbackTimer = null;

    function showBriefSuccess(title, message) {
        if (successFeedbackTimer !== null) clearTimeout(successFeedbackTimer);
        feedback('success', title, message);
        successFeedbackTimer = setTimeout(() => {
            feedback('idle', 'Siap scan', 'Arahkan kamera ke barcode barang berikutnya.');
            successFeedbackTimer = null;
        }, 1100);
    }
    function updateConversion() {
        if (!nsQty || !nsUom || !nsRatio || !nsSmallest || !nsConversion) return;
        const qty = Number(nsQty.value || 0);
        const uom = normalizeUom(nsUom.value) || 'PCS';
        const smallest = normalizeUom(nsSmallest.value) || 'PCS';
        const sameUom = uom === smallest;
        if (sameUom) {
            nsRatio.value = '1';
            nsRatio.readOnly = true;
        } else {
            nsRatio.readOnly = false;
        }
        const ratio = Number(nsRatio.value || 0);
        const result = Number.isFinite(qty * ratio) ? qty * ratio : 0;
        nsConversion.textContent = `${qty || 0} ${uom} × ${ratio || 0} = ${result.toLocaleString('id-ID', { maximumFractionDigits: 4 })} ${smallest}`;
    }

    function openNonSystem(data) {
        if (!nonSystemModal || !nonSystemForm) return;
        nonSystemOpen = true;
        nonSystemModal.hidden = false;
        document.body.classList.add('modal-open');
        nsBarcode.value = data.barcode || '';
        nsName.value = data.item_name || '';
        nsName.readOnly = Boolean(data.known_non_system);
        nsName.required = !data.known_non_system;
        nsKnownNote.hidden = !data.known_non_system;
        nsQty.value = '1';
        nsUom.value = data.uom_code || 'PCS';
        nsSmallest.value = data.smallest_uom_code || 'PCS';
        nsRatio.value = Number(data.ratio_to_smallest || 1).toString();
        updateConversion();
        feedback('idle', 'Barang Non-System', 'Lengkapi Qty dan UOM fisik sebelum menyimpan.');
        setTimeout(() => (data.known_non_system ? nsQty : nsName)?.focus(), 50);
    }

    function closeNonSystem() {
        if (!nonSystemModal) return;
        nonSystemOpen = false;
        nonSystemModal.hidden = true;
        document.body.classList.remove('modal-open');
        if (window.matchMedia('(pointer:fine)').matches) input?.focus({ preventScroll: true });
    }

    async function submitBarcode(raw) {
        const barcode = String(raw || '').trim();
        if (!barcode || busy || nonSystemOpen || window.__opnameScannerPause) return;
        const now = Date.now();
        // Hindari pengulangan karena kamera masih melihat barcode yang sama.
        if (barcode === lastCode && now - lastAcceptedAt < 2500) return;
        busy = true;
        feedback('idle','Memproses...', barcode);
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf || '',
                },
                body: JSON.stringify({ barcode }),
            });
            const d = await response.json();
            if (!response.ok) {
                const err = new Error(d?.errors?.barcode?.[0] || d?.message || 'Scan gagal diproses.');
                err.payload = d;
                throw err;
            }
            if (d.requires_non_system) {
                openNonSystem(d);
                return;
            }
            lastCode = barcode;
            lastAcceptedAt = Date.now();
            showBriefSuccess(`${d.item_code} · ${d.uom_code}`, d.item_name || 'Scan berhasil');
            signal(true);
        } catch (e) {
            const message = e.payload?.errors?.barcode?.[0] || e.payload?.message || e.message || 'Scan gagal diproses.';
            feedback('error','Scan ditolak',message);
            signal(false);
        } finally {
            busy = false;
            if (input) { input.value=''; if (!nonSystemOpen && window.matchMedia('(pointer:fine)').matches) input.focus({preventScroll:true}); }
        }
    }

    window.__opnameSubmitCameraCode = submitBarcode;
    form?.addEventListener('submit', e => { e.preventDefault(); submitBarcode(input.value); });

    nonSystemForm?.addEventListener('submit', async e => {
        e.preventDefault();
        if (busy) return;
        busy = true;
        const button = nonSystemForm.querySelector('button[type="submit"]');
        const previousLabel = button?.textContent;
        if (button) { button.disabled = true; button.textContent = 'Menyimpan...'; }

        try {
            const payload = {
                barcode: nsBarcode.value,
                item_name: nsName.value,
                qty: nsQty.value,
                uom_code: normalizeUom(nsUom.value),
                smallest_uom_code: normalizeUom(nsSmallest.value),
                ratio_to_smallest: nsRatio.value,
            };
            const response = await fetch(nonSystemUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf || '',
                },
                body: JSON.stringify(payload),
            });
            const d = await response.json();
            if (!response.ok) {
                const errors = d?.errors ? Object.values(d.errors).flat() : [];
                const err = new Error(errors[0] || d?.message || 'Barang Non-System gagal disimpan.');
                err.payload = d;
                throw err;
            }

            lastCode = d.barcode || nsBarcode.value;
            lastAcceptedAt = Date.now();
            closeNonSystem();
            showBriefSuccess(`${d.item_code || d.barcode} · ${d.uom_code}`, `${d.item_name} · Qty ${d.qty}`);
            signal(true);
        } catch (e) {
            const errors = e.payload?.errors ? Object.values(e.payload.errors).flat() : [];
            feedback('error', 'Non-System gagal', errors[0] || e.message || 'Data tidak dapat disimpan.');
            signal(false);
        } finally {
            busy = false;
            if (button) { button.disabled = false; button.textContent = previousLabel || 'Simpan Barang Temuan'; }
        }
    });

    [nsQty, nsUom, nsRatio, nsSmallest].forEach(el => el?.addEventListener('input', updateConversion));
    document.querySelectorAll('[data-non-system-close]').forEach(el => el.addEventListener('click', closeNonSystem));
    nonSystemModal?.addEventListener('click', e => { if (e.target === nonSystemModal) closeNonSystem(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && nonSystemOpen) closeNonSystem(); });

    // Camera initialization with explicit diagnostics and recovery.
    startBtn?.addEventListener('pointerdown', () => { try { const A = window.AudioContext || window.webkitAudioContext; if (A) { const ctx = window.__opnameAudioCtx || (window.__opnameAudioCtx = new A()); ctx.resume().catch(() => {}); } } catch(e) {} });
    startBtn?.addEventListener('click', async () => {
        if (!startBtn || startBtn.disabled) return;
        if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(location.hostname)) {
            feedback('error', 'Kamera diblokir browser', 'Buka aplikasi menggunakan HTTPS. Akses kamera dari HTTP di HP biasanya ditolak.');
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia) {
            feedback('error', 'Kamera tidak tersedia', 'Browser tidak mendukung akses kamera atau izin kamera diblokir. Gunakan Chrome/Safari terbaru.');
            return;
        }
        if (typeof BrowserMultiFormatReader !== 'function') {
            feedback('error', 'Library scanner gagal dimuat', 'Pastikan assets/vendor/zxing-browser.min.js tersedia dan refresh halaman (Ctrl+F5).');
            return;
        }
        startBtn.disabled = true;
        startBtn.textContent = 'Menghubungkan kamera...';
        feedback('idle', 'Meminta izin kamera', 'Izinkan akses kamera ketika browser meminta izin.');
        try {
            const reader = new BrowserMultiFormatReader(undefined, { delayBetweenScanAttempts: 150 });
            // Prefer back camera but do not force 1280x720; some phones reject it.
            try {
                controls = await reader.decodeFromConstraints(
                    { audio: false, video: { facingMode: { ideal: 'environment' } } },
                    video,
                    (result) => { if (result) submitBarcode(result.getText()); }
                );
            } catch (firstError) {
                // Fallback for devices that cannot fulfill facingMode constraints.
                controls = await reader.decodeFromConstraints(
                    { audio: false, video: true },
                    video,
                    (result) => { if (result) submitBarcode(result.getText()); }
                );
            }
            startBtn.style.display = 'none';
            feedback('idle', 'Kamera aktif', 'Arahkan barcode ke area scan.');
        } catch (error) {
            const name = error?.name || '';
            let detail = error?.message || String(error);
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') detail = 'Izin kamera ditolak. Buka pengaturan situs pada browser lalu izinkan Kamera.';
            else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') detail = 'Perangkat kamera tidak ditemukan.';
            else if (name === 'NotReadableError' || name === 'TrackStartError') detail = 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi kamera lain lalu coba lagi.';
            feedback('error', 'Gagal membuka kamera', detail);
            startBtn.disabled = false;
            startBtn.textContent = 'Coba Kamera Lagi';
            startBtn.style.display = '';
            console.error('Legacy checker camera:', error);
        }
    });
    window.addEventListener('beforeunload', () => controls?.stop());
    if (window.matchMedia('(pointer:fine)').matches) setTimeout(() => input?.focus({preventScroll:true}), 250);
}

document.addEventListener('DOMContentLoaded', () => {
    const scanner = document.querySelector('[data-scanner]');
    if (scanner) initScanner(scanner);
});
