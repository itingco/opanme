@extends('layouts.app')
@section('title','Detail Scan')
@section('content')
@php($isNonSystem = isset($nonSystemItem) && $nonSystemItem)
@php($first = $scans->first())
@php($detailItem = $first ?? ($snapshotItem ?? null))
<div class="page-heading"><div><h1>{{ $isNonSystem ? $nonSystemItem->alias_code : ($detailItem?->item_code ?? 'Detail Scan') }}</h1><p>{{ $isNonSystem ? $nonSystemItem->item_name : ($detailItem?->item_name ?? '-') }} · {{ $warehouse->warehouse_code }} @if($isNonSystem)<span class="non-system-row-badge">NON-SYSTEM</span>@endif</p></div><a class="btn" href="{{ route('admin.cycles.summary',$cycle) }}">← Summary</a></div>
<section class="panel"><h2>Scan Cycle Ini</h2><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Checker</th><th>Lokasi/Rak</th><th>Barcode</th><th>Qty Input</th><th>UOM</th><th class="num">Ratio Used</th><th>Smallest UOM</th><th class="num">Physical Qty</th></tr></thead><tbody>
@forelse($scans as $scan)<tr><td>{{ $scan->scanned_at?->format('d/m H:i:s') }}</td><td>{{ $scan->checker?->name ?? $scan->checker_id }}</td><td>{{ $scan->location }}</td><td>{{ $isNonSystem ? $nonSystemItem->alias_code : $scan->alias_code }}</td><td>{{ $isNonSystem ? number_format((float)$scan->input_qty,4,'.',',') : '1.0000' }}</td><td>{{ $isNonSystem ? $scan->input_uom_code : $scan->uom_code }}</td><td class="num">{{ number_format((float)$scan->ratio_used,4,'.',',') }}</td><td>{{ $isNonSystem ? $scan->smallest_uom_code : '-' }}</td><td class="num">{{ number_format((float)$scan->physical_qty,4,'.',',') }}</td></tr>@empty<tr><td colspan="9" class="empty">Tidak ada scan fisik untuk item ini.</td></tr>@endforelse
</tbody></table></div></section>
@if(!$isNonSystem && isset($sampleHistory))
<section class="panel"><div class="page-heading"><div><h2>Riwayat Sampling</h2><p>Semua sampling item ini di {{ $warehouse->warehouse_code }}, terbaru di atas.</p></div></div>
<form method="GET" action="{{ url()->current() }}" class="table-filter-bar"><label><span class="filter-label">Tanggal Dari</span><input type="date" name="sample_from" value="{{ $sampleFrom }}"></label><label><span class="filter-label">Tanggal Sampai</span><input type="date" name="sample_to" value="{{ $sampleTo }}"></label><div class="filter-actions"><button class="btn primary">Filter</button><a class="btn" href="{{ url()->current() }}">Semua Riwayat</a></div></form>
<div class="table-wrap"><table><thead><tr><th>Waktu</th><th>User Gerai</th><th>Lokasi / Rak</th><th>Barcode</th><th class="num">Stok Sistem</th><th class="num">Qty Fisik</th><th class="num">Selisih</th><th>Hasil</th></tr></thead><tbody>
@forelse($sampleHistory as $sample)<tr><td>{{ $sample->scanned_at?->format('d/m/Y H:i:s') }}</td><td>{{ $sample->user?->name ?? $sample->user_id }}</td><td>{{ $sample->location }}</td><td>{{ $sample->barcode }}</td><td class="num">{{ number_format((float)$sample->system_qty,4,'.',',') }}</td><td class="num">{{ number_format((float)$sample->physical_qty,4,'.',',') }}</td><td class="num">{{ number_format((float)$sample->physical_qty-(float)$sample->system_qty,4,'.',',') }}</td><td>{{ $sample->result==='MATCH' ? 'Cocok':'Tidak Cocok' }}</td></tr>@empty<tr><td colspan="8" class="empty">Belum ada riwayat sampling pada periode ini.</td></tr>@endforelse
</tbody></table></div>{{ $sampleHistory->links() }}</section>
@endif
@endsection
