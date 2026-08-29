@extends('layouts.app')
@section('title','Master Ratio')

@section('content')
<div class="page-heading">
    <div>
        <h1>Master UOM Ratio</h1>
        <p>Satu scan dikalikan ratio ini untuk menghasilkan smallest UOM.</p>
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
                        <thead>
                            <tr><th>Row</th><th>Barcode</th><th>Keterangan</th></tr>
                        </thead>
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
                @if($importResult['failed'] > count($importResult['errors']))
                    <small>Ditampilkan {{ count($importResult['errors']) }} dari {{ number_format($importResult['failed']) }} error.</small>
                @endif
            </details>
        @endif
    </section>
@endif

<section class="panel ratio-import-panel">
    <div class="ratio-import-copy">
        <h2>Import Ratio dari Excel</h2>
        <p>Gunakan format <strong>Database + Barcode ERP + Ratio</strong>. Sistem akan mencari Item dan UOM dari IC_Aliases secara otomatis.</p>
    </div>
    <div class="ratio-import-actions">
        <a class="btn" href="{{ route('admin.ratios.template') }}">Download Template Excel</a>
        <form method="POST" action="{{ route('admin.ratios.import') }}" enctype="multipart/form-data" class="ratio-import-form">
            @csrf
            <input type="file" name="ratio_file" accept=".xlsx,.csv" required>
            <button class="btn primary" type="submit">Import Ratio</button>
        </form>
    </div>
    <div class="ratio-import-note">
        <strong>Aturan import:</strong>
        Ratio wajib &gt; 0. Jika Item + UOM sudah ada, nilai lama akan di-update. Barcode yang tidak ditemukan atau ambigu tidak akan disimpan.
    </div>
</section>

<div class="split-grid ratio-layout">
    <section class="panel">
        <div class="panel-head"><h2>Tambah / Update Ratio</h2></div>
        <form method="POST" action="{{ route('admin.ratios.store') }}" class="stack-form" id="ratio-form">
            @csrf
            <label>
                Database
                <select name="source_database" id="ratio-db">
                    @foreach($databases as $db)<option>{{ $db }}</option>@endforeach
                </select>
            </label>
            <label>
                Barcode ERP
                <div class="input-action">
                    <input id="ratio-barcode" autocomplete="off">
                    <button class="btn" type="button" id="ratio-lookup">Cari</button>
                </div>
            </label>
            <div id="ratio-found" class="lookup-result muted">Cari barcode untuk mengisi data item otomatis.</div>
            <input type="hidden" name="item_id" id="ratio-item-id">
            <input type="hidden" name="item_code" id="ratio-item-code">
            <input type="hidden" name="item_name" id="ratio-item-name">
            <input type="hidden" name="uom_level" id="ratio-uom-level">
            <input type="hidden" name="uom_code" id="ratio-uom-code">
            <label>
                Ratio ke Smallest UOM
                <input type="number" name="ratio" step="0.0001" min="0.0001" required placeholder="Contoh: 20">
            </label>
            <button class="btn primary" type="submit">Simpan Ratio</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Data Ratio</h2></div>
        <form class="filter-row">
            <select name="source_database">
                <option value="">Semua DB</option>
                @foreach($databases as $db)
                    <option @selected(request('source_database')===$db)>{{ $db }}</option>
                @endforeach
            </select>
            <input name="q" value="{{ request('q') }}" placeholder="Item code / nama">
            <button class="btn" type="submit">Filter</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>DB</th><th>Item</th><th>UOM</th><th class="num">Ratio</th><th></th></tr></thead>
                <tbody>
                    @foreach($ratios as $ratio)
                        <tr>
                            <td>{{ $ratio->source_database }}</td>
                            <td><strong>{{ $ratio->item_code }}</strong><small>{{ $ratio->item_name }}</small></td>
                            <td>{{ $ratio->uom_code }} <small>L{{ $ratio->uom_level }}</small></td>
                            <td class="num">{{ rtrim(rtrim(number_format((float)$ratio->ratio,4,'.',','),'0'),'.') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.ratios.destroy',$ratio) }}" onsubmit="return confirm('Hapus ratio ini?')">
                                    @csrf @method('DELETE')
                                    <button class="danger-link">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $ratios->links() }}
    </section>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('ratio-lookup')?.addEventListener('click', async () => {
    const db = document.getElementById('ratio-db').value;
    const barcode = document.getElementById('ratio-barcode').value.trim();
    const box = document.getElementById('ratio-found');

    if (!barcode) {
        box.textContent = 'Isi barcode terlebih dahulu.';
        return;
    }

    box.textContent = 'Mencari...';
    try {
        const r = await fetch(`{{ route('admin.erp.barcode') }}?source_database=${encodeURIComponent(db)}&barcode=${encodeURIComponent(barcode)}`, {
            headers: {'Accept':'application/json'}
        });
        const j = await r.json();
        if (!r.ok) throw new Error(j.message || 'Gagal mencari barcode');

        const d = j.data;
        document.getElementById('ratio-item-id').value = d.item_id;
        document.getElementById('ratio-item-code').value = d.item_code;
        document.getElementById('ratio-item-name').value = d.item_name;
        document.getElementById('ratio-uom-level').value = d.uom_level;
        document.getElementById('ratio-uom-code').value = d.uom_code;
        box.className = 'lookup-result success-lite';
        box.innerHTML = `<strong>${d.item_code}</strong><br>${d.item_name}<br>UOM: ${d.uom_code} (Level ${d.uom_level})`;
    } catch (e) {
        box.className = 'lookup-result danger-lite';
        box.textContent = e.message;
    }
});
</script>
@endpush
