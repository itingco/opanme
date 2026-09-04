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
    $hasFilters = $q !== '';
@endphp

<div class="page-heading master-page-heading">
    <div>
        <div class="heading-eyebrow">Master Data</div>
        <h1>Master UOM Ratio</h1>
        <p>Berlaku untuk AS_INGCO dan AS_SMI. Satu ratio disimpan berdasarkan ItemCode + UOM.</p>
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
                        <thead><tr><th>Row</th><th>ItemCode</th><th>UOM</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            @foreach($importResult['errors'] as $error)
                                <tr>
                                    <td>{{ $error['row'] }}</td>
                                    <td>{{ $error['item_code'] ?: '-' }}</td>
                                    <td>{{ $error['uom_code'] ?: '-' }}</td>
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
            <h2>Import Ratio dari Excel</h2>
            <p>Upload format <strong>ItemCode + UOM + Ratio</strong>. Tidak perlu database dan tidak perlu barcode.</p>
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
            <span class="filter-label">Cari Item / UOM</span>
            <div class="filter-input-with-icon">
                <span aria-hidden="true">⌕</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="Contoh: CHPTB8703 atau KTK" autocomplete="off">
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
            <a class="btn filter-reset-btn {{ $hasFilters ? '' : 'muted-button' }}" href="{{ route('admin.ratios.index') }}">Reset</a>
        </div>
    </form>

    <div class="table-wrap master-table-wrap">
        <table class="master-table ratio-table">
            <thead>
                <tr>
                    <th class="row-number-col">No</th>
                    <th><a class="sortable-th" href="{{ $sortUrl('item_code') }}">ItemCode <span>{{ $sortMark('item_code') }}</span></a></th>
                    <th><a class="sortable-th" href="{{ $sortUrl('uom_code') }}">UOM <span>{{ $sortMark('uom_code') }}</span></a></th>
                    <th class="num"><a class="sortable-th align-right" href="{{ $sortUrl('ratio') }}">Ratio <span>{{ $sortMark('ratio') }}</span></a></th>
                    <th><a class="sortable-th" href="{{ $sortUrl('updated_at') }}">Update <span>{{ $sortMark('updated_at') }}</span></a></th>
                    <th class="action-col">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($ratios as $ratio)
                <tr>
                    <td data-label="No" class="row-number">{{ number_format(($ratios->firstItem() ?? 1) + $loop->index) }}</td>
                    <td data-label="ItemCode"><strong>{{ $ratio->item_code }}</strong></td>
                    <td data-label="UOM"><span class="uom-chip"><strong>{{ $ratio->uom_code }}</strong></span></td>
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
                                data-item-code="{{ $ratio->item_code }}"
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
                    <td colspan="6" class="empty table-empty-state">
                        <strong>{{ $hasFilters ? 'Ratio tidak ditemukan' : 'Belum ada data ratio' }}</strong>
                        <span>{{ $hasFilters ? 'Ubah kata kunci atau tekan Reset.' : 'Tambah ratio manual atau import dari Excel.' }}</span>
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
                <p>Ratio selalu dihitung ke smallest UOM item tersebut.</p>
            </div>
            <button type="button" class="user-modal-close" data-ratio-close aria-label="Tutup modal">×</button>
        </div>

        <form method="POST" action="{{ route('admin.ratios.store') }}" class="stack-form" id="ratio-form">
            @csrf
            <label>ItemCode
                <input type="text" name="item_code" id="ratio-item-code" required maxlength="100" placeholder="Contoh: CHPTB8703">
            </label>
            <label>UOM
                <input type="text" name="uom_code" id="ratio-uom-code" required maxlength="50" placeholder="Contoh: PCS / KTK / KRTN">
            </label>
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
    const itemCode = document.getElementById('ratio-item-code');
    const uomCode = document.getElementById('ratio-uom-code');
    const ratioValue = document.getElementById('ratio-value');

    function openModal(editButton) {
        const isEdit = !!editButton;
        title.textContent = isEdit ? 'Edit Ratio' : 'Tambah Ratio';
        itemCode.value = isEdit ? editButton.dataset.itemCode : '';
        uomCode.value = isEdit ? editButton.dataset.uomCode : '';
        ratioValue.value = isEdit ? editButton.dataset.ratio : '';
        itemCode.readOnly = isEdit;
        uomCode.readOnly = isEdit;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => (isEdit ? ratioValue : itemCode).focus(), 0);
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('ratio-create-open')?.addEventListener('click', () => openModal(null));
    document.querySelectorAll('[data-ratio-edit]').forEach(button => button.addEventListener('click', () => openModal(button)));
    document.querySelectorAll('[data-ratio-close]').forEach(button => button.addEventListener('click', closeModal));

    document.getElementById('ratio-form')?.addEventListener('submit', function () {
        itemCode.value = itemCode.value.trim().toUpperCase();
        uomCode.value = uomCode.value.trim().toUpperCase();
    });
});
</script>
@endpush
