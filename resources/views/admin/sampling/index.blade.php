@extends('layouts.app')
@section('title','Laporan Sampling')
@section('content')
<div class="page-heading"><div><h1>Laporan Sampling Gerai</h1><p>Audit sampling harian dan progres target opname 100% barang berstok dalam periode.</p></div><a class="btn primary" href="{{ route('admin.sampling.pdf', request()->query()) }}">Export PDF</a></div>
<section class="panel sampling-filter-panel">
    <div class="sampling-filter-head">
        <div>
            <span class="sampling-filter-eyebrow">Filter Laporan</span>
            <h2>Temukan data sampling lebih cepat</h2>
            <p>Pilih periode, satu atau beberapa gudang, user, hasil, atau cari item tertentu.</p>
        </div>
        @php
            $selectedWarehouseIds = $filters['erp_warehouse_ids'] ?? [];
            $activeFilterCount = collect([
                $filters['source_database'] ?? null,
                $selectedWarehouseIds !== [] ? $selectedWarehouseIds : null,
                $filters['user_id'] ?? null,
                $filters['result'] ?? null,
                $filters['location'] ?? null,
                $filters['q'] ?? null,
            ])->filter(fn ($value) => filled($value))->count();
        @endphp
        @if($activeFilterCount > 0)
            <span class="sampling-filter-count">{{ $activeFilterCount }} filter aktif</span>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.sampling.index') }}" class="sampling-filter-form" id="sampling-filter-form">
        <div class="sampling-period-group">
            <span class="sampling-group-label">Periode</span>
            <div class="sampling-period-fields">
                <label class="sampling-filter-field">
                    <span>Dari tanggal</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                </label>
                <span class="sampling-period-separator">s/d</span>
                <label class="sampling-filter-field">
                    <span>Sampai tanggal</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                </label>
            </div>
        </div>

        <div class="sampling-filter-grid">
            <label class="sampling-filter-field">
                <span>Database</span>
                <select name="source_database" id="report-source">
                    <option value="">Semua Database</option>
                    @foreach($databases as $db)
                        <option value="{{ $db }}" @selected($filters['source_database']===$db)>{{ $db }}</option>
                    @endforeach
                </select>
            </label>

            <label class="sampling-filter-field">
                <span>Gudang (bisa pilih multiple)</span>
                <select name="erp_warehouse_ids[]" id="report-warehouse" multiple size="6">
                    @foreach($warehouses as $w)
                        <option value="{{ $w['warehouse_id'] }}" @selected(in_array((int)$w['warehouse_id'], $selectedWarehouseIds, true))>{{ $w['warehouse_code'] }} · {{ $w['warehouse_name'] }}</option>
                    @endforeach
                </select>
                <small>Pilih lebih dari satu gudang dengan Ctrl + klik (Windows) / Cmd + klik (Mac).</small>
            </label>

            <div class="sampling-filter-field sampling-searchable-field" data-searchable-select="user">
                <span>User Gerai</span>
                <select name="user_id" id="report-user" class="sampling-native-select" tabindex="-1" aria-hidden="true">
                    <option value="">Semua User Gerai</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected($filters['user_id']===$u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                <div class="sampling-combobox">
                    <button type="button" class="sampling-combobox-trigger" aria-expanded="false">
                        <span class="sampling-combobox-value">Semua User Gerai</span>
                        <span class="sampling-combobox-chevron">⌄</span>
                    </button>
                    <div class="sampling-combobox-menu" hidden>
                        <div class="sampling-combobox-search-wrap">
                            <span>⌕</span>
                            <input type="search" class="sampling-combobox-search" placeholder="Cari nama user..." autocomplete="off">
                        </div>
                        <div class="sampling-combobox-options"></div>
                    </div>
                </div>
            </div>

            <label class="sampling-filter-field">
                <span>Hasil</span>
                <select name="result">
                    <option value="">Semua Hasil</option>
                    <option value="MATCH" @selected($filters['result']==='MATCH')>Cocok</option>
                    <option value="MISMATCH" @selected($filters['result']==='MISMATCH')>Tidak Cocok</option>
                </select>
            </label>

            <label class="sampling-filter-field">
                <span>Lokasi / Rak</span>
                <input name="location" value="{{ $filters['location'] }}" placeholder="Contoh: Rak A">
            </label>

            <label class="sampling-filter-field">
                <span>Cari Item</span>
                <div class="sampling-search-input">
                    <span>⌕</span>
                    <input name="q" value="{{ $filters['q'] }}" placeholder="Kode, nama, atau barcode">
                </div>
            </label>
        </div>

        <div class="sampling-filter-actions">
            <a class="btn sampling-reset-btn" href="{{ route('admin.sampling.index') }}">Reset</a>
            <button class="btn primary sampling-apply-btn" type="submit">Terapkan Filter</button>
        </div>
    </form>
</section>
@if(!empty($coverageError))<div class="alert danger">{{ $coverageError }}</div>@endif
<div class="summary-grid">
<section class="panel"><small>Total Sampling</small><h2>{{ number_format($stats['total']) }}</h2></section><section class="panel"><small>Item Unik</small><h2>{{ number_format($stats['unique_items']) }}</h2></section><section class="panel"><small>Cocok</small><h2>{{ number_format($stats['match']) }}</h2></section><section class="panel"><small>Tidak Cocok</small><h2>{{ number_format($stats['mismatch']) }}</h2></section><section class="panel"><small>User Aktif</small><h2>{{ number_format($stats['users']) }}</h2></section>
@if($coverage && !empty($coverage['warehouses']))
    @if(count($coverage['warehouses']) > 1)
        <section class="panel"><small>Coverage Gabungan</small><h2>{{ number_format($coverage['aggregate']['percentage'],2) }}%</h2><p>{{ number_format($coverage['aggregate']['completed']) }} / {{ number_format($coverage['aggregate']['target']) }} target gudang-item.</p></section>
    @endif
    @foreach($coverage['warehouses'] as $warehouseCoverage)
        <section class="panel">
            <small>Coverage {{ $warehouseCoverage['warehouse_code'] }}</small>
            <h2>{{ number_format($warehouseCoverage['percentage'],2) }}%</h2>
            <p>{{ $warehouseCoverage['warehouse_name'] }}<br>{{ number_format($warehouseCoverage['completed']) }} / {{ number_format($warehouseCoverage['target']) }} item. Target = item stok &gt; 0 per tanggal akhir filter.</p>
        </section>
    @endforeach
@endif
</div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Gudang</th><th>User</th><th>Lokasi</th><th>Item</th><th>Barcode</th><th class="num">Sistem</th><th class="num">Fisik</th><th class="num">Selisih</th><th>Hasil</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->scanned_at?->format('d/m/Y H:i:s') }}</td><td>{{ $r->warehouse_code }}</td><td>{{ $r->user?->name ?? $r->user_id }}</td><td>{{ $r->location }}</td><td><strong>{{ $r->item_code }}</strong><br><small>{{ $r->item_name }}</small></td><td>{{ $r->barcode }}</td><td class="num">{{ number_format((float)$r->system_qty,4,'.',',') }}</td><td class="num">{{ number_format((float)$r->physical_qty,4,'.',',') }}</td><td class="num">{{ number_format((float)$r->physical_qty-(float)$r->system_qty,4,'.',',') }}</td><td>{{ $r->result==='MATCH'?'Cocok':'Tidak Cocok' }}</td></tr>@empty<tr><td colspan="10" class="empty">Tidak ada hasil sampling pada filter ini.</td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}</section>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const source = document.getElementById('report-source');
    const warehouse = document.getElementById('report-warehouse');
    const warehouseUrl = @json(route('admin.erp.warehouses'));

    function initSearchableSelect(root) {
        if (!root) return null;

        const select = root.querySelector('select');
        const trigger = root.querySelector('.sampling-combobox-trigger');
        const valueLabel = root.querySelector('.sampling-combobox-value');
        const menu = root.querySelector('.sampling-combobox-menu');
        const search = root.querySelector('.sampling-combobox-search');
        const options = root.querySelector('.sampling-combobox-options');

        const close = () => {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            search.value = '';
        };

        const syncLabel = () => {
            const selected = select.options[select.selectedIndex];
            valueLabel.textContent = selected?.textContent?.trim() || select.options[0]?.textContent?.trim() || 'Pilih';
            trigger.classList.toggle('has-value', Boolean(select.value));
        };

        const render = () => {
            const term = search.value.trim().toLowerCase();
            options.innerHTML = '';
            let visible = 0;

            Array.from(select.options).forEach((option) => {
                const text = option.textContent.trim();
                if (term && !text.toLowerCase().includes(term)) return;

                visible += 1;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'sampling-combobox-option';
                if (option.value === select.value) button.classList.add('selected');
                const label = document.createElement('span');
                label.textContent = text;
                button.appendChild(label);
                if (option.value === select.value) {
                    const check = document.createElement('strong');
                    check.textContent = '✓';
                    button.appendChild(check);
                }
                button.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel();
                    close();
                });
                options.appendChild(button);
            });

            if (!visible) {
                const empty = document.createElement('div');
                empty.className = 'sampling-combobox-empty';
                empty.textContent = 'Tidak ada data yang cocok';
                options.appendChild(empty);
            }
        };

        trigger.addEventListener('click', () => {
            const willOpen = menu.hidden;
            document.querySelectorAll('.sampling-combobox-menu').forEach((other) => {
                if (other !== menu) other.hidden = true;
            });
            document.querySelectorAll('.sampling-combobox-trigger').forEach((other) => {
                if (other !== trigger) other.setAttribute('aria-expanded', 'false');
            });
            menu.hidden = !willOpen;
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) {
                render();
                requestAnimationFrame(() => search.focus());
            }
        });

        search.addEventListener('input', render);
        select.addEventListener('change', syncLabel);
        syncLabel();

        return { render, syncLabel, close };
    }

    initSearchableSelect(document.querySelector('[data-searchable-select="user"]'));

    source?.addEventListener('change', async () => {
        warehouse.innerHTML = '';
        if (!source.value) return;

        try {
            const response = await fetch(`${warehouseUrl}?source_database=${encodeURIComponent(source.value)}`, {
                headers: { Accept: 'application/json' },
            });
            const json = await response.json();
            (json.data || []).forEach((item) => {
                const option = document.createElement('option');
                option.value = item.warehouse_id;
                option.textContent = `${item.warehouse_code} · ${item.warehouse_name}`;
                warehouse.appendChild(option);
            });
        } catch (_) {
            warehouse.innerHTML = '';
        }
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('.sampling-searchable-field').forEach((root) => {
            if (root.contains(event.target)) return;
            const menu = root.querySelector('.sampling-combobox-menu');
            const trigger = root.querySelector('.sampling-combobox-trigger');
            if (menu) menu.hidden = true;
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    });
});
</script>
@endpush
