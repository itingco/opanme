(() => {
  function init() {
    const root = document.getElementById('v15-checker');
    if (!root || root.dataset.initialized === 'yes') return;
    root.dataset.initialized = 'yes';
    const $ = id => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const modal = $('v15-manual-modal'), nsModal = $('v15-non-system-modal');
    const code = $('v15-code'), check = $('v15-check'), search = $('v15-lookup-form');
    const qty = $('v15-qty'), save = $('v15-save'), video = $('v15-video');
    const captureBtn = $('v15-capture'), preview = $('v15-preview'), retake = $('v15-retake');
    const start = $('v15-start-camera'), hint = $('v15-video-hint');
    const cameraStatus = $('v15-camera-status'), saveStatus = $('v15-save-status');
    const aiStatus = $('v15-ai-status'), aiInput = $('v15-ai-value'), aiConfirm = $('v15-ai-confirm');
    const lookupResult = $('v15-lookup-result');
    const fb = $('v15-feedback');
    let selected = null, photo = '', stream = null, busy = false, querying = false, generation = 0;
    let audioContext = null;
    const status = (target, text) => { if (target) target.textContent = text; };
    const flash = (title, detail, success = true) => {
      if (!fb) return;
      fb.className = 'scan-feedback ' + (success ? 'success' : 'error');
      fb.querySelector('strong').textContent = title;
      fb.querySelector('span').textContent = detail;
    };
    async function dataFrom(response) {
      let payload = {};
      try { payload = await response.json(); } catch (_) {}
      if (!response.ok || payload.ok === false) {
        const errors = payload.errors ? Object.values(payload.errors).flat() : [];
        throw new Error(errors[0] || payload.message || ('HTTP ' + response.status));
      }
      return payload;
    }
    function beep() {
      try {
        audioContext = audioContext || new (window.AudioContext || window.webkitAudioContext)();
        if (audioContext.state === 'suspended') audioContext.resume();
        [900, 1250].forEach((freq, index) => {
          const osc = audioContext.createOscillator(), gain = audioContext.createGain();
          const begin = audioContext.currentTime + index * .11;
          osc.type = 'square'; osc.frequency.value = freq;
          gain.gain.setValueAtTime(.0001, begin);
          gain.gain.exponentialRampToValueAtTime(.16, begin + .012);
          gain.gain.exponentialRampToValueAtTime(.0001, begin + .105);
          osc.connect(gain); gain.connect(audioContext.destination);
          osc.start(begin); osc.stop(begin + .12);
        });
      } catch (e) { console.debug('Audio blocked', e); }
      if (navigator.vibrate) navigator.vibrate([90, 40, 90]);
    }
    function sync() { save.disabled = busy || !selected || !photo || !Number.isFinite(Number(qty.value)) || Number(qty.value) <= 0 || Number(qty.value) > 999999999; }
    function stopCamera() {
      if (stream) stream.getTracks().forEach(track => track.stop());
      stream = null; video.srcObject = null;
      hint.textContent = 'Kamera belum aktif';
      start.disabled = false; start.textContent = 'Aktifkan Kamera'; captureBtn.disabled = true;
    }
    function closeManual() {
      modal.hidden = true; document.body.classList.remove('modal-open');
      stopCamera(); selected = null; photo = ''; sync(); code.focus();
    }
    function snapshot() {
      if (!stream || video.readyState < 2 || !video.videoWidth) throw new Error('Aktifkan kamera terlebih dahulu.');
      const canvas = document.createElement('canvas');
      const scale = Math.min(1, 1920 / Math.max(video.videoWidth, video.videoHeight));
      canvas.width = Math.round(video.videoWidth * scale); canvas.height = Math.round(video.videoHeight * scale);
      canvas.getContext('2d', { alpha: false }).drawImage(video, 0, 0, canvas.width, canvas.height);
      return { canvas, data: canvas.toDataURL('image/jpeg', .82) };
    }
    search?.addEventListener('submit', async e => {
      e.preventDefault();
      if (querying || busy) return;
      const value = code.value.trim();
      if (!value) return;
      querying = true; check.disabled = true;
      const seq = ++generation;
      status(lookupResult, 'Memeriksa kode barang pada warehouse dan cycle aktif...');
      try {
        const url = new URL(root.dataset.itemLookupUrl, location.href);
        url.searchParams.set('item_code', value);
        const item = await dataFrom(await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }));
        if (seq !== generation) return;
        selected = item; photo = ''; qty.value = ''; preview.hidden = true; preview.removeAttribute('src');
        aiInput.value = ''; aiConfirm.disabled = true; status(aiStatus, '');
        status($('v15-item-code'), item.item_code);
        status($('v15-item-name'), item.item_name || 'Nama item tidak tersedia');
        status($('v15-item-database'), 'Database: ' + item.source_database);
        status(saveStatus, 'Qty dan foto wajib sebelum menyimpan.');
        status(cameraStatus, 'Aktifkan kamera untuk mengambil bukti fisik barang.');
        status(lookupResult, 'Kode ditemukan: ' + item.item_code);
        sync(); modal.hidden = false; document.body.classList.add('modal-open'); qty.focus();
      } catch (error) { status(lookupResult, error.message); flash('Kode tidak ditemukan', error.message, false); }
      finally { querying = false; check.disabled = false; }
    });
    $('v15-close')?.addEventListener('click', closeManual);
    $('v15-cancel')?.addEventListener('click', closeManual);
    qty?.addEventListener('input', sync);
    start?.addEventListener('click', async () => {
      if (stream) return;
      if (!navigator.mediaDevices?.getUserMedia || !window.isSecureContext) {
        status(cameraStatus, 'Kamera memerlukan HTTPS atau localhost dan browser yang mendukung getUserMedia.'); return;
      }
      start.disabled = true; status(cameraStatus, 'Meminta izin kamera...');
      try {
        try { stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } } }); }
        catch (_) { stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: true }); }
        video.srcObject = stream; await video.play();
        const track = stream.getVideoTracks()[0];
        try { await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }); } catch (_) {}
        hint.textContent = 'Kamera aktif';
        start.textContent = 'Kamera Aktif'; captureBtn.disabled = false;
        status(cameraStatus, 'Arahkan kamera ke barang, lalu tekan Ambil Foto.');
      } catch (error) {
        start.disabled = false;
        let msg = error.message || 'Tidak dapat membuka kamera.';
        if (error.name === 'NotAllowedError') msg = 'Izin kamera ditolak. Izinkan kamera melalui pengaturan browser.';
        if (error.name === 'NotReadableError') msg = 'Kamera sedang digunakan aplikasi lain.';
        status(cameraStatus, msg);
      }
    });
    captureBtn?.addEventListener('click', () => {
      try {
        photo = snapshot().data;
        preview.src = photo; preview.hidden = false; retake.hidden = false;
        status(cameraStatus, 'Foto berhasil diambil dari kamera aktif.'); sync();
      } catch (e) { status(cameraStatus, e.message); }
    });
    retake?.addEventListener('click', () => { photo = ''; preview.hidden = true; retake.hidden = true; status(cameraStatus, 'Foto dihapus. Silakan ambil foto ulang.'); sync(); });
    $('v15-save-form')?.addEventListener('submit', async e => {
      e.preventDefault(); sync(); if (save.disabled || busy || !selected) return;
      busy = true; sync(); status(saveStatus, 'Menyimpan qty dan bukti foto...');
      try {
        const result = await dataFrom(await fetch(root.dataset.cameraManualUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ item_code: selected.item_code, qty: qty.value, photo }),
        }));
        closeManual(); code.value = ''; status(lookupResult, '');
        flash('Tersimpan', result.item_code + ' — Qty ' + result.qty); beep();
      } catch (error) { status(saveStatus, 'Gagal: ' + error.message); }
      finally { busy = false; sync(); }
    });
    $('v15-ai-read')?.addEventListener('click', async () => {
      let frame;
      try { frame = snapshot(); } catch (e) { status(aiStatus, e.message); return; }
      status(aiStatus, 'Membaca frame kamera...'); aiInput.value = ''; aiConfirm.disabled = true;
      try {
        let value = '';
        if ('BarcodeDetector' in window) {
          try {
            const results = await new BarcodeDetector().detect(frame.canvas);
            if (results.length === 1) value = results[0].rawValue || '';
          } catch (_) {}
        }
        if (!value) {
          status(aiStatus, 'Decoder lokal belum berhasil; mencoba Vision AI...');
          const result = await dataFrom(await fetch(root.dataset.cameraAiUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ image: frame.data }),
          }));
          value = result.value || '';
        }
        aiInput.value = value; aiConfirm.disabled = !value;
        status(aiStatus, value ? 'Periksa hasil baca. Konfirmasi bila sudah benar.' : 'Belum ada barcode yang terbaca.');
      } catch (e) { status(aiStatus, e.message); }
    });
    aiInput?.addEventListener('input', () => { aiConfirm.disabled = !aiInput.value.trim(); });
    aiConfirm?.addEventListener('click', async () => {
      const value = aiInput.value.trim();
      if (!/^[A-Za-z0-9._~\/-]{1,150}$/.test(value)) { status(aiStatus, 'Kode tidak valid.'); return; }
      aiConfirm.disabled = true; status(aiStatus, 'Mengirim hasil baca ke proses scan...');
      try {
        const result = await dataFrom(await fetch(root.dataset.scanUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ barcode: value }),
        }));
        if (result.requires_non_system) {
          closeManual(); nsModal.hidden = false; document.body.classList.add('modal-open');
          $('v15-ns-barcode').value = value;
          $('v15-ns-name').value = result.item_name || '';
          $('v15-ns-uom').value = result.uom_code || 'PCS';
          $('v15-ns-smallest').value = result.smallest_uom_code || 'PCS';
          $('v15-ns-ratio').value = result.ratio_to_smallest || '1';
          status($('v15-ns-status'), 'Barang tidak ditemukan di master. Lengkapi form untuk mencatat sebagai Non-System.');
        } else {
          closeManual(); code.value = ''; flash('Scan berhasil', (result.item_code || value) + ' · ' + (result.item_name || '')); beep();
        }
      } catch (e) { status(aiStatus, 'Gagal: ' + e.message); }
      finally { aiConfirm.disabled = false; }
    });
    function closeNs() { nsModal.hidden = true; document.body.classList.remove('modal-open'); }
    $('v15-ns-close')?.addEventListener('click', closeNs);
    $('v15-ns-cancel')?.addEventListener('click', closeNs);
    $('v15-ns-form')?.addEventListener('submit', async e => {
      e.preventDefault(); const form = e.currentTarget;
      const button = form.querySelector('button[type=submit]'); button.disabled = true;
      status($('v15-ns-status'), 'Menyimpan barang Non-System...');
      try {
        const result = await dataFrom(await fetch(root.dataset.nonSystemUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({
            barcode: $('v15-ns-barcode').value, item_name: $('v15-ns-name').value,
            qty: $('v15-ns-qty').value, uom_code: $('v15-ns-uom').value,
            smallest_uom_code: $('v15-ns-smallest').value, ratio_to_smallest: $('v15-ns-ratio').value,
          }),
        }));
        closeNs(); flash('Non-System tersimpan', result.item_code + ' · ' + result.item_name); beep();
      } catch (err) { status($('v15-ns-status'), err.message); }
      finally { button.disabled = false; }
    });
    document.addEventListener('keydown', e => {
      if (e.key !== 'Escape') return;
      if (!nsModal.hidden) closeNs(); else if (!modal.hidden && !busy) closeManual();
    });
    window.addEventListener('beforeunload', stopCamera);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
