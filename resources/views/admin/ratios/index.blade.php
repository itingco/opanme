@extends('layouts.app')
@section('title','Master Ratio')

@section('content')
@php
    $sortUrl = function (string $column) use ($sort, $direction) {
        $params = request()->except('page');
        $params['sort'] = $column;
        $params['direction'] = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
        return route('admin.ratios.index', $params);
    };
    $sortMark = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕';
    $hasFilters = $q !== '' || $sourceDatabase !== '' || $uomLevel !== '';
@endphp

<div class="page-heading master-page-heading">
    <div>
        <div class="heading-eyebrow">Master Data</div>
        <h1>Master UOM Ratio</h1>
        <p>Kelola konversi hasil scan ke smallest UOM untuk setiap item dan database.</p>
    </div>
    <div class="heading-actions">
        <a class="btn" href="{{ route('admin.ratios.template') }}">Download Template</a>
        <button class="btn primary" type="button" id="ratio-create-open"><span class="btn-icon">+</span> Tambah Ratio</button>
    </div>
</div>

@if(session('ratio_import_result'))
    @php($importResult = session('ratio_import_result'))
    <section class="panel import-result-panel">
        <div class="panel-head import-result-head">
            <div>
                <h2>Hasil Import Ratio</h2>
                <p>{{ number_format($importResult['total_rows']) }} baris data diperiksa.</p>
            </div>
            <div class="import-metrics">
                <span><strong>{{ number_format($importResult['created']) }}</strong> Baru</span>
                <span><strong>{{ number_format($importResult['updated']) }}</strong> Update</span>
                <span><strong>{{ number_format($importResult['skipped']) }}</strong> Duplikat</span>
                <span class="{{ $importResult['failed'] > 0 ? 'has-error' : '' }}"><strong>{{ number_format($importResult['failed']) }}</strong> Gagal</span>
            </div>
        </div>

        @if(!empty($importResult['errors']))
            <details class="import-error-details">
                <summary>Lihat detail baris gagal</summary>
                <div class="table-wrap compact-table">
                    <table>
                        <thead><tr><th>Row</th><th>Barcode</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            @foreach($importResult['errors'] as $error)
                                <tr><td>{{ $error['row'] }}</td><td>{{ $error['barcode'] ?: '-' }}</td><td>{{ $error['message'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </section>
@endif

<section class="panel ratio-import-panel ratio-import-compact">
    <div class="ratio-import-copy">
        <div class="import-icon">XLSX</div>
        <div>
            <h2>Import Ratio dari Excel</h2>
            <p>Upload format <strong>Database + Barcode ERP + Ratio</strong>. Item dan UOM akan dicocokkan otomatis.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.ratios.import') }}" enctype="multipart/form-data" class="ratio-import-form ratio-import-inline">
        @csrf
        <input type="file" name="ratio_file" accept=".xlsx,.csv" required>
        <button class="btn" type="submit">Import Excel</button>
    </form>
</section>

<section class="panel master-table-panel">
    <div class="master-table-head">
        <div>
            <h2>Data Ratio</h2>
            <p>{{ number_format($ratios->total()) }} ratio ditemukan</p>
        </div>
        @if($hasFilters)<span class="filter-active-badge">Filter aktif</span>@endif
    </div>

    <form method="GET" action="{{ route('admin.ratios.index') }}" class="table-filter-bar ratio-filter-bar" id="ratio-filter-form">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">

        <label class="filter-search filter-field-wide">
            <span class="filter-label">Cari Item</span>
            <div class="filter-input-with-icon">
                <span aria-hidden="true">⌕</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="Item code, nama atau UOM..." autocomplete="off">
            </div>
        </label>

        <label class="filter-field">
            <span class="filter-label">Database</span>
            <select name="source_database">
                <option value="">Semua DB</option>
                @foreach($databases as $db)
                    <option value="{{ $db }}" @selected($sourceDatabase === $db)>{{ $db }}</option>
                @endforeach
            </select>
        </label>

        <label class="filter-field">
            <span class="filter-label">UOM Level</span>
            <select name="uom_level">
                <option value="">Semua Level</option>
                @foreach([1,2,3,4] as $level)
                    <option value="{{ $level }}" @selected($uomLevel === (string)$level)>Level {{ $level }}</option>
                @endforeach
            </select>
        </label>

        <label class="filter-field filter-per-page">
            <span class="filter-label">Baris</span>
            <select name="per_page">
                @foreach([10,25,50,100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </label>

        <div class="filter-actions">
            <button class="btn primary" type="submit">Terapkan</button>
            <a class="btn filter-reset-btn {{ $hasFilters ? '' : 'muted-button' }}" href="{{ route('admin.ratios.index') }}">Reset</a>
        </div>
    </form>

    <div class="table-wrap master-table-wrap">
        <table class="master-table ratio-table">
            <thead>
                <tr>
                    <th class="row-number-col">No</th>
                    <th><a class="sortable-th" data-sort="database" href="{{ $sortUrl('database') }}">Database <span>{{ $sortMark('database') }}</span></a></th>
                    <th><a class="sortable-th" data-sort="item_code" href="{{ $sortUrl('item_code') }}">Item <span>{{ $sortMark('item_code') }}</span></a></th>
                    <th><a class="sortable-th" data-sort="uom_level" href="{{ $sortUrl('uom_level') }}">UOM <span>{{ $sortMark('uom_level') }}</span></a></th>
                    <th class="num"><a class="sortable-th align-right" data-sort="ratio" href="{{ $sortUrl('ratio') }}">Ratio <span>{{ $sortMark('ratio') }}</span></a></th>
                    <th><a class="sortable-th" data-sort="updated_at" href="{{ $sortUrl('updated_at') }}">Update <span>{{ $sortMark('updated_at') }}</span></a></th>
                    <th class="action-col">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($ratios as $ratio)
                <tr>
                    <td data-label="No" class="row-number">{{ number_format(($ratios->firstItem() ?? 1) + $loop->index) }}</td>
                    <td data-label="Database"><span class="database-badge">{{ $ratio->source_database }}</span></td>
                    <td data-label="Item">
                        <div class="item-cell">
                            <strong>{{ $ratio->item_code }}</strong>
                            <small>{{ $ratio->item_name ?: '-' }}</small>
                        </div>
                    </td>
                    <td data-label="UOM">
                        <span class="uom-chip"><strong>{{ $ratio->uom_code }}</strong><small>Level {{ $ratio->uom_level }}</small></span>
                    </td>
                    <td data-label="Ratio" class="num ratio-value">{{ rtrim(rtrim(number_format((float)$ratio->ratio,4,'.',','),'0'),'.') }}</td>
                    <td data-label="Update" class="updated-cell">
                        <strong>{{ optional($ratio->updated_at)->format('d M Y') ?: '-' }}</strong>
                        <small>{{ optional($ratio->updated_at)->format('H:i') }}</small>
                    </td>
                    <td data-label="Aksi" class="action-cell">
                        <div class="row-actions">
                            <button
                                type="button"
                                class="btn small"
                                data-ratio-edit
                                data-db="{{ $ratio->source_database }}"
                                data-item-id="{{ $ratio->item_id }}"
                                data-item-code="{{ $ratio->item_code }}"
                                data-item-name="{{ $ratio->item_name }}"
                                data-uom-level="{{ $ratio->uom_level }}"
                                data-uom-code="{{ $ratio->uom_code }}"
                                data-ratio="{{ $ratio->ratio }}"
                            >Edit</button>
                            <form method="POST" action="{{ route('admin.ratios.destroy',$ratio) }}" onsubmit="return confirm('Hapus ratio {{ $ratio->item_code }} - {{ $ratio->uom_code }}?')">
                                @csrf @method('DELETE')
                                <button class="btn small table-danger-btn" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty table-empty-state">
                        <strong>{{ $hasFilters ? 'Ratio tidak ditemukan' : 'Belum ada data ratio' }}</strong>
                        <span>{{ $hasFilters ? 'Ubah kata kunci/filter atau tekan Reset.' : 'Tambah ratio manual atau import dari Excel.' }}</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.table-pagination', ['paginator' => $ratios])
</section>

<div class="user-modal" id="ratio-modal" hidden aria-hidden="true">
    <div class="user-modal-backdrop" data-ratio-close></div>
    <div class="user-modal-dialog ratio-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ratio-modal-title">
        <div class="user-modal-head">
            <div>
                <span class="modal-eyebrow">Master Ratio</span>
                <h2 id="ratio-modal-title">Tambah Ratio</h2>
                <p id="ratio-modal-description">Cari barcode ERP untuk mengisi item dan UOM secara otomatis.</p>
            </div>
            <button type="button" class="user-modal-close" data-ratio-close aria-label="Tutup modal">×</button>
        </div>

        <form method="POST" action="{{ route('admin.ratios.store') }}" class="stack-form" id="ratio-form">
            @csrf
            <label>Database
                <select name="source_database" id="ratio-db">
                    @foreach($databases as $db)<option value="{{ $db }}">{{ $db }}</option>@endforeach
                </select>
                <input type="hidden" name="source_database" id="ratio-db-hidden" disabled>
            </label>
            <label id="ratio-barcode-field">Barcode ERP
                <div class="input-action">
                    <input id="ratio-barcode" autocomplete="off" placeholder="Scan / masukkan barcode">
                    <button class="btn" type="button" id="ratio-lookup">Cari</button>
                </div>
            </label>
            <div id="ratio-found" class="lookup-result muted">Cari barcode untuk mengisi data item otomatis.</div>
            <input type="hidden" name="item_id" id="ratio-item-id">
            <input type="hidden" name="item_code" id="ratio-item-code">
            <input type="hidden" name="item_name" id="ratio-item-name">
            <input type="hidden" name="uom_level" id="ratio-uom-level">
            <input type="hidden" name="uom_code" id="ratio-uom-code">
            <label>Ratio ke Smallest UOM
                <input type="number" name="ratio" id="ratio-value" step="0.0001" min="0.0001" required placeholder="Contoh: 20">
            </label>
            <div class="user-modal-actions">
                <button type="button" class="btn" data-ratio-close>Batal</button>
                <button class="btn primary" type="submit">Simpan Ratio</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('ratio-modal');
    const title = document.getElementById('ratio-modal-title');
    const description = document.getElementById('ratio-modal-description');
    const dbInput = document.getElementById('ratio-db');
    const dbHidden = document.getElementById('ratio-db-hidden');
    const barcodeField = document.getElementById('ratio-barcode-field');
    const barcodeInput = document.getElementById('ratio-barcode');
    const ratioInput = document.getElementById('ratio-value');
    const foundBox = document.getElementById('ratio-found');
    const itemId = document.getElementById('ratio-item-id');
    const itemCode = document.getElementById('ratio-item-code');
    const itemName = document.getElementById('ratio-item-name');
    const uomLevel = document.getElementById('ratio-uom-level');
    const uomCode = document.getElementById('ratio-uom-code');
    let lastTrigger = null;

    function clearItem() {
        itemId.value = '';
        itemCode.value = '';
        itemName.value = '';
        uomLevel.value = '';
        uomCode.value = '';
    }

    function showFound(code, name, uom, level) {
        foundBox.className = 'lookup-result success-lite ratio-selected-item';
        foundBox.innerHTML = `<span>Item terpilih</span><strong>${code}</strong><small>${name || '-'}</small><em>${uom} · Level ${level}</em>`;
    }

    function openNew(trigger) {
        lastTrigger = trigger;
        title.textContent = 'Tambah Ratio';
        description.textContent = 'Cari barcode ERP untuk mengisi item dan UOM secara otomatis.';
        document.getElementById('ratio-form').reset();
        dbInput.disabled = false;
        dbHidden.disabled = true;
        dbHidden.value = '';
        barcodeField.hidden = false;
        clearItem();
        foundBox.className = 'lookup-result muted';
        foundBox.textContent = 'Cari barcode untuk mengisi data item otomatis.';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        setTimeout(() => barcodeInput.focus(), 20);
    }

    function openEdit(button) {
        lastTrigger = button;
        title.textContent = 'Edit Ratio';
        description.textContent = 'Perbarui nilai ratio untuk item dan UOM yang dipilih.';
        dbInput.value = button.dataset.db;
        dbInput.disabled = true;
        dbHidden.disabled = false;
        dbHidden.value = button.dataset.db;
        barcodeField.hidden = true;
        barcodeInput.value = '';
        itemId.value = button.dataset.itemId;
        itemCode.value = button.dataset.itemCode;
        itemName.value = button.dataset.itemName || '';
        uomLevel.value = button.dataset.uomLevel;
        uomCode.value = button.dataset.uomCode;
        ratioInput.value = button.dataset.ratio;
        showFound(button.dataset.itemCode, button.dataset.itemName, button.dataset.uomCode, button.dataset.uomLevel);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        setTimeout(() => ratioInput.focus(), 20);
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        lastTrigger?.focus();
    }

    document.getElementById('ratio-create-open')?.addEventListener('click', event => openNew(event.currentTarget));
    document.querySelectorAll('[data-ratio-edit]').forEach(button => button.addEventListener('click', () => openEdit(button)));
    modal.querySelectorAll('[data-ratio-close]').forEach(el => el.addEventListener('click', closeModal));

    document.getElementById('ratio-lookup')?.addEventListener('click', async () => {
        const db = dbInput.value;
        const barcode = barcodeInput.value.trim();
        if (!barcode) {
            foundBox.className = 'lookup-result danger-lite';
            foundBox.textContent = 'Isi barcode terlebih dahulu.';
            return;
        }

        foundBox.className = 'lookup-result muted';
        foundBox.textContent = 'Mencari barcode...';
        try {
            const response = await fetch(`{{ route('admin.erp.barcode') }}?source_database=${encodeURIComponent(db)}&barcode=${encodeURIComponent(barcode)}`, {
                headers: {'Accept':'application/json'}
            });
            const json = await response.json();
            if (!response.ok) throw new Error(json.message || 'Gagal mencari barcode');

            const data = json.data;
            itemId.value = data.item_id;
            itemCode.value = data.item_code;
            itemName.value = data.item_name;
            uomLevel.value = data.uom_level;
            uomCode.value = data.uom_code;
            showFound(data.item_code, data.item_name, data.uom_code, data.uom_level);
            ratioInput.focus();
        } catch (error) {
            clearItem();
            foundBox.className = 'lookup-result danger-lite';
            foundBox.textContent = error.message;
        }
    });

    dbInput.addEventListener('change', () => {
        if (!modal.hidden && title.textContent === 'Tambah Ratio') {
            clearItem();
            foundBox.className = 'lookup-result muted';
            foundBox.textContent = 'Database berubah. Cari ulang barcode untuk memilih item.';
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    document.querySelectorAll('#ratio-filter-form select').forEach(select => {
        select.addEventListener('change', () => document.getElementById('ratio-filter-form').requestSubmit());
    });
});
</script>
@endpush
