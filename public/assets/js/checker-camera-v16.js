(() => {
  function init() {
    const root = document.getElementById('v15-checker');
    if (!root || root.dataset.cameraV21 === 'yes') return;
    const $ = id => document.getElementById(id);
    if (!$('v21-qty')) return;
    root.dataset.cameraV21 = 'yes';
    const video = $('v16-scanner-video'), start = $('v16-camera-start'), stop = $('v16-camera-stop');
    const read = $('v16-camera-read'), status = $('v16-camera-status'), indicator = $('v16-camera-indicator');
    const panel = $('v16-confirm'), resultCode = $('v16-ai-code'), confirm = $('v16-confirm-scan');
    const cancel = $('v16-cancel-read'), detail = $('v20-item-detail'), qty = $('v21-qty');
    const capture = $('v21-capture'), preview = $('v21-photo-preview'), removePhoto = $('v21-remove-photo');
    const manualModal = $('v15-manual-modal'), nsModal = $('v15-non-system-modal');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let controls = null, media = null, detectorTimer = null, reader = null;
    let running = false, opening = false, busy = false, decoding = false, autoResume = false;
    let generation = 0, successTimer = null, audio = null, item = null, photo = '';
    // Hold a continuously visible barcode after save/cancel. Moving it out of frame
    // and back (or explicitly using AI) permits a legitimate next count of the same SKU.
    let blockedCode = '', lastSeenAt = 0;
    const setStatus = message => { status.textContent = message; };
    const setFeedback = (ok, title, message) => {
      const box = $('v15-feedback');
      if (!box) return;
      if (successTimer) clearTimeout(successTimer);
      box.className = 'scan-feedback ' + (ok ? 'success' : 'error');
      box.querySelector('strong').textContent = title;
      box.querySelector('span').textContent = message;
      if (ok) successTimer = setTimeout(() => {
        box.className = 'scan-feedback idle';
        box.querySelector('strong').textContent = 'Siap scan berikutnya';
        box.querySelector('span').textContent = 'Arahkan barcode berikutnya, atau masukkan kode barang tanpa barcode.';
      }, 1800);
    };
    function beep() {
      try {
        const Context = window.AudioContext || window.webkitAudioContext;
        if (Context) {
          audio = audio || new Context(); audio.resume?.().catch(() => {});
          [930,1350].forEach((hz,i) => {
            const osc = audio.createOscillator(), gain = audio.createGain(), when = audio.currentTime + i*.13;
            osc.type = 'square'; osc.frequency.value = hz; gain.gain.setValueAtTime(.0001,when);
            gain.gain.exponentialRampToValueAtTime(.20,when+.012); gain.gain.exponentialRampToValueAtTime(.0001,when+.12);
            osc.connect(gain).connect(audio.destination); osc.start(when); osc.stop(when+.13);
          });
        }
      } catch (_) {}
      navigator.vibrate?.([90,30,90]);
    }
    async function parseResponse(response) {
      let data;
      try { data = await response.json(); } catch (_) { throw new Error('Server tidak mengembalikan JSON (HTTP '+response.status+').'); }
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || ('HTTP '+response.status));
      return data;
    }
    function post(url, body) {
      return fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)}).then(parseResponse);
    }
    function ignoreWebQr(value) { return /^https?:\/\//i.test(String(value || '').trim()); }
    function fmt(n) { return Number(n).toLocaleString('id-ID',{maximumFractionDigits:4,useGrouping:true}); }
    function validQty() { return qty.value !== '' && qty.checkValidity() && Number.isFinite(Number(qty.value)) && Number(qty.value)>0 && Number(qty.value)<=999999999 && !!item && Number(qty.value)*Number(item.ratio)<=999999999999 && Number(qty.value)*Number(item.ratio)>=0.0001; }
    function sync() {
      start.disabled = opening || running || busy;
      stop.disabled = busy || (!running && !opening);
      read.disabled = !running || busy || !panel.hidden;
      indicator.textContent = running ? 'Kamera aktif' : 'Nonaktif';
      indicator.classList.toggle('active',running);
      $('v16-video-text').textContent = running ? (panel.hidden ? 'Arahkan barcode ke kotak scan' : 'Arahkan ke barang untuk foto bukti') : 'Tekan Aktifkan Kamera untuk mulai scan';
      confirm.disabled = busy || !validQty();
      cancel.disabled = busy;
      qty.disabled = busy || !item;
      capture.disabled = busy || !running || !item || !item.photo_allowed;
      removePhoto.disabled = busy;
      $('v15-check').disabled = busy || !panel.hidden;
      $('v21-physical').textContent = validQty() ? ('Qty terkecil yang dicatat: '+fmt(Number(qty.value)*Number(item.ratio))) : 'Masukkan qty yang valid.';
    }
    function clearPhoto() {
      photo = ''; preview.hidden = true; preview.removeAttribute('src'); removePhoto.hidden = true;
      $('v21-photo-status').textContent = '';
    }
    function closePanel() {
      item = null; clearPhoto(); panel.hidden = true; resultCode.value = ''; qty.value = '1'; sync();
    }
    function block(code) { blockedCode = code; lastSeenAt = Date.now(); }
    function showNonSystem(data, barcode) {
      $('v15-ns-barcode').value = barcode; $('v15-ns-name').value = data.item_name || '';
      $('v15-ns-qty').value = '1'; $('v15-ns-uom').value = data.uom_code || 'PCS';
      $('v15-ns-smallest').value = data.smallest_uom_code || 'PCS'; $('v15-ns-ratio').value = data.ratio_to_smallest || '1';
      $('v15-ns-status').textContent = 'Kode belum terdaftar di master. Lengkapi data barang temuan.';
      nsModal.hidden = false; document.body.classList.add('modal-open');
      setStatus('Barang Non-System: lengkapi data temuan terlebih dahulu.');
    }
    async function openConfirmation(raw) {
      const value = String(raw || '').trim();
      if (!value || ignoreWebQr(value) || !manualModal.hidden || !nsModal.hidden) return;
      block(value); clearPhoto(); item = null; panel.hidden = false; resultCode.value = value;
      qty.value = '1'; $('v21-uom').textContent = ''; $('v21-photo-area').hidden = true;
      detail.textContent = 'Memeriksa barcode ke master dan cycle aktif...'; sync();
      try {
        const url = new URL(root.dataset.cameraBarcodeLookupUrl,location.href);
        url.searchParams.set('barcode',value);
        const info = await parseResponse(await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}}));
        if (info.requires_non_system) {
          closePanel(); await stopCamera({preserveResume:true}); showNonSystem(info,value); return;
        }
        if (!info.ok) throw new Error(info.message || 'Barcode tidak ditemukan.');
        item = info; qty.value = String(info.qty);
        $('v21-uom').textContent = '('+info.uom_code+')';
        $('v21-photo-area').hidden = !info.photo_allowed;
        detail.textContent = 'Kode: '+info.item_code+' | Nama: '+(info.item_name || '-')+' | UOM: '+info.uom_code+' | Ratio: '+fmt(info.ratio)+' | Database: '+info.source_database;
        // No stock-system quantity or variance is returned/displayed to checker.
        setStatus('Barang cocok. Isi jumlah fisik dan konfirmasi untuk mencatat scan.');
      } catch(e) {
        detail.textContent = 'Tidak dapat dikonfirmasi: '+e.message;
        setStatus('Scan ditolak: '+e.message); setFeedback(false,'Scan ditolak',e.message);
      } finally { sync(); panel.scrollIntoView({behavior:'auto',block:'nearest'}); }
    }
    async function onDecoded(raw) {
      const code = String(raw || '').trim();
      if (!code || ignoreWebQr(code) || !running) return;
      const now = Date.now();
      if (code === blockedCode && now-lastSeenAt < 1500) { lastSeenAt = now; return; }
      if (busy || !manualModal.hidden || !nsModal.hidden || !panel.hidden) return;
      busy = true; sync();
      try { await openConfirmation(code); }
      finally { busy = false; sync(); }
    }
    function snapshot() {
      if (!running || !video.videoWidth || video.readyState<2) throw new Error('Aktifkan kamera terlebih dahulu.');
      const canvas = document.createElement('canvas');
      const scale = Math.min(1,1920/Math.max(video.videoWidth,video.videoHeight));
      canvas.width = Math.round(video.videoWidth*scale); canvas.height = Math.round(video.videoHeight*scale);
      canvas.getContext('2d',{alpha:false}).drawImage(video,0,0,canvas.width,canvas.height);
      return {canvas,data:canvas.toDataURL('image/jpeg',.82)};
    }
    async function stopCamera({preserveResume=false}={}) {
      generation++;
      const saved = controls; controls = null;
      if (detectorTimer !== null) clearInterval(detectorTimer); detectorTimer = null;
      try { saved?.stop(); } catch (_) {}
      media?.getTracks().forEach(t=>t.stop()); video.srcObject?.getTracks?.().forEach(t=>t.stop());
      media = null; video.srcObject = null; running = false; opening = false;
      if (!preserveResume) autoResume = false;
      sync();
    }
    async function beginCamera() {
      if (running || opening || !manualModal.hidden || !nsModal.hidden) return;
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) { setStatus('Kamera membutuhkan HTTPS atau localhost dan izin kamera.'); return; }
      opening = true; sync(); setStatus('Meminta izin kamera belakang...');
      const token = ++generation;
      try {
        const Reader = window.ZXingBrowser?.BrowserMultiFormatReader;
        if (typeof Reader === 'function') {
          reader = new Reader(undefined,{delayBetweenScanAttempts:120,delayBetweenScanSuccess:180});
          const decoded = result => { if (result && token === generation) onDecoded(result.getText()); };
          let c;
          try { c = await reader.decodeFromConstraints({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}}},video,decoded); }
          catch (_) {
            if (token !== generation) return;
            c = await reader.decodeFromConstraints({audio:false,video:{facingMode:{ideal:'environment'}}},video,decoded);
          }
          if (token !== generation) { c.stop(); return; }
          controls = c; media = video.srcObject;
        } else if ('BarcodeDetector' in window) {
          let acquired;
          try { acquired = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false}); }
          catch (_) { acquired = await navigator.mediaDevices.getUserMedia({video:true,audio:false}); }
          if (token !== generation) { acquired.getTracks().forEach(t=>t.stop()); return; }
          media = acquired; video.srcObject = media; await video.play();
          if (token !== generation) { acquired.getTracks().forEach(t=>t.stop()); return; }
          const detector = new BarcodeDetector();
          detectorTimer = setInterval(async()=>{
            if (!running || decoding || video.readyState<2) return;
            decoding = true;
            try { const hits = await detector.detect(video); if (token===generation && hits.length===1) onDecoded(hits[0].rawValue); }
            catch (_) {} finally { decoding = false; }
          },200);
        } else throw new Error('Library pembaca barcode tidak tersedia. Periksa assets/vendor/zxing-browser.min.js.');
        running = true; autoResume = true;
        setStatus(panel.hidden?'Kamera aktif. Arahkan barcode atau gunakan AI.':'Kamera aktif. Anda dapat mengambil foto bukti.');
      } catch(e) {
        if (token===generation) { await stopCamera(); setStatus('Kamera gagal: '+(e.name==='NotAllowedError'?'izin kamera ditolak':e.message)); }
      } finally { if (token===generation) { opening=false; sync(); } }
    }
    start.addEventListener('click',beginCamera);
    stop.addEventListener('click',()=>{stopCamera();setStatus('Kamera dimatikan.');});
    read.addEventListener('click',async()=>{
      if (busy || !panel.hidden) return;
      let frame;
      try { frame=snapshot(); } catch(e) {setStatus(e.message);return;}
      busy=true;sync();setStatus('Membaca barcode dari foto...');
      try {
        let value='';
        if ('BarcodeDetector' in window) {
          try { const hits=await new BarcodeDetector().detect(frame.canvas);if(hits.length===1)value=hits[0].rawValue||''; } catch (_) {}
        }
        if (!value) {
          setStatus('Meminta Vision AI membaca foto...');
          const data=await post(root.dataset.cameraAiUrl,{image:frame.data});value=String(data.value||'').trim();
        }
        if (!value) throw new Error('Tidak ada kode yang berhasil dibaca. Coba foto ulang.');
        if (ignoreWebQr(value)) {setStatus('QR website diabaikan.');return;}
        await openConfirmation(value);
      } catch(e) {setStatus('Pembacaan foto gagal: '+e.message);}
      finally {busy=false;sync();}
    });
    qty.addEventListener('input',sync);
    capture.addEventListener('click',()=>{
      try {photo=snapshot().data;preview.src=photo;preview.hidden=false;removePhoto.hidden=false;$('v21-photo-status').textContent='Foto bukti siap disimpan bersama Qty.';}
      catch(e) {$('v21-photo-status').textContent=e.message;}
    });
    removePhoto.addEventListener('click',clearPhoto);
    confirm.addEventListener('click',async()=>{
      if (busy || !validQty() || !item || panel.hidden) return;
      const barcode=resultCode.value;
      busy=true;sync();setStatus('Menyimpan qty fisik...');
      try {
        const data=await post(root.dataset.scanUrl,{barcode,qty:qty.value,...(photo?{photo}:{})});
        if (!data.ok) throw new Error('Barang tidak lagi dapat dikonfirmasi. Batal lalu scan ulang.');
        block(barcode);closePanel();
        setFeedback(true,'Scan berhasil',data.item_code+' · '+fmt(data.qty)+' '+data.uom_code+' (qty terkecil '+fmt(data.physical_qty)+')'+(data.photo_saved?' · Foto tersimpan':''));
        setStatus('Tersimpan. Jauhkan barcode dari kamera sebelum scan barang berikutnya.');beep();
      } catch(e) {setStatus('Gagal menyimpan: '+e.message);setFeedback(false,'Scan ditolak',e.message);}
      finally {busy=false;sync();}
    });
    cancel.addEventListener('click',()=>{if(busy)return;block(resultCode.value);closePanel();setStatus('Dibatalkan. Tidak ada stok yang dicatat.');});
    // The previous manual modal had an alternate AI button which posted a scan
    // immediately. Route it through the same qty confirmation, without changing
    // the established manual-item + mandatory photo workflow.
    $('v15-ai-confirm')?.addEventListener('click',async e=>{
      e.preventDefault();e.stopImmediatePropagation();
      const value=$('v15-ai-value').value.trim();
      if(busy||!value||ignoreWebQr(value))return;
      $('v15-close').click();busy=true;sync();
      try {await openConfirmation(value);} finally {busy=false;sync();}
    },true);
    const monitor=new MutationObserver(()=>{
      if(!manualModal.hidden||!nsModal.hidden){if(running||opening)stopCamera({preserveResume:true});}
      else if(autoResume&&!running&&!opening){setTimeout(()=>{if(manualModal.hidden&&nsModal.hidden&&autoResume)beginCamera();},250);}
    });
    monitor.observe(manualModal,{attributes:true,attributeFilter:['hidden']});
    monitor.observe(nsModal,{attributes:true,attributeFilter:['hidden']});
    window.addEventListener('beforeunload',()=>{stopCamera();monitor.disconnect();});
    sync();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
