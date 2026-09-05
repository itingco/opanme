@extends('layouts.app')
@section('title','Master Barcode')

@section('content')
@php
    $sortUrl = function (string $column) use ($sort, $direction) {
        $params = request()->except('page');
        $params['sort'] = $column;
        $params['direction'] = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
        return route('admin.barcodes.index', $params);
    };
    $sortMark = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕';
    $hasFilters = $q !== '';
@endphp

<div class="page-heading master-page-heading">
    <div>
        <div class="heading-eyebrow">Master Data</div>
        <h1>Master Barcode</h1>
        <p>Barcode disimpan lokal. Satu barcode hanya boleh menunjuk ke satu ItemCode + satu UOM.</p>
    </div>
    <div class="heading-actions">
        <a class="btn" href="{{ route('admin.barcodes.template') }}">Download Template</a>
        <a class="btn" href="{{ route('admin.barcodes.export') }}">Export Excel</a>
        <button class="btn primary" type="button" id="barcode-create-open"><span class="btn-icon">+</span> Tambah Barcode</button>
    </div>
</div>

@if(session('barcode_import_result'))
    @php($importResult = session('barcode_import_result'))
    <section class="panel import-result-panel">
        <div class="panel-head import-result-head">
            <div>
                <h2>Hasil Import Barcode</h2>
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
                                <tr>
                                    <td>{{ $error['row'] }}</td>
                                    <td>{{ $error['barcode'] ?: '-' }}</td>
                                    <td>{{ $error['message'] }}</td>
                                </tr>
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
            <h2>Import Barcode dari Excel</h2>
            <p>Upload format <strong>ItemCode + Barcode + UOM</strong>. Barcode tidak lagi diambil dari IC_Aliases saat scan.</p>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.barcodes.import') }}" enctype="multipart/form-data" class="ratio-import-form ratio-import-inline">
        @csrf
        <input type="file" name="barcode_file" accept=".xlsx,.csv" required>
        <button class="btn" type="submit">Import Excel</button>
    </form>
</section>

<section class="panel master-table-panel">
    <div class="master-table-head">
        <div>
            <h2>Data Barcode</h2>
            <p>{{ number_format($barcodes->total()) }} barcode ditemukan</p>
        </div>
        @if($hasFilters)<span class="filter-active-badge">Filter aktif</span>@endif
    </div>

    <form method="GET" action="{{ route('admin.barcodes.index') }}" class="table-filter-bar ratio-filter-bar">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">

        <label class="filter-search filter-field-wide">
            <span class="filter-label">Cari Barcode / Item / UOM</span>
            <div class="filter-input-with-icon">
                <span aria-hidden="true">⌕</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="Contoh: 899..., CHPTB8703 atau KTK" autocomplete="off">
            </div>
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
            <a class="btn filter-reset-btn {{ $hasFilters ? '' : 'muted-button' }}" href="{{ route('admin.barcodes.index') }}">Reset</a>
        </div>
    </form>

    <div class="table-wrap master-table-wrap">
        <table class="master-table ratio-table">
            <thead>
                <tr>
                    <th class="row-number-col">No</th>
                    <th><a class="sortable-th" href="{{ $sortUrl('item_code') }}">ItemCode <span>{{ $sortMark('item_code') }}</span></a></th>
                    <th><a class="sortable-th" href="{{ $sortUrl('barcode') }}">Barcode <span>{{ $sortMark('barcode') }}</span></a></th>
                    <th><a class="sortable-th" href="{{ $sortUrl('uom_code') }}">UOM <span>{{ $sortMark('uom_code') }}</span></a></th>
                    <th><a class="sortable-th" href="{{ $sortUrl('updated_at') }}">Update <span>{{ $sortMark('updated_at') }}</span></a></th>
                    <th class="action-col">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($barcodes as $barcode)
                <tr>
                    <td data-label="No" class="row-number">{{ number_format(($barcodes->firstItem() ?? 1) + $loop->index) }}</td>
                    <td data-label="ItemCode"><strong>{{ $barcode->item_code }}</strong></td>
                    <td data-label="Barcode"><strong>{{ $barcode->barcode }}</strong></td>
                    <td data-label="UOM"><span class="uom-chip"><strong>{{ $barcode->uom_code }}</strong></span></td>
                    <td data-label="Update" class="updated-cell">
                        <strong>{{ optional($barcode->updated_at)->format('d M Y') ?: '-' }}</strong>
                        <small>{{ optional($barcode->updated_at)->format('H:i') }}</small>
                    </td>
                    <td data-label="Aksi" class="action-cell">
                        <div class="row-actions">
                            <button
                                type="button"
                                class="btn small"
                                data-barcode-edit
                                data-item-code="{{ $barcode->item_code }}"
                                data-barcode="{{ $barcode->barcode }}"
                                data-uom-code="{{ $barcode->uom_code }}"
                            >Edit</button>
                            <form method="POST" action="{{ route('admin.barcodes.destroy',$barcode) }}" onsubmit="return confirm('Hapus barcode {{ $barcode->barcode }}?')">
                                @csrf @method('DELETE')
                                <button class="btn small table-danger-btn" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty table-empty-state">
                        <strong>{{ $hasFilters ? 'Barcode tidak ditemukan' : 'Belum ada data barcode' }}</strong>
                        <span>{{ $hasFilters ? 'Ubah kata kunci atau tekan Reset.' : 'Tambah barcode manual atau import dari Excel.' }}</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.table-pagination', ['paginator' => $barcodes])
