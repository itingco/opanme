@extends('layouts.app')
@section('title','Tugas Sampling Gudang')
@section('content')
@vite('resources/css/warehouse-sampling.css')
<div class="page-heading"><div><h1>Tugas Sampling Gudang</h1><p>Masukkan qty fisik yang benar-benar ditemukan untuk setiap baris yang ditugaskan.</p></div></div>
<section class="panel">
@forelse($periods as $period)
@php $pct=$period->items_count>0?min(100,($period->checked_items_count/$period->items_count)*100):0; $targetCount=$period->items_count>0?(int)ceil($period->items_count*((float)$period->target_percentage/100)):0; $reached=$targetCount>0&&$period->checked_items_count>=$targetCount; @endphp
<article class="ws-period-card"><div class="ws-period-head"><div><strong>{{ $period->cycle_no }}</strong><small>{{ $period->warehouse_code }} · {{ $period->warehouse_name }} · {{ $period->location }}</small></div><span class="ws-badge {{ strtolower($period->status) }}">{{ $period->status }}</span></div><div class="ws-period-meta"><span><b>{{ $period->items_count }}</b>Total baris</span><span><b>{{ $period->checked_items_count }}</b>Sudah dicek</span><span><b>{{ number_format((float)$period->target_percentage,0) }}%</b>Target</span></div><div class="ws-progress {{ $reached?'target-reached':'' }}"><span style="width:{{ $pct }}%"></span></div><small class="ws-note">{{ number_format($pct,1) }}% selesai · target {{ $targetCount }} baris</small><a class="btn primary" href="{{ route('warehouse.checker.show',$period) }}">{{ $period->isOpen() ? 'Mulai / Lanjut Cek':'Lihat Hasil' }}</a></article>
@empty<div class="empty">Belum ada tugas sampling gudang yang diberikan kepada Anda.</div>@endforelse
{{ $periods->links() }}
</section>
@endsection
