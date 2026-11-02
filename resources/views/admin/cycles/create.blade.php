@extends('layouts.app')
@section('title','Buat Cycle')
@section('content')
<div class="page-heading"><div><h1>Buat Cycle Baru</h1><p>Pilih database dan beberapa warehouse yang berada dalam area fisik stock opname.</p></div></div>
<section class="panel narrow">
<form method="POST" action="{{ route('admin.cycles.store') }}" class="stack-form" id="cycle-form">@csrf
<label>Database ERP<select name="source_database" id="cycle-db" required><option value="">Pilih database</option>@foreach($databases as $db)<option value="{{ $db }}" @selected(old('source_database')===$db)>{{ $db }}</option>@endforeach</select></label>
<label>Tanggal Cutoff<input type="date" name="cutoff_date" value="{{ old('cutoff_date', now()->format('Y-m-d')) }}" required></label>
<div><div class="label-title">Warehouse aktif</div><div id="warehouse-list" class="warehouse-picker" aria-live="polite"><div class="empty compact">Pilih database untuk mengambil warehouse.</div></div></div>
<button class="btn primary full" id="cycle-submit" type="submit" disabled>Buat Cycle & Lanjut Assignment</button>
</form>
</section>
@endsection
@push('scripts')
<script>
(() => {
    const select = document.getElementById('cycle-db');
    const list = document.getElementById('warehouse-list');
    const submit = document.getElementById('cycle-submit');
    const form = document.getElementById('cycle-form');
    const endpoint = @json(auth()->user()->isAdminGerai() ? route('gerai.admin.warehouses') : route('admin.erp.warehouses'));
    const previousIds = @json(array_map('strval', old('warehouse_ids', [])));
    let controller;

    function showMessage(message, isError = false, reference = '') {
        list.replaceChildren();
        const panel = document.createElement('div');
        panel.className = isError ? 'danger-lite' : 'empty compact';
        panel.style.cssText = 'padding:14px;line-height:1.55;';
        const text = document.createElement('div');
        text.textContent = message;
        panel.append(text);
        if (reference) {
            const code = document.createElement('small');
            code.style.display = 'block';
            code.textContent = `Kode error: ${reference} (lihat storage/logs/laravel.log)`;
            panel.append(code);
        }
        if (isError) {
            const retry = document.createElement('button');
            retry.className = 'btn small';
            retry.type = 'button';
            retry.style.marginTop = '10px';
            retry.textContent = 'Coba Lagi';
            retry.addEventListener('click', loadWarehouses);
            panel.append(retry);
        }
        list.append(panel);
    }

    function syncSubmit() {
        submit.disabled = !list.querySelector('input[name="warehouse_ids[]"]:checked');
    }

    async function loadWarehouses() {
        controller?.abort();
        submit.disabled = true;
        const db = select.value;
        if (!db) {
            showMessage('Pilih database untuk mengambil warehouse.');
            return;
        }
        showMessage('Mengambil warehouse dari ERP...');
        controller = new AbortController();
        try {
            const url = new URL(endpoint, location.origin);
            url.searchParams.set('source_database', db);
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                if (response.status === 401 || response.status === 403) {
                    throw Object.assign(new Error('Akses ditolak atau sesi login sudah habis. Silakan login ulang dan periksa hak akses Cycle Opname.'), {reference: json.reference});
                }
                throw Object.assign(new Error(json.message || `Request gagal (HTTP ${response.status}).`), {reference: json.reference});
            }
            if (!Array.isArray(json.data)) throw new Error('Respons endpoint warehouse tidak valid.');
            list.replaceChildren();
            for (const warehouse of json.data) {
                const label = document.createElement('label');
                label.className = 'warehouse-option';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'warehouse_ids[]';
                checkbox.value = String(warehouse.warehouse_id);
                checkbox.checked = previousIds.includes(checkbox.value);
                checkbox.addEventListener('change', syncSubmit);
                const span = document.createElement('span');
                const strong = document.createElement('strong');
                strong.textContent = String(warehouse.warehouse_code ?? '');
                const small = document.createElement('small');
                small.textContent = String(warehouse.warehouse_name ?? '');
                span.append(strong, small);
                label.append(checkbox, span);
                list.append(label);
            }
            if (!json.data.length) showMessage(`Tidak ada warehouse aktif dari ${db}.`);
            syncSubmit();
        } catch (error) {
            if (error.name === 'AbortError') return;
            showMessage(error.message || 'Gagal membaca warehouse ERP.', true, error.reference || '');
        }
    }
    select.addEventListener('change', loadWarehouses);
    form.addEventListener('submit', event => {
        if (!list.querySelector('input[name="warehouse_ids[]"]:checked')) {
            event.preventDefault();
            showMessage('Pilih minimal satu warehouse.', true);
        }
    });
    if (select.value) loadWarehouses();
})();
</script>
@endpush
