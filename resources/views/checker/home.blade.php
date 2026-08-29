@extends('layouts.app')
@section('title','Tugas Saya')
@section('page-class','checker-page')
@section('content')
<div class="checker-heading"><h1>Tugas Saya</h1><p>Pilih warehouse, isi lokasi/rak, lalu mulai scan.</p></div>
<div class="assignment-list">@forelse($assignments as $assignment)<article class="checker-assignment"><div class="assignment-top"><div><strong>{{ $assignment->warehouse->warehouse_code }}</strong><small>{{ $assignment->warehouse->warehouse_name }}</small></div><span>{{ $assignment->cycle->cycle_no }}</span></div><form method="POST" action="{{ route('checker.session.start',[$assignment->cycle,$assignment->warehouse->id]) }}" class="location-form">@csrf<label>Lokasi / Rak<input name="location" required maxlength="255" placeholder="Contoh: RAK A-01" autocomplete="off"></label><button class="btn primary full big" type="submit">Mulai Scan</button></form></article>@empty<div class="empty large-empty">Tidak ada cycle OPEN yang ditugaskan kepada Anda.</div>@endforelse</div>
@endsection
