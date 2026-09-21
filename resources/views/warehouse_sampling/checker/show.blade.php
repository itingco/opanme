@extends('layouts.app')
@section('title','Cek Qty Fisik')
@section('content')
@vite('resources/css/warehouse-sampling.css')
@php $total=(int)$period->items_count; $checked=(int)$period->checked_items_count; $pct=$total>0?min(100,($checked/$total)*100):0; $targetCount=$total>0?(int)ceil($total*((float)$period->target_percentage/100)):0; $reached=$targetCount>0&&$checked>=$targetCount; @endphp
<div class="page-heading"><div><h1>{{ $period->cycle_no }}</h1><p>{{ $period->warehouse_code }} · {{ $period->warehouse_name }} · {{ $period->location }}</p></div><a class="btn" href="{{ route('warehouse.checker.index') }}">← Tugas</a></div>
<section class="panel"><div class="ws-progress {{ $reached?'target-reached':'' }}"><span style="width:{{ $pct }}%"></span></div><p class="ws-note">{{ $checked }} / {{ $total }} baris selesai ({{ number_format($pct,1) }}%). Target periode {{ number_format((float)$period->target_percentage,0) }}% = minimal {{ $targetCount }} baris. {{ $reached?'Target tercapai.':'' }}</p></section>
@if($period->isOpen())<div class="ws-lock-note">Hitung fisik terlebih dahulu lalu masukkan Qty Ditemukan. Qty sistem baru ditampilkan setelah baris disimpan.</div>@else<div class="alert">Periode sudah CLOSED. Data hanya dapat dilihat.</div>@endif
<div class="ws-check-list">
@forelse($items as $item)
<article class="ws-check-card {{ $item->checked_at ? 'checked':'' }} {{ $item->result==='MISMATCH'?'mismatch':'' }}"><div class="ws-check-head"><div><strong>#{{ $item->line_no }} · {{ $item->item_code }}</strong><small>{{ $item->item_name }}</small></div><span class="ws-badge {{ $item->checked_at ? ($item->result==='MATCH'?'open':'closed'):'draft' }}">{{ $item->checked_at ? ($item->result==='MATCH'?'COCOK':'SELISIH'):'BELUM' }}</span></div>
@if($item->checked_at)<div class="ws-result-row"><span>Qty Sistem<b>{{ number_format((float)$item->system_qty,4,'.',',') }} {{ $item->uom_code }}</b></span><span>Qty Fisik<b>{{ number_format((float)$item->physical_qty,4,'.',',') }} {{ $item->uom_code }}</b></span><span>Selisih<b>{{ number_format((float)$item->physical_qty-(float)$item->system_qty,4,'.',',') }}</b></span></div><small class="ws-note">Terakhir disimpan {{ $item->checked_at?->format('d/m/Y H:i:s') }}.</small>@endif
@if($period->isOpen())<form method="POST" action="{{ route('warehouse.checker.check',[$period,$item]) }}" class="ws-check-form">@csrf @method('PUT')<label>Qty Fisik Ditemukan ({{ $item->uom_code }})<input type="number" name="physical_qty" min="0" step="0.0001" inputmode="decimal" value="{{ $item->checked_at ? $item->physical_qty : '' }}" placeholder="Masukkan hasil hitung fisik" required></label><button class="btn {{ $item->checked_at?'':'primary' }}" type="submit">{{ $item->checked_at?'Perbaiki Qty':'Simpan Qty' }}</button></form>@endif
</article>
@empty<div class="empty large-empty">Tidak ada item dalam periode ini.</div>@endforelse
</div>
{{ $items->links() }}
@endsection
