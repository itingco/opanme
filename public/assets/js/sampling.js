(function () {
function initSamplingScanner() {
    const root = document.querySelector('[data-sampling-scanner]');
    if (!root || root.dataset.samplingScannerInitialized === '1') return;
    root.dataset.samplingScannerInitialized = '1';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';
    // Uses the GERAI scan-lookup and scan-confirm endpoints defined in routes/web.php.
    const lookupUrl = root.dataset.lookupUrl;
    const confirmUrl = root.dataset.confirmUrl;
    const barcodeForm = document.getElementById('sample-barcode-form');
    const barcodeInput = document.getElementById('sample-barcode');
    const feedback = document.getElementById('sample-feedback');
    const resultBox = document.getElementById('sample-result');
    const modalPanel = resultBox?.querySelector('.sampling-modal-panel');
    const matchBtn = document.getElementById('sample-match');
    const mismatchBtn = document.getElementById('sample-mismatch');
    const decisionActions = document.getElementById('sample-validation-actions');
    const mismatchForm = document.getElementById('sample-mismatch-form');
    const mismatchSaveBtn = document.getElementById('sample-mismatch-save');
    const mismatchBackBtn = document.getElementById('sample-mismatch-cancel');
    const qtyInput = document.getElementById('sample-physical-qty');
    const differenceLabel = document.getElementById('sample-qty-difference');
    const validationError = document.getElementById('sample-validation-error');
    const mismatchSystemQty = document.getElementById('sample-mismatch-system-qty');
    const transitStockBox = document.getElementById('sample-transit-stock');
    const transitStockList = document.getElementById('sample-transit-stock-list');
    const cameraBtn = document.getElementById('sample-camera');
    const photoBtn = document.getElementById('sample-camera-photo');
    const photoInput = document.getElementById('sample-camera-photo-input');
    const cameraNote = document.getElementById('sample-camera-note');
    const video = document.getElementById('sample-video');
    let active = null;
    let busy = false;
    let validationPending = false;
    let cameraControls = null;
    let scanResumeAt = 0;

    function setFeedback(type, title, message) {
        feedback.className = `scan-feedback sampling-scan-feedback ${type}`;
        feedback.querySelector('strong').textContent = title;
        feedback.querySelector('span').textContent = message;
        const icon = feedback.querySelector('.feedback-icon');
        if (icon) icon.textContent = type === 'success' ? '✓' : type === 'error' ? '!' : '⌁';
    }

    function sound(kind='scan') {
        if (navigator.vibrate) navigator.vibrate(kind === 'error' || kind === 'mismatch' ? [120,60,120] : 90);
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            const ctx = new Ctx();
            const play = (freq, at, duration, gainValue=0.14) => {
                const osc=ctx.createOscillator(), gain=ctx.createGain(); osc.frequency.value=freq; gain.gain.value=gainValue;
                osc.connect(gain); gain.connect(ctx.destination); osc.start(ctx.currentTime+at); osc.stop(ctx.currentTime+at+duration);
            };
            if (kind === 'error' || kind === 'mismatch') { play(260,0,0.12,0.16); play(190,0.16,0.16,0.16); }
            else if (kind === 'match') { play(920,0,0.10,0.16); play(1180,0.11,0.10,0.14); }
            else play(820,0,0.11,0.15);
        } catch (_) {}
    }

    async function post(url, payload) {
        const response = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}, body:JSON.stringify(payload) });
        const data = await response.json().catch(()=>({}));
        if (!response.ok) {
            const errors = data.errors ? Object.values(data.errors).flat() : [];
            throw new Error(errors[0] || data.message || 'Permintaan gagal diproses.');
        }
        return data;
    }

    function setModalError(message='') {
        if (!validationError) return;
        validationError.textContent = message;
        validationError.hidden = !message;
    }

    function setValidationBusy(state) {
        [matchBtn, mismatchBtn, mismatchSaveBtn, mismatchBackBtn].forEach(button => {
            if (button) button.disabled = state;
        });
        if (qtyInput) qtyInput.disabled = state;
    }

    function updateDifference() {
        if (!differenceLabel) return;
        const raw = qtyInput?.value ?? '';
        if (!active || raw === '' || !Number.isFinite(Number(raw))) {
            differenceLabel.textContent = 'Selisih: -';
            differenceLabel.className = 'sample-qty-difference';
            return;
        }

        const difference = Number(raw) - Number(active.system_qty || 0);
        const prefix = difference > 0 ? '+' : '';
        differenceLabel.textContent = `Selisih: ${prefix}${fmt(difference)}`;
        differenceLabel.className = `sample-qty-difference ${difference === 0 ? 'is-zero' : 'has-difference'}`;
    }

    function showDecisionActions() {
        if (decisionActions) decisionActions.hidden = false;
        if (mismatchForm) mismatchForm.hidden = true;
        if (qtyInput) qtyInput.value = '';
        setModalError('');
        updateDifference();
        setTimeout(()=>matchBtn?.focus({preventScroll:true}),50);
    }

    function showMismatchForm() {
        if (decisionActions) decisionActions.hidden = true;
        if (mismatchForm) mismatchForm.hidden = false;
        if (qtyInput) qtyInput.value = '';
        setModalError('');
        updateDifference();
        setTimeout(()=>qtyInput?.focus({preventScroll:true}),50);
    }

    function renderTransitStock(rows) {
        if (!transitStockBox || !transitStockList) return;

        const positiveRows = Array.isArray(rows)
            ? rows.filter(row => Number(row?.smallest_on_hand || 0) > 0)
            : [];

        transitStockList.innerHTML = '';
        if (!positiveRows.length) {
            transitStockBox.hidden = true;
            return;
        }

        positiveRows.forEach((row) => {
            const line = document.createElement('div');
            const code = row.warehouse_code || `ID ${row.warehouse_id}`;
            const name = row.warehouse_name || 'In Transit';
            line.textContent = `${code} · ${name}: ${fmt(row.smallest_on_hand)}`;
            transitStockList.appendChild(line);
        });
        transitStockBox.hidden = false;
    }

    function openValidation(data) {
        active = data;
        validationPending = true;
        document.getElementById('sample-result-code').textContent=data.item_code;
        document.getElementById('sample-result-name').textContent=data.item_name;
        document.getElementById('sample-result-barcode').textContent=`Barcode ${data.barcode} · ${data.location}`;
        document.getElementById('sample-system-qty').textContent=fmt(data.system_qty);
        document.getElementById('sample-uom').textContent=`UOM scan: ${data.uom_code}`;
        if (mismatchSystemQty) mismatchSystemQty.textContent = fmt(data.system_qty);
        renderTransitStock(data.transit_stock);
        showDecisionActions();
        resultBox.hidden = false;
        document.body.classList.add('sampling-validation-open');
        barcodeInput.disabled = true;
        sound('scan');
        setFeedback('success','Item ditemukan','Selesaikan validasi stok fisik pada modal.');
        setTimeout(()=>matchBtn?.focus({preventScroll:true}) || modalPanel?.focus({preventScroll:true}),80);
    }

    function resetScan() {
        active = null;
        validationPending = false;
        scanResumeAt = Date.now() + 700;
        resultBox.hidden = true;
        mismatchForm.hidden = true;
        if (decisionActions) decisionActions.hidden = false;
        qtyInput.value = '';
        barcodeInput.value = '';
        barcodeInput.disabled = false;
        document.body.classList.remove('sampling-validation-open');
        setModalError('');
        setValidationBusy(false);
        renderTransitStock([]);
        updateDifference();
        setTimeout(()=>barcodeInput.focus({preventScroll:true}),50);
        setTimeout(()=>setFeedback('idle','Siap scan','Arahkan kamera atau scan barcode berikutnya.'),900);
    }

    async function lookup(raw) {
        const barcode=String(raw||'').trim();
        if(!barcode || busy || validationPending || Date.now() < scanResumeAt) return;
        busy=true;
        setFeedback('idle','Membaca barcode...',barcode);
        try {
            const d=await post(lookupUrl,{barcode});
            openValidation(d);
        } catch(e) {
            active=null;
            validationPending=false;
            resultBox.hidden=true;
            document.body.classList.remove('sampling-validation-open');
            barcodeInput.disabled=false;
            renderTransitStock([]);
            sound('error');
            setFeedback('error','Scan ditolak',e.message);
        }
        finally {
            busy=false;
            barcodeInput.value='';
        }
    }

    async function confirm(result, physical_qty=null) {
        if(!active || busy) return;

        if (result === 'MISMATCH' && (physical_qty === null || physical_qty === '' || !Number.isFinite(Number(physical_qty)) || Number(physical_qty) < 0)) {
            setModalError('Qty fisik wajib diisi dengan angka 0 atau lebih.');
            qtyInput?.focus({preventScroll:true});
            return;
        }

        busy=true;
        setValidationBusy(true);
        setModalError('');
        try {
            const d=await post(confirmUrl,{token:active.token,result,physical_qty});
            sound(result==='MATCH'?'match':'mismatch');
            setFeedback('success',result==='MATCH'?'Stok Cocok':'Selisih Tersimpan',`${d.item_code} · fisik ${d.physical_qty}`);
            document.getElementById('sample-count').textContent=Number(d.count).toLocaleString('id-ID');
            document.getElementById('sample-empty-row')?.remove();
            const tr=document.createElement('tr');
            tr.innerHTML=`<td data-label="Waktu">${d.scanned_at}</td><td data-label="Lokasi / Rak">${escapeHtml(d.location)}</td><td data-label="Item"><strong>${escapeHtml(d.item_code)}</strong><br><small>${escapeHtml(d.item_name)}</small></td><td data-label="Sistem" class="num">${fmt(d.system_qty)}</td><td data-label="Fisik" class="num">${fmt(d.physical_qty)}</td><td data-label="Hasil"><span class="sample-status ${d.result.toLowerCase()}">${d.result==='MATCH'?'Cocok':'Tidak Cocok'}</span></td>`;
            document.getElementById('sample-history-body').prepend(tr);
            resetScan();
        } catch(e) {
            sound('error');
            setModalError(e.message);
            setFeedback('error','Gagal menyimpan',e.message);
        }
        finally {
            busy=false;
            setValidationBusy(false);
        }
    }

    function fmt(v){return Number(v).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:4});}
    function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;}

    barcodeForm?.addEventListener('submit',e=>{e.preventDefault();lookup(barcodeInput.value)});
    matchBtn?.addEventListener('click',()=>confirm('MATCH'));
    mismatchBtn?.addEventListener('click',showMismatchForm);
    mismatchBackBtn?.addEventListener('click',showDecisionActions);
    qtyInput?.addEventListener('input',()=>{setModalError('');updateDifference();});
    mismatchForm?.addEventListener('submit',e=>{e.preventDefault();confirm('MISMATCH',qtyInput.value)});

    function getBarcodeReaderClass() {
        return window.ZXingBrowser && window.ZXingBrowser.BrowserMultiFormatReader
            ? window.ZXingBrowser.BrowserMultiFormatReader
            : null;
    }

    function cameraErrorMessage(error) {
        const name = error?.name || '';
        if (!window.isSecureContext && !['localhost','127.0.0.1','::1'].includes(location.hostname)) {
            return 'Kamera live diblokir browser karena halaman dibuka melalui HTTP. Gunakan HTTPS, atau tombol Ambil Foto Barcode.';
        }
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') return 'Izin kamera ditolak. Aktifkan permission Camera untuk browser lalu coba lagi.';
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') return 'Kamera tidak ditemukan pada perangkat ini.';
        if (name === 'NotReadableError' || name === 'TrackStartError') return 'Kamera sedang dipakai aplikasi lain atau tidak dapat dibuka.';
        if (name === 'OverconstrainedError') return 'Kamera tersedia, tetapi konfigurasi kamera belakang tidak didukung.';
        if (name === 'SecurityError') return 'Browser memblokir akses kamera untuk halaman ini. Gunakan HTTPS.';
        return error?.message || 'Kamera tidak dapat dibuka. Gunakan Ambil Foto Barcode atau input manual.';
    }

    function showCameraNote(message, kind='info') {
        if (!cameraNote) return;
        cameraNote.textContent = message;
        cameraNote.className = `sampling-camera-note ${kind}`;
        cameraNote.hidden = !message;
    }

    async function startLiveCamera() {
        if (!cameraBtn) return;

        const localHost = ['localhost','127.0.0.1','::1'].includes(location.hostname);
        if (!window.isSecureContext && !localHost) {
            showCameraNote('Live camera diblokir browser karena halaman masih HTTP. Kamera foto akan dibuka sebagai fallback. Untuk scanner live, gunakan HTTPS.', 'error');
            setFeedback('idle','Mode kamera foto','Live camera membutuhkan HTTPS. Kamera foto sedang dibuka.');
            if (photoInput) photoInput.click();
            return;
        }

        cameraBtn.disabled = true;
        cameraBtn.textContent = 'Membuka kamera...';
        showCameraNote('Meminta akses kamera belakang...', 'info');

        const ReaderClass = getBarcodeReaderClass();
        if (!ReaderClass) {
            cameraBtn.disabled = false;
            cameraBtn.textContent = 'Coba Kamera Lagi';
            showCameraNote('Library barcode scanner tidak termuat. Refresh halaman (Ctrl+F5) lalu coba lagi.', 'error');
            setFeedback('error','Scanner tidak termuat','Refresh halaman lalu coba kembali.');
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            cameraBtn.disabled = false;
            cameraBtn.textContent = 'Coba Kamera Lagi';
            const message = 'Browser ini tidak menyediakan akses kamera live. Kamera foto akan dibuka sebagai fallback.';
            showCameraNote(message, 'error');
            setFeedback('idle','Mode kamera foto',message);
            if (photoInput) photoInput.click();
            return;
        }

        try {
            cameraControls?.stop?.();
            const reader = new ReaderClass(undefined, { delayBetweenScanAttempts: 180 });
            cameraControls = await reader.decodeFromConstraints(
                { audio:false, video:{ facingMode:{ ideal:'environment' }, width:{ ideal:1280 }, height:{ ideal:720 } } },
                video,
                result => { if (result && !validationPending) lookup(result.getText()); }
            );
            cameraBtn.hidden = true;
            cameraBtn.style.display = 'none';
            showCameraNote('Kamera aktif. Arahkan barcode ke area kotak scan.', 'success');
            setFeedback('idle','Kamera aktif','Arahkan barcode ke kamera.');
        } catch(error) {
            cameraBtn.disabled = false;
            cameraBtn.textContent = 'Coba Kamera Lagi';
            const message = cameraErrorMessage(error);
            showCameraNote(message, 'error');
            setFeedback('error','Kamera tidak tersedia',message);
        }
    }

    cameraBtn?.addEventListener('click', startLiveCamera);

    photoBtn?.addEventListener('click', () => photoInput?.click());
    photoInput?.addEventListener('change', async () => {
        const file = photoInput.files?.[0];
        if (!file || busy || validationPending) return;
        const ReaderClass = getBarcodeReaderClass();
        if (!ReaderClass) {
            showCameraNote('Library barcode scanner tidak termuat. Refresh halaman lalu coba lagi.', 'error');
            photoInput.value = '';
            return;
        }
        photoBtn.disabled = true;
        const oldText = photoBtn.textContent;
        photoBtn.textContent = 'Membaca foto...';
        showCameraNote('Membaca barcode dari foto...', 'info');
        const objectUrl = URL.createObjectURL(file);
        try {
            const reader = new ReaderClass();
            const result = await reader.decodeFromImageUrl(objectUrl);
            const value = result?.getText?.();
            if (!value) throw new Error('Barcode tidak terbaca dari foto.');
            showCameraNote(`Barcode ditemukan: ${value}`, 'success');
            await lookup(value);
        } catch(error) {
            const message = error?.message?.includes('No MultiFormat Readers') || error?.name === 'NotFoundException'
                ? 'Barcode tidak terbaca. Ambil foto lebih dekat, terang, dan fokus pada barcode.'
                : (error?.message || 'Barcode tidak terbaca dari foto.');
            showCameraNote(message, 'error');
            setFeedback('error','Barcode foto tidak terbaca',message);
        } finally {
            URL.revokeObjectURL(objectUrl);
            photoInput.value = '';
            photoBtn.disabled = false;
            photoBtn.textContent = oldText || 'Ambil Foto Barcode';
        }
    });

    window.OpnameSamplingScanner = {
        startCamera: startLiveCamera,
        openPhoto: function () { if (photoInput) photoInput.click(); },
        isReady: true
    };

    if (cameraBtn) cameraBtn.dataset.samplingBound = '1';
    if (photoBtn) photoBtn.dataset.samplingBound = '1';

    showCameraNote('Scanner siap. Tekan Aktifkan Kamera. Jika browser memblokir kamera live, gunakan Ambil Foto Barcode.', 'info');

    const historyDetails = document.querySelector('.sampling-history-details');
    const mobileHistory = window.matchMedia('(max-width: 780px)');
    const syncHistoryDetails = () => {
        if (!historyDetails) return;
        if (mobileHistory.matches) historyDetails.removeAttribute('open');
        else historyDetails.setAttribute('open','');
    };
    syncHistoryDetails();
    mobileHistory.addEventListener?.('change', syncHistoryDetails);

    window.addEventListener('beforeunload',()=>cameraControls?.stop());
    setTimeout(()=>barcodeInput?.focus({preventScroll:true}),200);
    root.dataset.samplingScannerReady = '1';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSamplingScanner, { once: true });
} else {
    initSamplingScanner();
}

window.addEventListener('load', initSamplingScanner, { once: true });
})();
