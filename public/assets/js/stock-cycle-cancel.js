/* Cycle Stock Opname: pembatalan milik pembuat saja, dengan validasi ulang di server. */
(function () {
    'use strict';
    const script = document.currentScript;
    const optionsUrl = script?.dataset.optionsUrl;
    const cancelPrefix = script?.dataset.cancelPrefix;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const table = document.querySelector('.cycle-list-page .cycle-table');
    if (!optionsUrl || !cancelPrefix || !csrf || !table) return;

    const style = document.createElement('style');
    style.textContent = '.cycle-cancel-button{border:1px solid #fca5a5;background:#fff7f7;color:#991b1b;border-radius:7px;padding:6px 10px;font-size:11px;font-weight:700;cursor:pointer}.cycle-cancel-button:hover{background:#fee2e2}.cycle-status.batal{background:#fee2e2!important;color:#991b1b!important}.cycle-cancel-dialog{border:1px solid #e2e8f0;border-radius:12px;padding:22px;width:min(450px,calc(100vw - 32px));box-shadow:0 24px 60px #1118274d;color:#111827}.cycle-cancel-dialog::backdrop{background:rgba(15,23,42,.5)}.cycle-cancel-dialog textarea{display:block;width:100%;min-height:96px;margin:12px 0;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font:inherit}.cycle-cancel-dialog .cycle-cancel-buttons{display:flex;justify-content:flex-end;gap:8px}.cycle-cancel-dialog .cycle-cancel-buttons button{padding:9px 13px;border-radius:7px;cursor:pointer}.cycle-cancel-dialog .cycle-submit{background:#b91c1c;color:white;border:0}';
    document.head.appendChild(style);

    const statusSelect = document.querySelector('.cycle-list-page select[name="status"]');
    if (statusSelect && !Array.from(statusSelect.options).some(o => o.value === 'BATAL')) {
        const opt = new Option('BATAL', 'BATAL');
        if (new URLSearchParams(location.search).get('status') === 'BATAL') opt.selected = true;
        statusSelect.add(opt);
    }

    const rows = [];
    table.querySelectorAll('tbody tr').forEach(tr => {
        const link = tr.querySelector('a.cycle-link[href]');
        if (!link) return;
        const match = new URL(link.href, location.href).pathname.match(/\/admin\/cycles\/(\d+)\/?$/);
        if (!match) return;
        rows.push({ id: match[1], number: link.textContent.trim(), tr });
    });
    if (!rows.length) return;

    const dialog = document.createElement('dialog');
    dialog.className = 'cycle-cancel-dialog';
    dialog.innerHTML = '<h3 style="margin:0">Batalkan Cycle Stock Opname</h3><p class="cycle-cancel-target"></p><p style="font-size:13px;color:#64748b">Cycle menjadi BATAL. Data scan, snapshot dan riwayat tetap tersimpan. Pembatalan tidak dapat dibatalkan kembali.</p><form method="POST"><input type="hidden" name="_token"><label for="cycle-cancel-reason">Alasan pembatalan (wajib, minimal 10 karakter)</label><textarea id="cycle-cancel-reason" name="reason" minlength="10" maxlength="500" required placeholder="Jelaskan alasan pembatalan..."></textarea><div class="cycle-cancel-buttons"><button type="button" class="cycle-dismiss">Kembali</button><button type="submit" class="cycle-submit">Konfirmasi Batal</button></div></form>';
    document.body.appendChild(dialog);
    dialog.querySelector('input[name="_token"]').value = csrf;
    dialog.querySelector('.cycle-dismiss').addEventListener('click', () => dialog.close());
    dialog.querySelector('form').addEventListener('submit', function (event) {
        const reason = this.elements.reason.value.trim();
        if (reason.length < 10) {
            event.preventDefault();
            alert('Alasan pembatalan minimal 10 karakter.');
            return;
        }
        if (!confirm('Yakin batalkan cycle ini secara permanen?')) event.preventDefault();
    });

    const url = new URL(optionsUrl, location.href);
    rows.forEach(r => url.searchParams.append('ids[]', r.id));
    fetch(url.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => { if (!r.ok) throw Error('Gagal memuat hak akses pembatalan'); return r.json(); })
        .then(data => {
            rows.forEach(row => {
                const canceled = data.cancelled?.[row.id];
                if (canceled) {
                    const status = row.tr.querySelector('.cycle-status');
                    if (status) {
                        status.title = 'Alasan: ' + (canceled.reason || '-') + ' | Waktu: ' + (canceled.at || '-');
                        const info = document.createElement('small');
                        info.className = 'cycle-sub';
                        info.textContent = 'Alasan: ' + (canceled.reason || '-');
                        status.after(info);
                    }
                }
                if (data.options?.[row.id] !== true) return;
                const actions = row.tr.querySelector('td:last-child .cycle-actions');
                if (!actions) return;
                actions.querySelector('.cycle-muted')?.remove();
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'cycle-cancel-button';
                button.textContent = 'Batalkan';
                button.addEventListener('click', () => {
                    dialog.querySelector('.cycle-cancel-target').textContent = 'Nomor: ' + row.number;
                    dialog.querySelector('form').action = cancelPrefix.replace(/\/$/, '') + '/' + encodeURIComponent(row.id);
                    dialog.querySelector('textarea').value = '';
                    dialog.showModal();
                    dialog.querySelector('textarea').focus();
                });
                actions.appendChild(button);
            });
        })
        .catch(error => console.warn('[Cycle cancel]', error.message));
})();
