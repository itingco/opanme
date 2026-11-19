/* Patch tambahan untuk scanner Gerai, tanpa mengganti scanner/kamera bawaan. */
(() => {
  const root = document.querySelector('[data-sampling-scanner]');
  if (!root) return;
  const modal = document.getElementById('sample-result');
  const form = document.getElementById('sample-mismatch-form');
  const actions = document.getElementById('sample-validation-actions');
  const qty = document.getElementById('sample-physical-qty');
  if (!modal || !form || !qty) return;
  let lookup = null;
  let activeToken = '';
  const originalFetch = window.fetch.bind(window);
  window.fetch = async function(resource, init) {
    const response = await originalFetch(resource, init);
    const url = typeof resource === 'string' ? resource : (resource?.url || '');
    if (url.includes('/scan-lookup') && response.ok) {
      try { lookup = await response.clone().json(); } catch (_) {}
    }
    return response;
  };
  const text = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
  const fmt = value => Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 });
  let info = document.getElementById('cycle-gerai-uom-hint');
  if (!info) {
    info = document.createElement('div');
    info.id = 'cycle-gerai-uom-hint';
    info.style.cssText = 'margin:10px 0;padding:10px;background:#f4f7fc;border-radius:6px;color:#16263a;';
    qty.closest('label')?.after(info);
  }
  const currentDiff = document.getElementById('sample-qty-difference');
  function displayQty() {
    if (!lookup) return;
    const ratio = Number(lookup.ratio || 1);
    const base = lookup.smallest_uom_code || lookup.uom_code;
    const physical = Number(qty.value) * ratio;
    const delta = physical - Number(lookup.system_qty || 0);
    text('sample-uom', 'Stok sistem dalam ' + base);
    info.textContent = `Barcode: ${lookup.uom_code} | 1 ${lookup.uom_code} = ${fmt(ratio)} ${base} | Qty fisik: ${fmt(physical)} ${base}`;
    if (currentDiff && Number.isFinite(delta)) currentDiff.textContent = 'Selisih: ' + (delta > 0 ? '+' : '') + fmt(delta) + ' ' + base;
  }
  function showQty() {
    if (modal.hidden || !lookup || !lookup.token || lookup.token === activeToken) return;
    activeToken = lookup.token;
    if (actions) actions.hidden = true;
    form.hidden = false;
    qty.value = '1';
    const label = qty.closest('label');
    if (label) label.firstChild.textContent = 'Qty fisik dalam satuan barcode (' + lookup.uom_code + ') ';
    displayQty();
    setTimeout(() => qty.focus({preventScroll:true}), 40);
  }
  new MutationObserver(showQty).observe(modal, {attributes:true,attributeFilter:['hidden']});
  qty.addEventListener('input', () => setTimeout(displayQty, 0));
})();
