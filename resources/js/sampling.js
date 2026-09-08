import { BrowserMultiFormatReader } from '@zxing/browser';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
// Uses the GERAI scan-lookup and scan-confirm endpoints defined in routes/web.php.
const root = document.querySelector('[data-sampling-scanner]');

if (root) {
    const lookupUrl = root.dataset.lookupUrl;
    const confirmUrl = root.dataset.confirmUrl;
    const barcodeForm = document.getElementById('sample-barcode-form');
    const barcodeInput = document.getElementById('sample-barcode');
    const feedback = document.getElementById('sample-feedback');
    const resultBox = document.getElementById('sample-result');
    const matchBtn = document.getElementById('sample-match');
    const mismatchBtn = document.getElementById('sample-mismatch');
    const mismatchForm = document.getElementById('sample-mismatch-form');
    const qtyInput = document.getElementById('sample-physical-qty');
    let active = null;
    let busy = false;
    let cameraControls = null;

    function setFeedback(type, title, message) {
        feedback.className = `sample-feedback ${type}`;
        feedback.querySelector('strong').textContent = title;
        feedback.querySelector('span').textContent = message;
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

    function resetScan() {
        active = null; resultBox.hidden = true; mismatchForm.hidden = true; qtyInput.value=''; barcodeInput.value='';
        setTimeout(()=>barcodeInput.focus({preventScroll:true}),50);
    }

    async function lookup(raw) {
        const barcode=String(raw||'').trim(); if(!barcode || busy) return; busy=true; setFeedback('idle','Membaca barcode...',barcode);
        try {
            const d=await post(lookupUrl,{barcode}); active=d;
            document.getElementById('sample-result-code').textContent=d.item_code;
            document.getElementById('sample-result-name').textContent=d.item_name;
            document.getElementById('sample-result-barcode').textContent=`Barcode ${d.barcode} · ${d.location}`;
            document.getElementById('sample-system-qty').textContent=Number(d.system_qty).toLocaleString('id-ID',{maximumFractionDigits:4});
            document.getElementById('sample-uom').textContent=`UOM scan: ${d.uom_code}`;
            resultBox.hidden=false; mismatchForm.hidden=true; sound('scan'); setFeedback('success','Item ditemukan','Bandingkan stok sistem dengan jumlah fisik di rak.');
        } catch(e) { active=null; resultBox.hidden=true; sound('error'); setFeedback('error','Scan ditolak',e.message); }
        finally { busy=false; barcodeInput.value=''; }
    }

    async function confirm(result, physical_qty=null) {
        if(!active || busy) return; busy=true;
        try {
            const d=await post(confirmUrl,{token:active.token,result,physical_qty});
            sound(result==='MATCH'?'match':'mismatch'); setFeedback('success',result==='MATCH'?'Stok Cocok':'Selisih Tersimpan',`${d.item_code} · fisik ${d.physical_qty}`);
            document.getElementById('sample-count').textContent=Number(d.count).toLocaleString('id-ID');
            document.getElementById('sample-empty-row')?.remove();
            const tr=document.createElement('tr'); tr.innerHTML=`<td>${d.scanned_at}</td><td>${escapeHtml(d.location)}</td><td><strong>${escapeHtml(d.item_code)}</strong><br><small>${escapeHtml(d.item_name)}</small></td><td class="num">${fmt(d.system_qty)}</td><td class="num">${fmt(d.physical_qty)}</td><td><span class="sample-status ${d.result.toLowerCase()}">${d.result==='MATCH'?'Cocok':'Tidak Cocok'}</span></td>`;
            document.getElementById('sample-history-body').prepend(tr); resetScan();
        } catch(e) { sound('error'); setFeedback('error','Gagal menyimpan',e.message); }
        finally { busy=false; }
    }

    function fmt(v){return Number(v).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:4});}
    function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;}
    barcodeForm?.addEventListener('submit',e=>{e.preventDefault();lookup(barcodeInput.value)});
    matchBtn?.addEventListener('click',()=>confirm('MATCH'));
    mismatchBtn?.addEventListener('click',()=>{mismatchForm.hidden=false;qtyInput.focus()});
    document.getElementById('sample-mismatch-cancel')?.addEventListener('click',()=>{mismatchForm.hidden=true;qtyInput.value='' });
    mismatchForm?.addEventListener('submit',e=>{e.preventDefault();confirm('MISMATCH',qtyInput.value)});

    document.getElementById('sample-camera')?.addEventListener('click', async e => {
        const btn=e.currentTarget; btn.disabled=true; btn.textContent='Membuka kamera...';
        try {
            const reader=new BrowserMultiFormatReader(undefined,{delayBetweenScanAttempts:160});
            cameraControls=await reader.decodeFromConstraints({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}}},document.getElementById('sample-video'),r=>{if(r) lookup(r.getText())});
            btn.style.display='none'; setFeedback('idle','Kamera aktif','Arahkan barcode ke kamera.');
        } catch(_) {btn.disabled=false;btn.textContent='Coba Kamera Lagi';setFeedback('error','Kamera tidak tersedia','Gunakan scanner USB atau input barcode manual.');}
    });
    window.addEventListener('beforeunload',()=>cameraControls?.stop());
    setTimeout(()=>barcodeInput?.focus({preventScroll:true}),200);
}
