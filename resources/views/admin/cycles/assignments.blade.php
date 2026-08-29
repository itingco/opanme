@extends('layouts.app')
@section('title','Assignment Checker')
@section('content')
<div class="page-heading"><div><h1>Assignment Checker</h1><p>{{ $cycle->cycle_no }} · {{ $cycle->source_database }}</p></div></div>
<form method="POST" action="{{ route('admin.cycles.assignments.update',$cycle) }}">@csrf @method('PUT')
<section class="panel"><div class="assignment-grid">@foreach($cycle->warehouses as $warehouse)<article class="assignment-card"><h3>{{ $warehouse->warehouse_code }}</h3><p>{{ $warehouse->warehouse_name }}</p><div class="checker-options">@forelse($checkers as $checker)<label class="check"><input type="checkbox" name="assignments[{{ $warehouse->id }}][]" value="{{ $checker->id }}" @checked(in_array($checker->id,$selected->get($warehouse->id,[])))> <span>{{ $checker->name }}<small>{{ $checker->username }}</small></span></label>@empty<div class="empty compact">Belum ada user Checker aktif.</div>@endforelse</div></article>@endforeach</div></section>
<div class="sticky-actions"><a class="btn" href="{{ route('admin.cycles.show',$cycle) }}">Batal</a><button class="btn primary" type="submit">Simpan Assignment</button></div></form>
@endsection
