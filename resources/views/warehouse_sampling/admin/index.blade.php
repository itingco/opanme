@extends('layouts.app')
@section('title','Sampling Gudang')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling.css') }}?v=20260923e">
@endpush

<div class="page-heading">
    <div>
        <h1>Sampling Gudang</h1>
        <p>Satu periode dapat mengambil stok dari beberapa database dan beberapa gudang sekaligus.</p>
    </div>
    <a class="btn" href="{{ route('warehouse.history.index') }}">History Sampling</a>
</div>

<div class="ws-grid">
    <section class="panel ws-home-panel ws-create-period-panel">
        <div class="ws-home-panel-head">
            <div>
                <h2>Buat Periode Multi-Gudang</h2>
                <p class="ws-note">Pilih gudang, tentukan checker, lalu buat periode sampling.</p>
            </div>
        </div>
        <p class="ws-note ws-create-description">Pilih satu atau lebih gudang dari AS_INGCO / AS_SMI. Stok setiap gudang akan disnapshot terpisah saat item dipilih.</p>

        <form method="POST" action="{{ route('warehouse.admin.store') }}" class="stack-form" id="ws-create-form">
            @csrf

            <div class="ws-multi-warehouse-picker" id="ws-multi-warehouse-picker"
                 data-url="{{ route('warehouse.admin.warehouses') }}"
                 data-databases='@json($databases)'
                 data-selected='@json(array_values((array) old('warehouse_keys', [])))'>
                <div class="ws-picker-head">
                    <div>
                        <strong>Database & Gudang yang Digabung</strong>
                        <small>Checker akan menghitung fisik gabungan dari seluruh gudang yang dipilih.</small>
                    </div>
                    <span class="ws-selected-warehouse-count"><b id="ws-selected-warehouse-count">0</b> gudang dipilih</span>
                </div>

                <div id="ws-warehouse-groups" class="ws-warehouse-groups">
                    <div class="empty compact">Memuat daftar gudang...</div>
                </div>
            </div>

            <div class="ws-form-grid">
                <label>
                    Target Checker (%)
                    <input name="target_percentage" type="number" min="1" max="100" step="0.01" value="{{ old('target_percentage',100) }}" required>
                </label>
                <label>
                    Checker Gudang
                    <select name="assigned_checker_id" required>
                        <option value="">Pilih checker</option>
                        @foreach($checkers as $checker)
                            <option value="{{ $checker->id }}" @selected((string)old('assigned_checker_id')===(string)$checker->id)>
                                {{ $checker->name }} · {{ $checker->username }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <label>
                Area / Lokasi Fisik Gabungan
                <input name="location" value="{{ old('location') }}" maxlength="255" placeholder="Contoh: Area fisik Gudang Medan / Rak A-C" required>
            </label>

            <label>
                Catatan
                <textarea name="notes" rows="3" placeholder="Opsional">{{ old('notes') }}</textarea>
            </label>

            <button class="btn primary" type="submit">Buat Periode & Pilih Item</button>
        </form>
    </section>

    <section class="panel ws-home-panel ws-period-panel">
        <div class="panel-head ws-home-panel-head ws-period-panel-head">
            <h2>Daftar Periode</h2>
            <span class="ws-note">{{ number_format($periods->total()) }} periode</span>
        </div>

        <div class="ws-period-scroll">
        @forelse($periods as $period)
            @php
                $pct = $period->items_count > 0 ? min(100, ($period->checked_items_count / $period->items_count) * 100) : 0;
                $targetCount = $period->items_count > 0 ? (int) ceil($period->items_count * ((float)$period->target_percentage / 100)) : 0;
                $reached = $period->checked_items_count >= $targetCount && $targetCount > 0;
                $warehouseLabels = $period->warehouses->map(fn($wh) => $wh->source_database.' · '.$wh->warehouse_code);
            @endphp

            <article class="ws-period-card">
                <div class="ws-period-head">
                    <div>
                        <strong>{{ $period->cycle_no }}</strong>
                        <small>{{ $period->warehouses->count() }} gudang · {{ $period->location }}</small>
                    </div>
                    <span class="ws-badge {{ strtolower($period->status) }}">{{ $period->status }}</span>
                </div>

                <div class="ws-period-warehouses">
                    @foreach($warehouseLabels->take(4) as $label)
                        <span>{{ $label }}</span>
                    @endforeach
                    @if($warehouseLabels->count() > 4)
                        <span>+{{ $warehouseLabels->count()-4 }} lainnya</span>
                    @endif
                </div>

                <div class="ws-period-meta">
                    <span><b>{{ $period->items_count }}</b>Item sampling</span>
                    <span><b>{{ $period->checked_items_count }}</b>Sudah dihitung</span>
                    <span><b>{{ $period->validated_items_count }}</b>Sudah divalidasi</span>
                </div>

                <div>
                    <div class="ws-progress {{ $reached ? 'target-reached':'' }}"><span style="width:{{ $pct }}%"></span></div>
                    <small class="ws-note">Progress checker {{ number_format($pct,1) }}% · target {{ number_format((float)$period->target_percentage,0) }}% ({{ $targetCount }} item) · {{ $period->checker?->name ?? '-' }}</small>
                </div>

                <a class="btn" href="{{ route('warehouse.admin.show',$period) }}">Kelola & Validasi</a>
            </article>
        @empty
            <div class="empty">Belum ada periode sampling gudang.</div>
        @endforelse
        </div>

        <div class="ws-period-pagination">{{ $periods->links() }}</div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('ws-multi-warehouse-picker');
    if (!root) return;

    const url = root.dataset.url;
    const databases = JSON.parse(root.dataset.databases || '[]');
    const selected = new Set(JSON.parse(root.dataset.selected || '[]').map(String));
    const groups = document.getElementById('ws-warehouse-groups');
    const counter = document.getElementById('ws-selected-warehouse-count');

    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

    function syncCounter() {
        counter.textContent = root.querySelectorAll('input[name="warehouse_keys[]"]:checked').length;
    }

    async function loadDatabase(source) {
        const response = await fetch(`${url}?source_database=${encodeURIComponent(source)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await response.json();
        if (!response.ok) throw new Error(json?.message || `Gagal memuat gudang ${source}`);
        return json.data || [];
    }

    try {
        const all = await Promise.all(databases.map(async source => ({ source, rows: await loadDatabase(source) })));
        groups.innerHTML = '';

        all.forEach(({source, rows}) => {
            const section = document.createElement('section');
            section.className = 'ws-warehouse-group';
            section.dataset.database = source;
            section.innerHTML = `
                <div class="ws-warehouse-group-head">
                    <div>
                        <strong>${esc(source)}</strong>
                        <small><span data-visible-count>${rows.length}</span> dari ${rows.length} gudang tampil</small>
                    </div>
                    <label><input type="checkbox" data-select-db="${esc(source)}"> Pilih tampil</label>
                </div>
                <div class="ws-db-search">
                    <span class="ws-db-search-icon">⌕</span>
                    <input type="search" data-db-search placeholder="Cari gudang di ${esc(source)}..." autocomplete="off" aria-label="Cari gudang ${esc(source)}">
                    <button type="button" class="ws-db-search-clear" data-db-search-clear hidden>×</button>
                </div>
                <div class="ws-warehouse-options"></div>
                <div class="ws-warehouse-empty" hidden>Tidak ada gudang yang cocok.</div>`;

            const options = section.querySelector('.ws-warehouse-options');
            rows.forEach(warehouse => {
                const key = `${source}|${warehouse.warehouse_id}`;
                const label = document.createElement('label');
                label.className = 'ws-warehouse-check';
                label.dataset.search = `${warehouse.warehouse_code || ''} ${warehouse.warehouse_name || ''}`.toLowerCase();
                label.innerHTML = `
                    <input type="checkbox" name="warehouse_keys[]" value="${esc(key)}" ${selected.has(key) ? 'checked' : ''}>
                    <span class="ws-warehouse-text">
                        <strong>${esc(warehouse.warehouse_code)}</strong>
                        <small>${esc(warehouse.warehouse_name)}</small>
                    </span>`;
                options.appendChild(label);
            });

            const search = section.querySelector('[data-db-search]');
            const clear = section.querySelector('[data-db-search-clear]');
            const selectAll = section.querySelector('[data-select-db]');
            const visibleCounter = section.querySelector('[data-visible-count]');
            const emptyState = section.querySelector('.ws-warehouse-empty');
            const checkboxes = [...section.querySelectorAll('input[name="warehouse_keys[]"]')];

            const visibleLabels = () => [...section.querySelectorAll('.ws-warehouse-check')].filter(label => !label.hidden);
            const visibleCheckboxes = () => visibleLabels().map(label => label.querySelector('input[name="warehouse_keys[]"]'));

            const syncDb = () => {
                const visible = visibleCheckboxes();
                selectAll.disabled = visible.length === 0;
                selectAll.checked = visible.length > 0 && visible.every(x => x.checked);
                selectAll.indeterminate = visible.some(x => x.checked) && !selectAll.checked;
                syncCounter();
            };

            const applyFilter = () => {
                const term = String(search.value || '').trim().toLowerCase();
                let visibleCount = 0;
                section.querySelectorAll('.ws-warehouse-check').forEach(label => {
                    const show = term === '' || String(label.dataset.search || '').includes(term);

                    // Do not rely only on the HTML `hidden` attribute here.
                    // `.ws-warehouse-check` is explicitly rendered as a grid in our CSS,
                    // which can override the browser's default hidden presentation.
                    // This class is therefore the authoritative visual filter.
                    label.classList.toggle('ws-filter-hidden', !show);
                    label.hidden = !show;
                    label.setAttribute('aria-hidden', show ? 'false' : 'true');

                    if (show) visibleCount += 1;
                });
                visibleCounter.textContent = visibleCount;
                emptyState.hidden = visibleCount !== 0;
                clear.hidden = term === '';

                // Start each filtered result from the top of its database list.
                const optionsScroller = section.querySelector('.ws-warehouse-options');
                if (optionsScroller) optionsScroller.scrollTop = 0;

                syncDb();
            };

            selectAll.addEventListener('change', () => {
                visibleCheckboxes().forEach(x => x.checked = selectAll.checked);
                syncDb();
            });
            checkboxes.forEach(x => x.addEventListener('change', syncDb));
            search.addEventListener('input', applyFilter);
            clear.addEventListener('click', () => {
                search.value = '';
                applyFilter();
                search.focus();
            });

            groups.appendChild(section);
            applyFilter();
        });

        syncCounter();
    } catch (error) {
        groups.innerHTML = `<div class="alert danger">${esc(error.message || 'Gagal memuat daftar gudang.')}</div>`;
    }
});
</script>
@endpush
