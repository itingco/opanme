/* Checker Cycle Legacy - optional photo barcode fallback. No DB writes until user confirms. */
(() => {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-scanner]');
    if (!root || !document.querySelector('.camera-box')) return;
    const camera = document.querySelector('.camera-box');
    const video = document.getElementById('barcode-video');
    const field = document.getElementById('manual-barcode');
    const form = document.getElementById('manual-scan-form');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    // Tombol sudah dirender di Blade agar selalu terlihat sebelum JS dimuat.
    if (!document.getElementById('legacy-photo-capture') || !document.getElementById('legacy-photo-upload')) return;
    const popup = document.createElement('div');
    popup.className = 'legacy-photo-dialog';
    popup.hidden = true;
    popup.innerHTML = '<section class="legacy-photo-card" role="dialog" aria-modal="true" aria-label="Baca Barcode dari Foto"><h3>Periksa Hasil Baca Foto</h3><img id="legacy-photo-preview" alt="Foto barcode"><div id="legacy-photo-status" class="legacy-photo-status" role="status" aria-live="polite">Memproses foto...</div><label for="legacy-photo-code">Hasil barcode (boleh dikoreksi)</label><input id="legacy-photo-code" type="text" maxlength="150" autocomplete="off" placeholder="Kode barang / barcode / KODE~QTY"><div class="legacy-photo-buttons"><button type="button" class="btn" id="legacy-photo-again">Foto Ulang</button><button type="button" class="btn primary" id="legacy-photo-save">Proses Scan</button><button type="button" class="btn" id="legacy-photo-close">Batal</button></div><small>AI/OCR dapat keliru. Cocokkan teks dengan label sebelum Proses Scan. Foto tidak otomatis disimpan sebagai transaksi.</small></section>';
    document.body.appendChild(popup);
    const qs = id => document.getElementById(id);
    const status = qs('legacy-photo-status');
    const codeField = qs('legacy-photo-code');
    const preview = qs('legacy-photo-preview');
    const chooser = qs('legacy-photo-file');
    let latestFile = null;
    let generation = 0;
    let tempUrl = null;
    let processing = false;

    const setStatus = (msg) => { status.textContent = msg; };
    const close = () => {
      generation++;
      popup.hidden = true;
      processing = false;
      if (tempUrl) URL.revokeObjectURL(tempUrl);
      tempUrl = null;
      latestFile = null;
      chooser.value = '';
    };
    const canvasFile = async () => {
      if (!video || video.readyState < 2 || video.videoWidth === 0) return null;
      const canvas = document.createElement('canvas');
      const maxSide = 1800;
      const scale = Math.min(1, maxSide / Math.max(video.videoWidth, video.videoHeight));
      canvas.width = Math.round(video.videoWidth * scale);
      canvas.height = Math.round(video.videoHeight * scale);
      canvas.getContext('2d', { willReadFrequently: true }).drawImage(video, 0, 0, canvas.width, canvas.height);
      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', .9));
      return blob ? new File([blob], 'barcode.jpg', { type: 'image/jpeg' }) : null;
    };
    const decodeNative = async (img) => {
      if (typeof window.BarcodeDetector !== 'function') return '';
      try {
        const formats = typeof BarcodeDetector.getSupportedFormats === 'function' ? await BarcodeDetector.getSupportedFormats() : [];
        const supported = ['code_128','code_39','code_93','ean_13','ean_8','upc_a','upc_e','itf','qr_code','data_matrix'].filter(x=>formats.includes(x));
        if (!supported.length) return '';
        const d = new BarcodeDetector({ formats: supported });
        const found = await d.detect(img);
        return found?.[0]?.rawValue?.trim() || '';
      } catch (_) { return ''; }
    };
    const decodeZXing = async img => {
      const Reader = window.ZXingBrowser?.BrowserMultiFormatReader;
      if (typeof Reader !== 'function') return '';
      try {
        const reader = new Reader();
        const result = await reader.decodeFromImageElement(img);
        return String(result?.getText?.() || '').trim();
      } catch (_) { return ''; }
    };
    const decodeVision = async (file) => {
      const endpoint = window.LEGACY_PHOTO_AI_URL;
      if (!endpoint) throw new Error('Endpoint AI belum dikonfigurasi.');
      const fd = new FormData();
      fd.append('photo', file, 'barcode.jpg');
      const response = await fetch(endpoint, { method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf}, body:fd });
      const payload = await response.json().catch(()=>({}));
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'OCR/AI tidak berhasil membaca barcode.');
      return { code: String(payload.code || ''), confidence: payload.confidence || 'low' };
    };
    const openFile = async (file) => {
      if (!file || processing) return;
      if (!file.type.startsWith('image/')) { alert('Pilih file gambar.'); return; }
      if (file.size > 3 * 1024 * 1024) { alert('Foto maksimal 3 MB.'); return; }
      processing = true;
      const job = ++generation;
      latestFile = file;
      popup.hidden = false;
      codeField.value = '';
      if (tempUrl) URL.revokeObjectURL(tempUrl);
      tempUrl = URL.createObjectURL(file);
      preview.src = tempUrl;
      setStatus('Menganalisis foto dengan pembaca barcode lokal...');
      try {
        await new Promise((resolve,reject)=>{ if (preview.complete && preview.naturalWidth) return resolve(); preview.onload=resolve; preview.onerror=()=>reject(new Error('Foto gagal dibuka.')); });
        let code = await decodeNative(preview);
        let source = 'BarcodeDetector';
        if (!code) { code = await decodeZXing(preview); source = 'ZXing'; }
        if (!code) {
          setStatus('Barcode belum terbaca secara lokal. Mencoba OCR/AI...');
          const ai = await decodeVision(file);
          code = ai.code;
          source = `AI (keyakinan ${ai.confidence})`;
        }
        if (job !== generation) return;
        codeField.value = code;
        setStatus(`Hasil ditemukan menggunakan ${source}. Pastikan teks cocok dengan label sebelum diproses.`);
        codeField.focus();
      } catch (error) {
        if (job === generation) { setStatus((error?.message || 'Foto tidak terbaca.') + ' Anda tetap bisa mengetik kode secara manual.'); codeField.focus(); }
      } finally { if (job === generation) processing = false; }
    };
    qs('legacy-photo-capture').addEventListener('click', async () => {
      const file = await canvasFile();
      if (file) return openFile(file);
      // If video is not ready, use phone's native photo capture as fallback.
      chooser.click();
    });
    qs('legacy-photo-upload').addEventListener('click', () => chooser.click());
    chooser.addEventListener('change', () => { if (chooser.files?.[0]) openFile(chooser.files[0]); });
    qs('legacy-photo-again').addEventListener('click', () => { close(); chooser.click(); });
    qs('legacy-photo-close').addEventListener('click', close);
    popup.addEventListener('click', e => { if (e.target === popup) close(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !popup.hidden) close(); });
    qs('legacy-photo-save').addEventListener('click', () => {
      const code = codeField.value.trim();
      if (!code || code.length > 150 || /\s/.test(code)) return setStatus('Kode harus diisi tanpa spasi, maksimal 150 karakter.');
      close();
      // Use the current trusted existing scanner flow (including non-system, ratio, and cycle validation).
      field.value = code;
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.dispatchEvent(new Event('submit', { bubbles:true, cancelable:true }));
    });
  });
})();
