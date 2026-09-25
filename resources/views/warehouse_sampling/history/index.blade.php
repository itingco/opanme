@extends('layouts.app')
@section('title','History Sampling Gudang')
@section('page-class','ws-history-page')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling.css') }}?v=20260925historyfull">
@endpush

<section class="ws-history-hero panel">
    <div class="ws-history-hero-copy">
        <span class="ws-history-kicker">HISTORY SAMPLING GUDANG</span>
        <h1>History Sampling Cek Stok</h1>
        <p>Data mentah dari periode Sampling Gudang yang sudah selesai divalidasi dan ditutup. Gunakan filter untuk mencari histori, lalu export dalam format PDF atau Excel Raw.</p>
    </div>
    <div class="ws-history-hero-actions">
        <a class="btn" href="{{ route('warehouse.history.pdf', request()->query()) }}">Export PDF</a>
        <a class="btn primary" href="{{ route('warehouse.history.excel', request()->query()) }}">Export Excel Raw</a>
    </div>
</section>

<section class="panel ws-history-filter-panel ws-history-filter-panel-full">
    <div class="ws-history-section-head">
        <div>
            <h2>Filter & Pencarian</h2>
            <p>Cari berdasarkan periode, item, gudang, checker, komentar, atau rentang tanggal tutup.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('warehouse.history.index') }}" class="ws-history-filter-grid ws-history-filter-grid-full">
        <label class="ws-history-search-field ws-history-search-field-full">
            <span>Cari History</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Periode, item, gudang, komentar, checker...">
        </label>
        <label><span>Dari Tanggal Tutup</span><input type="date" name="date_from" value="{{ $filters['date_from'] }}"></label>
        <label><span>Sampai Tanggal Tutup</span><input type="date" name="date_to" value="{{ $filters['date_to'] }}"></label>
        <label><span>Database</span><select name="source_database"><option value="">Semua Database</option><option value="AS_INGCO" @selected($filters['source_database']==='AS_INGCO')>AS_INGCO</option><option value="AS_SMI" @selected($filters['source_database']==='AS_SMI')>AS_SMI</option></select></label>
        <label><span>Gudang</span><select name="warehouse_key"><option value="">Semua Gudang</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse['key'] }}" @selected($filters['warehouse_key']===$warehouse['key'])>{{ $warehouse['source_database'] }} · {{ $warehouse['warehouse_code'] }} · {{ $warehouse['warehouse_name'] }}</option>@endforeach</select></label>
        <label><span>Checker</span><select name="checker_id"><option value="">Semua Checker</option>@foreach($checkers as $checker)<option value="{{ $checker['id'] }}" @selected((int)$filters['checker_id']===$checker['id'])>{{ $checker['name'] }}</option>@endforeach</select></label>
        <label><span>Hasil</span><select name="result"><option value="">Semua Hasil</option><option value="MATCH" @selected($filters['result']==='MATCH')>Cocok</option><option value="MISMATCH" @selected($filters['result']==='MISMATCH')>Selisih</option></select></label>
        <div class="ws-history-filter-actions ws-history-filter-actions-full">
            <a class="btn" href="{{ route('warehouse.history.index') }}">Reset</a>
            <button class="btn primary" type="submit">Terapkan Filter</button>
        </div>
    </form>
</section>

<section class="ws-history-metrics ws-history-metrics-full">
    <div class="ws-history-metric-card">
        <span>Periode</span>
        <strong>{{ number_format($stats['periods']) }}</strong>
        <small>Periode closed</small>
    </div>
    <div class="ws-history-metric-card">
        <span>Item Sampling</span>
        <strong>{{ number_format($stats['items']) }}</strong>
        <small>Item unik yang pernah dicek</small>
    </div>
    <div class="ws-history-metric-card">
        <span>Baris Gudang-Item</span>
        <strong>{{ number_format($stats['rows']) }}</strong>
        <small>Data mentah per gudang</small>
    </div>
    <div class="ws-history-metric-card ws-history-success-card">
        <span>Cocok</span>
        <strong>{{ number_format($stats['match']) }}</strong>
        <small>Baris dengan hasil cocok</small>
    </div>
    <div class="ws-history-metric-card ws-history-warning-card">
        <span>Selisih</span>
        <strong>{{ number_format($stats['mismatch']) }}</strong>
        <small>Baris dengan hasil selisih</small>
    </div>
</section>

