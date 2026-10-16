@extends('layouts.app')
@section('title','History Sampling Gudang')
@section('page-class','ws-history-page')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling.css') }}?v=20261012historyqty">
<style>
.ws-history-compact{width:100%;border-collapse:collapse;font-size:12px}
.ws-history-compact th,.ws-history-compact td{padding:10px;border-bottom:1px solid #e5e7eb;white-space:normal;vertical-align:top}
.ws-history-compact th{background:#f8fafc;text-align:left;position:sticky;top:0}
.ws-history-compact td.num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.ws-history-compact .ws-admin-note{max-width:260px;min-width:160px;overflow-wrap:anywhere}
.ws-history-compact .muted{display:block;font-size:10px;color:#667085}
.ws-history-compact .ws-short{background:#fff1f0}
.ws-history-compact .ws-over{background:#eff8ff}
.ws-history-compact .ws-match{background:#f6fef9}
.ws-history-compact-scroll{width:100%;overflow-x:auto}
</style>
@endpush
@php
$fmtQty=static function ($value): string {
    $s=rtrim(rtrim(number_format((float)($value??0),4,'.',','),'0'),'.');
    return $s==='' || $s==='-0' ? '0' : $s;
};
@endphp
<section class="panel"><div class="panel-head"><div><h1>History Sampling Gudang</h1><p>Laporan ringkas: Qty Validasi, Qty Fisik Checker, Override Admin, Qty Final, Selisih, dan komentar.</p></div><div><a class="btn" href="{{ route('warehouse.history.pdf',request()->query()) }}">Export PDF</a> <a class="btn primary" href="{{ route('warehouse.history.excel',request()->query()) }}">Export Excel</a></div></div></section>
<section class="panel">
    <form method="GET" action="{{ route('warehouse.history.index') }}" class="ws-history-filter-grid ws-history-filter-grid-full">
        <label><span>Cari Periode / Item / Komentar</span><input name="q" type="search" value="{{ $filters['q'] }}"></label>
        <label><span>Dari</span><input name="date_from" type="date" value="{{ $filters['date_from'] }}"></label>
        <label><span>Sampai</span><input name="date_to" type="date" value="{{ $filters['date_to'] }}"></label>
        <label><span>Database</span><select name="source_database"><option value="">Semua DB</option><option value="AS_INGCO" @selected($filters['source_database']==='AS_INGCO')>AS_INGCO</option><option value="AS_SMI" @selected($filters['source_database']==='AS_SMI')>AS_SMI</option></select></label>
        <label><span>Gudang</span><select name="warehouse_key"><option value="">Semua Gudang</option>@foreach($warehouses as $wh)<option value="{{ $wh['key'] }}" @selected($filters['warehouse_key']===$wh['key'])>{{ $wh['source_database'] }} · {{ $wh['warehouse_code'] }}</option>@endforeach</select></label>
        <label><span>Checker</span><select name="checker_id"><option value="">Semua Checker</option>@foreach($checkers as $checker)<option value="{{ $checker['id'] }}" @selected((int)$filters['checker_id']===$checker['id'])>{{ $checker['name'] }}</option>@endforeach</select></label>
        <label><span>Hasil</span><select name="result"><option value="">Semua</option><option value="MATCH" @selected($filters['result']==='MATCH')>Cocok</option><option value="MISMATCH" @selected($filters['result']==='MISMATCH')>Selisih</option></select></label>
        <div class="ws-history-filter-actions"><button class="btn primary" type="submit">Cari</button><a class="btn" href="{{ route('warehouse.history.index') }}">Reset</a></div>
    </form>
</section>
<section class="ws-history-metrics ws-history-metrics-full">
    <div class="ws-history-metric-card"><span>Periode</span><strong>{{ $stats['periods'] }}</strong></div>
    <div class="ws-history-metric-card"><span>Baris</span><strong>{{ $stats['rows'] }}</strong></div>
    <div class="ws-history-metric-card"><span>Cocok</span><strong>{{ $stats['match'] }}</strong></div>
    <div class="ws-history-metric-card"><span>Selisih</span><strong>{{ $stats['mismatch'] }}</strong></div>
</section>
<section class="panel"><div class="panel-head"><h2>Hasil Validasi Gudang</h2><span>{{ $rows->total() }} baris</span></div>
<div class="ws-history-compact-scroll"><table class="ws-history-compact">
<thead><tr><th>Tutup / Periode</th><th>Database / Gudang</th><th>Item</th><th class="num">Qty Validasi</th><th class="num">Fisik Checker Total</th><th class="num">Override Admin Total</th><th class="num">Qty Final Gudang</th><th class="num">Selisih</th><th>Hasil</th><th>Checker</th><th>Komentar Admin Gudang</th></tr></thead>
<tbody>
@forelse($rows as $row)
@php
$validasi=(float)($row->validation_system_qty??$row->warehouse_system_qty??0);
$final=(float)($row->warehouse_physical_qty??0);
$selisih=$final-$validasi;
@endphp
<tr class="{{ $selisih < -0.0001 ? 'ws-short' : ($selisih > 0.0001 ? 'ws-over' : 'ws-match') }}">
<td>{{ $row->closed_at ? \Carbon\Carbon::parse($row->closed_at)->format('d/m/Y') : '-' }}<span class="muted">{{ $row->cycle_no }}</span></td>
<td>{{ $row->source_database }}<span class="muted">{{ $row->warehouse_code }} · {{ $row->warehouse_name }}</span></td>
<td><strong>{{ $row->item_code }}</strong><span class="muted">{{ $row->item_name }}</span></td>
<td class="num">{{ $fmtQty($validasi) }}</td><td class="num">{{ $fmtQty($row->checker_physical_total) }}</td>
<td class="num">{{ $row->admin_override_qty === null ? '-' : $fmtQty($row->admin_override_qty) }}</td>
<td class="num"><strong>{{ $fmtQty($final) }}</strong></td><td class="num">{{ $fmtQty($selisih) }}</td>
<td>{{ $row->warehouse_result==='MATCH' ? 'COCOK' : ($selisih>0 ? 'LEBIH' : 'KURANG') }}</td>
<td>{{ $row->checker_name??'-' }}</td>
<td class="ws-admin-note">{{ $row->validation_note ?: '-' }}<span class="muted">{{ $row->validator_name??'-' }}</span></td>
</tr>
@empty<tr><td colspan="11" class="empty">Belum ada history yang sesuai filter.</td></tr>
@endforelse
</tbody></table></div>
<div class="ws-history-pagination">{{ $rows->links() }}</div>
</section>
@endsection
