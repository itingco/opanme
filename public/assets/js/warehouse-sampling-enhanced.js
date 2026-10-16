(() => {
'use strict';
window.addEventListener('load', () => {
    if (window.wsPeriodIsDraft) return;
    const list = document.querySelector('.ws-admin-item-list');
    if (!list) return;
    const cards = [...list.querySelectorAll(':scope > .ws-admin-item-card')];
    if (!cards.length) return;
    const format = n => Number(n || 0).toLocaleString('id-ID', {maximumFractionDigits:4});
    const numeric = s => Number(String(s || '0').replace(/,/g, '').trim()) || 0;
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const selected = new Set();
    const selectors = new Map();
    const actions = document.createElement('div');
    actions.className = 'ws-bulk-bar';
    actions.innerHTML = '<span class="ws-bulk-count">0 item dipilih</span><button class="btn small" type="button" data-open-all>Buka Semua Selisih</button><button class="btn small" type="button" data-close-matched>Tutup Semua Cocok</button><button class="btn small" type="button" data-select-errors>Pilih Semua Selisih</button><button class="btn primary" type="button" data-save-bulk disabled>Validasi Terpilih</button><small class="ws-bulk-hint">Centang item yang menunggu validasi. Isi override atau catatan pada item tertentu sebelum klik Validasi Terpilih. Validasi massal bersifat atomik: jika ada item gagal, semuanya dibatalkan.</small>';
    list.before(actions);
    const btnBulk = actions.querySelector('[data-save-bulk]');
    const updateSelected = () => {
        actions.querySelector('.ws-bulk-count').textContent = `${selected.size} item dipilih`;
        btnBulk.disabled = selected.size === 0;
    };

    function enhanceForm(form) {
        const physical = Number(form.dataset.physicalTotal || 0);
        const rows = [...form.querySelectorAll('[data-stock-row]')];
        const allocationInputs = rows.map(row => row.querySelector('[data-allocation-input]')).filter(Boolean);
        const warning = form.querySelector('[data-allocation-warning]');
        const totalEl = form.querySelector('[data-allocation-total]');
        const saveButton = form.querySelector('[data-validation-save]');
        const note = form.querySelector('.ws-validation-note');
        const panel = document.createElement('div');
        panel.className = 'ws-override-panel';
        panel.innerHTML = `<label>Qty Override Admin (opsional)<input type="number" min="0" max="999999999999" step="1" inputmode="numeric" name="admin_override_qty" placeholder="Kosong = gunakan Qty Fisik Checker" autocomplete="off"></label><small>Fisik checker: <strong>${format(physical)}</strong>. Total final menggunakan override jika diisi. Qty fisik asli tidak diubah.</small>`;
        note?.before(panel);
        const override = panel.querySelector('input');
        const target = () => override.value === '' ? Math.round(physical) : Number(override.value);
        const isValid = () => {
            const n = target();
            const total = allocationInputs.reduce((sum,input) => sum + Number(input.value || 0), 0);
            return Number.isSafeInteger(n) && n >= 0 && Number.isInteger(total) && total === n && (override.value !== '' || Math.abs(physical-n) < .0001);
        };
        function refresh() {
            const n = target();
            const allocated = allocationInputs.reduce((sum,input) => sum + Number(input.value || 0), 0);
            const valid = isValid();
            if (warning) {
                warning.classList.toggle('ok', valid);
                warning.textContent = valid
                  ? `Qty Final Admin ${format(n)} sudah sesuai total distribusi. Fisik Checker tetap ${format(physical)}.`
                  : `Total distribusi ${format(allocated)} harus sesuai Qty Final Admin ${format(n)}, bukan wajib sesuai Qty Fisik Checker ${format(physical)}.`;
            }
            if (totalEl) totalEl.textContent = format(allocated);
            if (saveButton) saveButton.disabled = !valid;
        }
        function distribute() {
            const qty = target();
            if (!Number.isSafeInteger(qty) || qty < 0) {refresh();return;}
            const candidates = rows.filter(row => row.dataset.canAllocate === '1');
            const weighted = candidates.filter(row => Number(row.dataset.system || 0) > 0);
            const total = weighted.reduce((sum,row) => sum + Number(row.dataset.system || 0), 0);
            const fractions = weighted.map(row => {
                const exact = qty * (Number(row.dataset.system) / total);
                return {row,base:Math.floor(exact),fraction:exact-Math.floor(exact)};
            });
            let missing = qty - fractions.reduce((sum,x) => sum + x.base, 0);
            fractions.sort((a,b) => b.fraction-a.fraction || Number(a.row.dataset.stockId)-Number(b.row.dataset.stockId));
            const allocations = new Map();
            fractions.forEach((x,i) => allocations.set(x.row, x.base + (i<missing?1:0)));
            if (weighted.length === 0 && candidates.length) allocations.set(candidates[0], qty);
            rows.forEach(row => {
                const input = row.querySelector('[data-allocation-input]');
                if (!input) return;
                const value = allocations.get(row) || 0;
                input.value = String(value);
                const system = Number(row.dataset.system || 0);
                const variance = value - system;
                const varianceInput = row.querySelector('[data-variance-input]');
                if (varianceInput) varianceInput.value = String(Math.round(variance*10000)/10000);
                const proportionEl = row.querySelector('[data-proportional-qty]');
                if (proportionEl) proportionEl.textContent = format(value);
                const varianceEl = row.querySelector('[data-row-variance]');
                if (varianceEl) varianceEl.textContent = format(variance);
                row.classList.toggle('is-short', variance < -.0001);
                row.classList.toggle('is-over', variance > .0001);
                row.classList.toggle('is-match', Math.abs(variance) <= .0001);
            });
            refresh();
        }
        override.addEventListener('input', distribute);
        rows.forEach(row => row.querySelector('[data-variance-input]')?.addEventListener('change', () => {queueMicrotask(refresh);setTimeout(refresh,0);}));
        // Capture-phase listener runs ahead of the old form's bubble listener that assumes physical===allocation.
        form.addEventListener('submit', event => {
            event.stopImmediatePropagation();
            if (!isValid()) { event.preventDefault(); refresh(); }
        }, true);
        refresh();
        return {refresh,isValid,override};
    }

    cards.forEach(card => {
        const form = card.querySelector('.ws-validation-form');
        const validated = card.classList.contains('validated');
        const varianceLabel = card.querySelector('.ws-total-strip > span:last-child b');
        const mismatch = validated
          ? !!card.querySelector('.ws-allocation-card.is-short,.ws-allocation-card.is-over')
          : !!form && Math.abs(numeric(varianceLabel?.textContent)) > .0001;
        const type = mismatch ? 'mismatch' : (form || validated ? 'match' : 'pending');
        const details = document.createElement('details');
        details.className = 'ws-accordion ws-accordion--'+type;
        details.open = mismatch;
        const summary = document.createElement('summary');
        const head = card.querySelector('.ws-admin-item-head');
        const body = document.createElement('div');
        body.className = 'ws-accordion-body';
        if (form) {
            const match = form.action.match(/\/items\/(\d+)\/validate\/?$/);
            if (match) {
                const id = match[1];
                const checkboxLabel = document.createElement('label');
                checkboxLabel.className = 'ws-accordion-select';
                checkboxLabel.innerHTML = '<input type="checkbox" aria-label="Pilih item untuk validasi massal"> Pilih';
                checkboxLabel.addEventListener('click',e => e.stopPropagation());
                checkboxLabel.addEventListener('keydown',e => e.stopPropagation());
                const check = checkboxLabel.querySelector('input');
                check.addEventListener('change',() => {check.checked ? selected.add(id) : selected.delete(id);updateSelected();});
                summary.appendChild(checkboxLabel);
                selectors.set(id,{card,form,check,details,enhanced:enhanceForm(form)});
            }
        }
        if (head) summary.appendChild(head);
        while (card.firstChild) body.appendChild(card.firstChild);
        details.appendChild(summary);details.appendChild(body);card.appendChild(details);
        card.classList.add('ws-enhanced-item');
    });
    actions.querySelector('[data-open-all]').addEventListener('click', () => list.querySelectorAll('.ws-accordion--mismatch').forEach(el=>el.open=true));
    actions.querySelector('[data-close-matched]').addEventListener('click', () => list.querySelectorAll('.ws-accordion--match').forEach(el=>el.open=false));
    actions.querySelector('[data-select-errors]').addEventListener('click', () => {
        for (const [id,rec] of selectors) if (rec.details.classList.contains('ws-accordion--mismatch')) {rec.check.checked=true; selected.add(id);rec.details.open=true;}
        updateSelected();
    });
    btnBulk.addEventListener('click', () => {
        if (selected.size === 0) return;
        const invalid = [...selected].filter(id => !selectors.get(id)?.enhanced.isValid());
        if (invalid.length) {
            invalid.forEach(id => selectors.get(id).details.open=true);
            alert('Ada Qty Final/total alokasi belum cocok. Periksa item yang terbuka sebelum validasi massal.');
            return;
        }
        if (!confirm(`Validasi ${selected.size} item terpilih sekaligus?`)) return;
        const submitForm = document.createElement('form');
        submitForm.method='POST';submitForm.action=window.wsBulkValidationUrl;submitForm.hidden=true;
        function hidden(name,value) {
            const el = document.createElement('input'); el.type='hidden'; el.name=name; el.value=value;
            submitForm.appendChild(el);
        }
        hidden('_token',token);
        for (const id of selected) {
            const {form} = selectors.get(id);
            const prefix=`items[${id}]`;
            form.querySelectorAll('[data-allocation-input]').forEach(input => {
                const stockId = input.name.match(/\[(\d+)\]/)?.[1];
                if (stockId) hidden(`${prefix}[allocation_qty][${stockId}]`,input.value);
            });
            const override = form.querySelector('[name="admin_override_qty"]');
            if (override && override.value !== '') hidden(`${prefix}[admin_override_qty]`,override.value);
            hidden(`${prefix}[validation_note]`,form.querySelector('[name="validation_note"]')?.value || '');
        }
        document.body.appendChild(submitForm); submitForm.submit();
    });
});
})();