<section class="panel ws-history-table-panel ws-history-table-panel-full">
    <div class="ws-history-table-head ws-history-table-head-full">
        <div>
            <h2>Data Mentah Sampling</h2>
            <p>{{ number_format($rows->total()) }} baris ditemukan. Satu baris mewakili satu item pada satu gudang.</p>
        </div>
        <div class="ws-history-table-note">
            <strong>Tampilan Full Width</strong>
            <small>Scroll ke kanan untuk melihat seluruh kolom data mentah.</small>
        </div>
    </div>

    <div class="ws-history-table-scroll ws-history-table-scroll-full">
        <table class="ws-history-table ws-history-table-full">
            <thead>
                <tr>
                    <th>Tanggal Tutup</th>
                    <th>Periode</th>
                    <th>Area</th>
                    <th>Database</th>
                    <th>Gudang</th>
                    <th>Item</th>
                    <th>UOM</th>
                    <th class="num">Total Sistem</th>
                    <th class="num">Fisik Checker</th>
                    <th class="num">Snapshot Gudang</th>
                    <th class="num">Sales Invoice</th>
                    <th class="num">Sistem Setelah Sales</th>
                    <th>No Sales Invoice</th>
                    <th class="num">Alokasi Fisik</th>
                    <th class="num">Selisih</th>
                    <th>Hasil</th>
                    <th>Checker</th>
                    <th>Komentar Checker</th>
                    <th>Validator</th>
                    <th>Catatan Validasi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $warehouseSystem = (float)($row->warehouse_system_qty ?? 0);
                    $salesInvoiceQty = (float)($row->sales_invoice_qty ?? 0);
                    $adjustedSystem = (float)($row->adjusted_system_qty ?? $warehouseSystem);
                    $warehousePhysical = (float)($row->warehouse_physical_qty ?? 0);
                    $variance = $warehousePhysical - $adjustedSystem;
                    $invoiceDetails = is_array($row->sales_invoice_details ?? null)
                        ? $row->sales_invoice_details
                        : json_decode((string)($row->sales_invoice_details ?? ''), true);
                    $invoiceNumbers = collect(is_array($invoiceDetails) ? $invoiceDetails : [])
                        ->pluck('invoice_number')->filter()->unique()->implode(', ');
                @endphp
                <tr>
                    <td>{{ $row->closed_at ? \Carbon\Carbon::parse($row->closed_at)->format('d/m/Y H:i') : '-' }}</td>
                    <td><strong>{{ $row->cycle_no }}</strong><small>Line #{{ $row->line_no }}</small></td>
                    <td>{{ $row->location }}</td>
                    <td><span class="ws-history-db">{{ $row->source_database }}</span></td>
                    <td><strong>{{ $row->warehouse_code }}</strong><small>{{ $row->warehouse_name }}</small></td>
                    <td><strong>{{ $row->item_code }}</strong><small>{{ $row->item_name }}</small></td>
                    <td>{{ $row->uom_code }}</td>
                    <td class="num">{{ number_format((float)$row->total_system_qty,4,'.',',') }}</td>
                    <td class="num">{{ number_format((float)$row->checker_physical_total,4,'.',',') }}</td>
                    <td class="num">{{ number_format($warehouseSystem,4,'.',',') }}</td>
                    <td class="num {{ $salesInvoiceQty > 0 ? 'negative' : '' }}">{{ number_format($salesInvoiceQty,4,'.',',') }}</td>
                    <td class="num">{{ number_format($adjustedSystem,4,'.',',') }}</td>
                    <td class="ws-history-comment">{{ $invoiceNumbers ?: '-' }}</td>
                    <td class="num">{{ number_format($warehousePhysical,4,'.',',') }}</td>
                    <td class="num {{ $variance < 0 ? 'negative' : ($variance > 0 ? 'positive' : '') }}">{{ number_format($variance,4,'.',',') }}</td>
                    <td><span class="ws-history-result {{ strtolower((string)$row->warehouse_result) }}">{{ $row->warehouse_result==='MATCH' ? 'COCOK' : ($row->warehouse_result==='MISMATCH' ? 'SELISIH' : '-') }}</span></td>
                    <td>{{ $row->checker_name ?? '-' }}<small>{{ $row->checked_at ? \Carbon\Carbon::parse($row->checked_at)->format('d/m/Y H:i') : '' }}</small></td>
                    <td class="ws-history-comment">{{ $row->checker_comment ?: '-' }}</td>
                    <td>{{ $row->validator_name ?? '-' }}<small>{{ $row->validated_at ? \Carbon\Carbon::parse($row->validated_at)->format('d/m/Y H:i') : '' }}</small></td>
                    <td class="ws-history-comment">{{ $row->validation_note ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="20" class="empty">Belum ada history Sampling Gudang CLOSED yang sesuai filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="ws-history-pagination">{{ $rows->links() }}</div>
</section>
@endsection
