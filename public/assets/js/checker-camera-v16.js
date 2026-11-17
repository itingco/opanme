(() => {
  function init() {
    const root = document.getElementById('v15-checker');
    if (!root || !document.getElementById('v16-scanner-video')) return;
    const $ = id => document.getElementById(id);
    const video = $('v16-scanner-video'), start = $('v16-camera-start'), stop = $('v16-camera-stop');
    const read = $('v16-camera-read'), status = $('v16-camera-status'), indicator = $('v16-camera-indicator');
    const panel = $('v16-confirm'), resultCode = $('v16-ai-code'), confirm = $('v16-confirm-scan');
    const manualModal = $('v15-manual-modal'), nsModal = $('v15-non-system-modal');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let controls = null, media = null, barcodeTimer = null, reader = null;
    let running = false, opening = false, busy = false, decoding = false, autoResume = false;
    let recentValue = '', recentAt = 0, generation = 0, successTimer = null, audio = null;
    const setStatus = message => { status.textContent = message; };
    const setFeedback = (ok, title, detail) => {
      const box = $('v15-feedback');
      if (!box) return;
      if (successTimer) clearTimeout(successTimer);
      box.className = 'scan-feedback ' + (ok ? 'success' : 'error');
      box.querySelector('strong').textContent = title;
      box.querySelector('span').textContent = detail;
      if (ok) successTimer = setTimeout(() => {
        box.className = 'scan-feedback idle';
        box.querySelector('strong').textContent = 'Siap scan berikutnya';
        box.querySelector('span').textContent = 'Arahkan barcode berikutnya, atau masukkan kode barang tanpa barcode.';
      }, 1300);
    };
    function beep() {
      try {
        const Context = window.AudioContext || window.webkitAudioContext;
        if (!Context) return;
        audio = audio || new Context();
        audio.resume?.().catch(() => {});
        [930, 1350].forEach((hz, i) => {
          const oscillator = audio.createOscillator(), gain = audio.createGain();
          const when = audio.currentTime + i * .13;
          oscillator.type = 'square'; oscillator.frequency.value = hz;
          gain.gain.setValueAtTime(.0001, when);
          gain.gain.exponentialRampToValueAtTime(.20, when + .012);
          gain.gain.exponentialRampToValueAtTime(.0001, when + .12);
          oscillator.connect(gain).connect(audio.destination);
          oscillator.start(when); oscillator.stop(when + .13);
        });
      } catch (e) { console.debug('beep:', e); }
      navigator.vibrate?.([90, 30, 90]);
    }
    async function parseResponse(response) {
      let data = {};
      try { data = await response.json(); } catch (_) { throw new Error('Server tidak mengembalikan JSON (HTTP ' + response.status + ').'); }
      if (!response.ok) {
        throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || ('HTTP ' + response.status));
      }
      return data;
    }
    const itemDetail = $('v20-item-detail');
    const lookupUrl = root.dataset.cameraBarcodeLookupUrl;
    let verifiedCode = '';
    function ignoreWebQr(code) { return /^https?:\/\//i.test(String(code || '').trim()); }
    async function resolvePhotoCode(raw) {
      const value = String(raw || '').trim();
      verifiedCode = '';
      confirm.disabled = true;
      if (!value || ignoreWebQr(value)) {
        itemDetail.textContent = ignoreWebQr(value) ? 'QR website (http/https) diabaikan.' : 'Kode kosong.';
        return;
      }
      itemDetail.textContent = 'Memeriksa barcode ke master dan cycle aktif...';
      try {
        if (!lookupUrl) throw new Error('Route lookup barcode belum terpasang.');
        const response = await fetch(lookupUrl + '?barcode=' + encodeURIComponent(value), {
          credentials:'same-origin', headers:{Accept:'application/json'}
        });
        const info = await parseResponse(response);
        if (!info.ok) throw new Error(info.message || 'Barcode tidak ditemukan.');
        // TextContent prevents HTML injection from OCR or item names.
        itemDetail.textContent = 'Kode Barang: ' + info.item_code + ' | Nama: ' + info.item_name
          + ' | UOM: ' + info.uom_code + ' | Ratio: ' + info.ratio
          + ' | Qty: ' + info.qty + ' | Qty Smallest: ' + info.physical_qty
          + ' | Database: ' + info.source_database;
        verifiedCode = value;
        confirm.disabled = false;
        setStatus('Barang cocok. Konfirmasi untuk mencatat scan.');
      } catch (e) {
        itemDetail.textContent = 'Tidak dapat dikonfirmasi: ' + e.message;
        setStatus('Barcode tidak cocok. Foto ulang atau masukkan kode barang manual.');
      }
    }    function showNonSystem(payload, barcode) {
      if (!nsModal) throw new Error('Form Non-System belum tersedia.');
      // The V15 manual script already binds the Save button of this modal.
      $('v15-ns-barcode').value = barcode;
      $('v15-ns-name').value = payload.item_name || '';
      $('v15-ns-qty').value = '1';
      $('v15-ns-uom').value = payload.uom_code || 'PCS';
      $('v15-ns-smallest').value = payload.smallest_uom_code || 'PCS';
      $('v15-ns-ratio').value = payload.ratio_to_smallest || '1';
      $('v15-ns-status').textContent = 'Kode belum terdaftar di cycle ini. Lengkapi data barang temuan.';
      nsModal.hidden = false;
      document.body.classList.add('modal-open');
      setStatus('Barang Non-System: lengkapi data temuan terlebih dahulu.');
    }
    async function submitBarcode(barcode, origin = 'camera') {
      barcode = String(barcode || '').trim();
      if (ignoreWebQr(barcode)) return; // Website QR is never submitted or counted.
      if (!barcode || busy || !running || !manualModal.hidden || !nsModal.hidden || !panel.hidden) return;
      if (barcode === recentValue && Date.now() - recentAt < 2800) return;
      busy = true;
      recentValue = barcode; recentAt = Date.now();
      setStatus('Barcode terbaca: ' + barcode + '. Memproses di server...');
      try {
        const data = await parseResponse(await fetch(root.dataset.scanUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: {'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':csrf},
          body: JSON.stringify({ barcode })
        }));
        if (data.requires_non_system) {
          await stopCamera({ preserveResume: true });
          showNonSystem(data, barcode);
        } else {
          setFeedback(true, 'Scan berhasil', (data.item_code || barcode) + ' · ' + (data.item_name || ''));
          setStatus('Berhasil disimpan: ' + barcode + '. Siap scan berikutnya.');
          beep();
        }
      } catch (e) {
        setStatus('Barcode ' + barcode + ' terbaca, tetapi ditolak: ' + e.message);
        setFeedback(false, 'Scan ditolak', e.message);
      } finally { busy = false; }
    }
    function snapshot() {
      if (!running || !video.videoWidth || video.readyState < 2) throw new Error('Aktifkan kamera dan arahkan ke barcode dahulu.');
      const canvas = document.createElement('canvas');
      const scale = Math.min(1, 1920 / Math.max(video.videoWidth, video.videoHeight));
      canvas.width = Math.round(video.videoWidth * scale);
      canvas.height = Math.round(video.videoHeight * scale);
      canvas.getContext('2d', {alpha:false}).drawImage(video,0,0,canvas.width,canvas.height);
      return { canvas, data: canvas.toDataURL('image/jpeg', .86) };
    }
    function refreshButtons() {
      start.disabled = opening || running;
      stop.disabled = !running && !opening;
      read.disabled = !running || busy;
      indicator.textContent = running ? 'Kamera aktif' : 'Nonaktif';
      indicator.classList.toggle('active', running);
    }
    async function stopCamera({ preserveResume = false } = {}) {
      generation++;
      const savedControls = controls;
      controls = null;
      if (barcodeTimer !== null) clearInterval(barcodeTimer);
      barcodeTimer = null;
      if (savedControls) { try { savedControls.stop(); } catch (_) {} }
      if (media) media.getTracks().forEach(t => t.stop());
      if (video.srcObject) video.srcObject.getTracks?.().forEach(t => t.stop());
      media = null; video.srcObject = null;
      running = false; opening = false;
      if (!preserveResume) autoResume = false;
      refreshButtons();
    }
    async function beginCamera() {
      if (running || opening || !manualModal.hidden || !nsModal.hidden) return;
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        setStatus('Kamera membutuhkan HTTPS atau localhost dan browser yang mengizinkan akses kamera.'); return;
      }
      opening = true; refreshButtons();
      setStatus('Meminta izin kamera belakang...');
      const token = ++generation;
      try {
        const Reader = window.ZXingBrowser?.BrowserMultiFormatReader;
        if (typeof Reader === 'function') {
          reader = new Reader(undefined, {delayBetweenScanAttempts:120, delayBetweenScanSuccess:180});
          let c;
          const onDecoded = result => { if (result && token === generation) submitBarcode(result.getText()); };
          try {
            c = await reader.decodeFromConstraints(
              {audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}}},
              video, onDecoded
            );
          } catch (primaryError) {
            c = await reader.decodeFromConstraints(
              {audio:false,video:{facingMode:{ideal:'environment'}}}, video, onDecoded
            );
          }
          if (token !== generation) { c.stop(); return; }
          controls = c;
          media = video.srcObject;
        } else if ('BarcodeDetector' in window) {
          try { media = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false}); }
          catch (_) { media = await navigator.mediaDevices.getUserMedia({video:true,audio:false}); }
          if (token !== generation) { media.getTracks().forEach(t=>t.stop()); return; }
          video.srcObject = media; await video.play();
          const detector = new BarcodeDetector();
          barcodeTimer = setInterval(async () => {
            if (!running || decoding || busy || !manualModal.hidden || !nsModal.hidden || video.readyState < 2) return;
            decoding = true;
            try { const hits = await detector.detect(video); if (hits.length === 1) submitBarcode(hits[0].rawValue); }
            catch (_) {} finally { decoding = false; }
          }, 200);
        } else {
          throw new Error('Library pembaca barcode tidak tersedia. Periksa assets/vendor/zxing-browser.min.js.');
        }
        running = true; autoResume = true;
        setStatus('Kamera aktif. Arahkan barcode atau tekan Ambil Gambar & Baca AI.');
      } catch (e) {
        if (token === generation) {
          setStatus('Kamera gagal: ' + (e.name === 'NotAllowedError' ? 'izin kamera ditolak' : e.message));
          autoResume = false;
        }
      } finally { if (token === generation) { opening = false; refreshButtons(); } }
    }
    start.addEventListener('click', () => { audio = audio || null; beginCamera(); });
    stop.addEventListener('click', () => { stopCamera(); setStatus('Kamera dimatikan.'); });
    read.addEventListener('click', async () => {
      if (busy) return;
      let frame;
      try { frame = snapshot(); } catch (e) { setStatus(e.message); return; }
      busy = true; refreshButtons(); panel.hidden = true;
      setStatus('Foto diambil dari kamera aktif. Membaca barcode...');
      try {
        let value = '';
        if ('BarcodeDetector' in window) {
          try { const d = await new BarcodeDetector().detect(frame.canvas); if (d.length === 1) value = d[0].rawValue || ''; } catch (_) {}
        }
        if (!value) {
          setStatus('Barcode belum terbaca secara lokal. Meminta Vision AI membaca foto...');
          const data = await parseResponse(await fetch(root.dataset.cameraAiUrl, {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf},
            body:JSON.stringify({image:frame.data})
          }));
          value = String(data.value || '').trim();
        }
        if (!value) throw new Error('Tidak ada kode yang berhasil dibaca. Coba foto ulang dengan cahaya lebih baik.');
        if (ignoreWebQr(value)) { setStatus('QR mengarah ke website, diabaikan.'); return; }
        resultCode.value = value;
        panel.hidden = false;
        await resolvePhotoCode(value);
        resultCode.focus();
      } catch (e) { setStatus('Pembacaan foto gagal: ' + e.message); }
      finally { busy = false; refreshButtons(); }
    });
    confirm.addEventListener('click', async () => {
      const value = resultCode.value.trim();
      if (value !== verifiedCode || confirm.disabled || ignoreWebQr(value)) { setStatus('Periksa data barang terlebih dahulu.'); return; }
      if (!/^[A-Za-z0-9._~\/-]{1,150}$/.test(value)) { setStatus('Format kode tidak valid.'); return; }
      panel.hidden = true;
      await submitBarcode(value, 'photo');
    });
    resultCode.addEventListener('input', () => { verifiedCode = ''; confirm.disabled = true; itemDetail.textContent = 'Kode diedit. Klik Periksa Kode kembali.'; });
    const recheck = document.createElement('button');
    recheck.type = 'button'; recheck.className = 'btn'; recheck.textContent = 'Periksa Kode';
    recheck.addEventListener('click', () => resolvePhotoCode(resultCode.value));
    confirm.parentNode.appendChild(recheck);
    $('v16-cancel-read').addEventListener('click', () => { panel.hidden = true; });
    // V15 manual lookup opens its modal only AFTER item validation.
    // Stop barcode scanning while the manual camera uses the same physical device.
    const monitor = new MutationObserver(() => {
      if (!manualModal.hidden || !nsModal.hidden) {
        if (running || opening) stopCamera({preserveResume:true});
      } else if (autoResume && !running && !opening) {
        // Defer to let the manual modal stop its camera track first.
        setTimeout(() => { if (manualModal.hidden && nsModal.hidden && autoResume) beginCamera(); }, 250);
      }
    });
    monitor.observe(manualModal, {attributes:true,attributeFilter:['hidden']});
    monitor.observe(nsModal, {attributes:true,attributeFilter:['hidden']});
    window.addEventListener('beforeunload', () => { stopCamera(); monitor.disconnect(); });
    refreshButtons();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
