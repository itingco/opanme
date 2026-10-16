@extends('layouts.app')
@section('title', 'Tugas Opname Legacy')
@section('page-class', 'checker-page')
@section('content')
<div class="checker-heading"><h1>Opname Gudang Legacy</h1><p>Cycle Legacy yang ditugaskan kepada Checker Gudang. Pilih gudang dan mulai scan.</p></div>
<div class="assignment-list">
@forelse($assignments as $assignment)
<article class="checker-assignment">
    <div class="assignment-top"><div><strong>{{ $assignment->warehouse->warehouse_code }}</strong><small>{{ $assignment->warehouse->warehouse_name }}</small></div><span>{{ $assignment->cycle->cycle_no }}</span></div>
    <form method="POST" action="{{ route('warehouse.legacy.session.start', [$assignment->cycle, $assignment->warehouse->id]) }}" class="location-form">
        @csrf
        <label>Lokasi / Rak<input name="location" required maxlength="255" placeholder="Contoh: RAK A-01" autocomplete="off"></label>
        <button class="btn primary full big" type="submit">Mulai Scan</button>
    </form>
</article>
@empty
<div class="empty large-empty">Belum ada cycle Legacy OPEN yang ditugaskan kepada Anda.</div>
@endforelse
</div>
@endsection
