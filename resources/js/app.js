import { BrowserMultiFormatReader } from '@zxing/browser';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

function feedback(type, title, message) {
    const box = document.getElementById('scan-feedback');
    if (!box) return;
    box.className = `scan-feedback ${type}`;
    box.querySelector('.feedback-icon').textContent = type === 'success' ? '✓' : type === 'error' ? '!' : '⌁';
    box.querySelector('strong').textContent = title;
    box.querySelector('span').textContent = message;
}

function signal(ok) {
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

async function initScanner(root) {
    const url = root.dataset.scanUrl;
    const video = document.getElementById('barcode-video');
    const startBtn = document.getElementById('start-camera');
    const form = document.getElementById('manual-scan-form');
    const input = document.getElementById('manual-barcode');
    let busy = false;
    let lastCode = '';
    let lastAcceptedAt = 0;
    let controls = null;

    async function submitBarcode(raw) {
        const barcode = String(raw || '').trim();
        if (!barcode || busy) return;
        const now = Date.now();
        if (barcode === lastCode && now - lastAcceptedAt < 1200) return;
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
            lastCode = barcode;
            lastAcceptedAt = Date.now();
            feedback('success', `${d.item_code} · ${d.uom_code}`, d.item_name || 'Scan berhasil');
            signal(true);
        } catch (e) {
            const message = e.payload?.errors?.barcode?.[0] || e.payload?.message || e.message || 'Scan gagal diproses.';
            feedback('error','Scan ditolak',message);
            signal(false);
        } finally {
            busy = false;
            if (input) { input.value=''; if (window.matchMedia('(pointer:fine)').matches) input.focus({preventScroll:true}); }
        }
    }

    form?.addEventListener('submit', e => { e.preventDefault(); submitBarcode(input.value); });

    startBtn?.addEventListener('click', async () => {
        startBtn.disabled = true;
        startBtn.textContent = 'Membuka kamera...';
        try {
            const reader = new BrowserMultiFormatReader(undefined, { delayBetweenScanAttempts: 120 });
            controls = await reader.decodeFromConstraints(
                { audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } } },
                video,
                result => { if (result) submitBarcode(result.getText()); }
            );
            startBtn.style.display = 'none';
            feedback('idle','Kamera aktif','Arahkan barcode ke area scan.');
        } catch (e) {
            startBtn.disabled = false;
            startBtn.textContent = 'Coba Kamera Lagi';
            feedback('error','Kamera tidak tersedia','Gunakan Chrome/HTTPS atau scanner/input barcode di bawah.');
        }
    });

    window.addEventListener('beforeunload', () => controls?.stop());
    if (window.matchMedia('(pointer:fine)').matches) setTimeout(() => input?.focus({preventScroll:true}), 250);
}

document.addEventListener('DOMContentLoaded', () => {
    const scanner = document.querySelector('[data-scanner]');
    if (scanner) initScanner(scanner);
});
