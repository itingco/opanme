(() => {
  function init() {
    const root = document.querySelector('[data-scanner]');
    if (!root || root.dataset.v12Ready) return;
    root.dataset.v12Ready = '1';
    const get = id => document.getElementById(id);
    const video = get('barcode-video');
    const manualModal = get('v12-manual-modal');
    const manualForm = get('v12-manual-form');
    const code = get('v12-manual-code');
    const qty = get('v12-manual-qty');
    const preview = get('v12-proof-preview');
    const save = get('v12-manual-save');
    const proofStatus = get('v12-proof-status');
    const aiModal = get('v12-ai-modal');
    const aiValue = get('v12-ai-value');
    const aiStatus = get('v12-ai-status');
    const aiPreview = get('v12-ai-preview');
    const mainStatus = get('v12-camera-status');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let proof = '';
    let submitting = false;
    let reading = false;
    let aiFrame = '';

    function message(text) { if (mainStatus) mainStatus.textContent = text; }
    function open(modal) { modal.hidden = false; document.body.classList.add('modal-open'); window.__opnameScannerPause = true; }
    function close(modal) {
      modal.hidden = true;
      if (manualModal.hidden && aiModal.hidden) { window.__opnameScannerPause = false; document.body.classList.remove('modal-open'); }
    }
    function capture() {
      if (!video || video.readyState < 2 || !video.videoWidth || !video.videoHeight || !video.srcObject || !video.srcObject.getVideoTracks().some(t => t.readyState === 'live')) {
        throw new Error('Kamera belum aktif. Tekan Aktifkan Kamera terlebih dahulu.');
      }
      const maxSide = 1920;
      const scale = Math.min(1, maxSide / Math.max(video.videoWidth, video.videoHeight));
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(video.videoWidth * scale);
      canvas.height = Math.round(video.videoHeight * scale);
      canvas.getContext('2d', {alpha:false}).drawImage(video, 0, 0, canvas.width, canvas.height);
      return {canvas, data: canvas.toDataURL('image/jpeg', .84)};
    }
    function sync() { save.disabled = submitting || !code.value.trim() || !(Number(qty.value) > 0) || !proof; }
    get('v12-manual-open')?.addEventListener('click', () => {
      proof = ''; manualForm.reset(); preview.hidden = true;
      proofStatus.textContent = 'Foto belum diambil.';
      sync(); open(manualModal); code.focus();
    });
    const closeManual = () => close(manualModal);
    get('v12-manual-close')?.addEventListener('click', closeManual);
    get('v12-manual-cancel')?.addEventListener('click', closeManual);
    [code, qty].forEach(el => el.addEventListener('input', sync));
    get('v12-capture-proof')?.addEventListener('click', () => {
      try {
        proof = capture().data;
        preview.src = proof; preview.hidden = false;
        proofStatus.textContent = 'Foto diambil dari kamera aktif.';
        sync();
      } catch(e) { proofStatus.textContent = e.message; }
    });
    async function parseJson(response) {
      const json = await response.json().catch(() => ({}));
      if (!response.ok || json.ok === false) {
        const err = json.errors ? Object.values(json.errors).flat()[0] : json.message;
        throw new Error(err || 'Server gagal memproses permintaan.');
      }
      return json;
    }
    manualForm?.addEventListener('submit', async e => {
      e.preventDefault(); sync(); if (save.disabled || submitting) return;
      submitting = true; sync(); proofStatus.textContent = 'Menyimpan qty dan foto...';
      try {
        const result = await parseJson(await fetch(root.dataset.cameraManualUrl, {
          method: 'POST', credentials:'same-origin',
          headers: {'Accept':'application/json', 'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
          body: JSON.stringify({item_code:code.value.trim(), qty:qty.value, photo:proof})
        }));
        closeManual();
        proofStatus.textContent = 'Foto tersimpan.';
        message('Berhasil: '+result.item_code+' · Qty '+result.qty);
        window.opanmeLoudBeep?.();
        const fb = get('scan-feedback');
        if (fb) { fb.className='scan-feedback success';fb.querySelector('strong').textContent='Input manual berhasil';fb.querySelector('span').textContent=result.item_code+' · Qty '+result.qty; }
      } catch(error) { proofStatus.textContent = 'Gagal: '+error.message; }
      finally { submitting = false; sync(); }
    });
    const closeAi = () => close(aiModal);
    get('v12-ai-close')?.addEventListener('click', closeAi);
    get('v12-ai-cancel')?.addEventListener('click', closeAi);
    get('v12-photo-read')?.addEventListener('click', async () => {
      if (reading) return;
      let frame;
      try { frame = capture(); } catch(e) { message(e.message); return; }
      reading = true;
      aiFrame = frame.data;
      aiPreview.src = aiFrame;
      aiValue.value = '';
      aiStatus.textContent = 'Membaca gambar...';
      open(aiModal);
      try {
        let candidate = '';
        if ('BarcodeDetector' in window) {
          try {
            const detector = new BarcodeDetector();
            const codes = await detector.detect(frame.canvas);
            if (codes.length === 1) candidate = codes[0].rawValue || '';
          } catch(e) { console.debug('BarcodeDetector unavailable',e); }
        }
        if (!candidate) {
          aiStatus.textContent = 'Decoder belum berhasil. Mengirim frame ke Vision AI...';
          const response = await fetch(root.dataset.cameraAiUrl, {
            method:'POST', credentials:'same-origin',
            headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
            body: JSON.stringify({image:aiFrame})
          });
          const result = await parseJson(response);
          candidate = result.value || '';
          aiStatus.textContent = 'Hasil AI: periksa dan konfirmasi sebelum scan.';
        } else { aiStatus.textContent = 'Barcode terdeteksi dari foto. Periksa sebelum scan.'; }
        aiValue.value = candidate;
      } catch(e) {
        aiStatus.textContent = e.message + ' Anda dapat mengetik kode yang terlihat, lalu konfirmasi.';
      } finally { reading = false; }
    });
    get('v12-ai-confirm')?.addEventListener('click', async () => {
      const value = aiValue.value.trim();
      if (!/^[A-Za-z0-9._~\/-]{1,150}$/.test(value)) { aiStatus.textContent='Barcode/QR tidak valid.';return; }
      const btn = get('v12-ai-confirm'); btn.disabled=true;
      closeAi(); message('Memproses barcode: '+value);
      // Reuse standard ScanService path, including NON-SYSTEM fallback and QR KODE~QTY.
      try {
        if (typeof window.__opnameSubmitCameraCode === 'function') {
          await window.__opnameSubmitCameraCode(value);
        } else {
          message('Scanner belum siap. Aktifkan kamera dan refresh halaman.');
        }
      } finally { btn.disabled=false; }
    });
    document.addEventListener('keydown', e => {if(e.key==='Escape') {if(!aiModal.hidden) closeAi(); else if(!manualModal.hidden) closeManual();}});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
