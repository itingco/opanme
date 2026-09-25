@extends('layouts.app')
@section('title','Tugas Sampling Gudang')
@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/warehouse-sampling.css') }}?v=20260923i">
@endpush
<div class="page-heading">
    <div>
        <h1>Tugas Sampling Gudang</h1>
        <p>Klik kode barang, isi Qty Fisik dan komentar melalui modal, lalu simpan draft satu per satu. Finalisasi setelah semua item selesai.</p>
    </div>
</div>
<section class="panel">
@forelse($periods as $period)
@php
    $filled=(int)($period->filled_items_count ?? 0);
    $total=(int)$period->items_count;
    $finalized=$total>0 && (int)$period->checked_items_count===$total;
    $pct=$total>0?min(100,($filled/$total)*100):0;
    $targetCount=$total>0?(int)ceil($total*((float)$period->target_percentage/100)):0;
    $reached=$targetCount>0&&$filled>=$targetCount;
@endphp
<article class="ws-period-card">
    <div class="ws-period-head">
        <div><strong>{{ $period->cycle_no }}</strong><small>{{ $period->warehouses_count }} gudang digabung · {{ $period->location }}</small></div>
        @if($finalized)
            <span class="ws-badge open">FINAL</span>
        @else
            <span class="ws-badge {{ strtolower($period->status) }}">{{ $period->status }}</span>
        @endif
    </div>
    <div class="ws-period-meta">
        <span><b>{{ $total }}</b>Total item</span>
        <span><b>{{ $filled }}</b>Draft terisi</span>
        <span><b>{{ $finalized ? '100%' : number_format((float)$period->target_percentage,0).'%' }}</b>{{ $finalized ? 'Final' : 'Target' }}</span>
    </div>
    <div class="ws-progress {{ $finalized || $reached?'target-reached':'' }}"><span style="width:{{ $finalized ? 100 : $pct }}%"></span></div>
    <small class="ws-note">
        @if($finalized)
            Seluruh {{ $total }} item sudah difinalisasi dan terkunci.
        @else
            {{ number_format($pct,1) }}% draft terisi · target {{ $targetCount }} item
        @endif
    </small>
    <a class="btn {{ $finalized ? '' : 'primary' }}" href="{{ route('warehouse.checker.show',$period) }}">
        {{ $finalized ? 'Lihat Hasil Final' : ($period->isOpen() ? 'Lanjut Input Qty' : 'Lihat Hasil') }}
    </a>
</article>
@empty<div class="empty">Belum ada tugas sampling gudang yang diberikan kepada Anda.</div>@endforelse
{{ $periods->links() }}
</section>
@endsection