</section>

<div class="user-modal" id="barcode-modal" hidden aria-hidden="true">
    <div class="user-modal-backdrop" data-barcode-close></div>
    <div class="user-modal-dialog ratio-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="barcode-modal-title">
        <div class="user-modal-head">
            <div>
                <span class="modal-eyebrow">Master Barcode</span>
                <h2 id="barcode-modal-title">Tambah Barcode</h2>
                <p>Satu barcode hanya boleh menunjuk ke satu ItemCode dan satu UOM.</p>
            </div>
            <button type="button" class="user-modal-close" data-barcode-close aria-label="Tutup modal">×</button>
        </div>

        <form method="POST" action="{{ route('admin.barcodes.store') }}" class="stack-form" id="barcode-form">
            @csrf
            <label>ItemCode
                <input type="text" name="item_code" id="barcode-item-code" required maxlength="100" placeholder="Contoh: CHPTB8703">
            </label>
            <label>Barcode
                <input type="text" name="barcode" id="barcode-value" required maxlength="150" placeholder="Scan / masukkan barcode" autocomplete="off">
            </label>
            <label>UOM / Satuan
                <input type="text" name="uom_code" id="barcode-uom-code" required maxlength="50" placeholder="Contoh: PCS / KTK / KRTN">
            </label>
            <div class="user-modal-actions">
                <button type="button" class="btn" data-barcode-close>Batal</button>
                <button class="btn primary" type="submit">Simpan Barcode</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('barcode-modal');
    const title = document.getElementById('barcode-modal-title');
    const itemCode = document.getElementById('barcode-item-code');
    const barcodeValue = document.getElementById('barcode-value');
    const uomCode = document.getElementById('barcode-uom-code');

    function openModal(editButton) {
        const isEdit = !!editButton;
        title.textContent = isEdit ? 'Edit Barcode' : 'Tambah Barcode';
        itemCode.value = isEdit ? editButton.dataset.itemCode : '';
        barcodeValue.value = isEdit ? editButton.dataset.barcode : '';
        uomCode.value = isEdit ? editButton.dataset.uomCode : '';
        barcodeValue.readOnly = isEdit;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => (isEdit ? itemCode : barcodeValue).focus(), 0);
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('barcode-create-open')?.addEventListener('click', () => openModal(null));
    document.querySelectorAll('[data-barcode-edit]').forEach(button => button.addEventListener('click', () => openModal(button)));
    document.querySelectorAll('[data-barcode-close]').forEach(button => button.addEventListener('click', closeModal));

    document.getElementById('barcode-form')?.addEventListener('submit', function () {
        itemCode.value = itemCode.value.trim().toUpperCase();
        barcodeValue.value = barcodeValue.value.trim();
        uomCode.value = uomCode.value.trim().toUpperCase();
    });
});
</script>
@endpush
