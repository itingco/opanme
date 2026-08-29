@extends('layouts.app')
@section('title','Dashboard Admin')
@section('content')
<div class="page-heading"><div><h1>Dashboard</h1><p>Pusat kontrol stock opname.</p></div><a class="btn primary" href="{{ route('admin.cycles.create') }}">+ Buat Cycle</a></div>
<div class="metric-grid">
    <article class="metric"><span>Cycle Open</span><strong>{{ $openCycles->count() }}</strong></article>
    <article class="metric"><span>Checker Aktif</span><strong>{{ number_format($checkerCount) }}</strong></article>
    <article class="metric"><span>Scan Hari Ini</span><strong>{{ number_format($todayScans) }}</strong></article>
</div>
<section class="panel"><div class="panel-head"><h2>Cycle Aktif</h2></div>
@if($openCycles->isEmpty())<div class="empty">Belum ada cycle yang sedang berjalan.</div>@else
<div class="cycle-list">@foreach($openCycles as $cycle)<a class="cycle-row" href="{{ route('admin.cycles.show',$cycle) }}"><div><strong>{{ $cycle->cycle_no }}</strong><small>{{ $cycle->source_database }} · mulai {{ optional($cycle->started_at)->format('d/m/Y H:i') }}</small></div><span class="status open">OPEN</span></a>@endforeach</div>@endif
</section>
<section class="panel"><div class="panel-head"><h2>Cycle Terbaru</h2><a href="{{ route('admin.cycles.index') }}">Lihat semua</a></div>
<div class="table-wrap"><table><thead><tr><th>Cycle</th><th>DB</th><th>Status</th><th>Dibuat</th></tr></thead><tbody>@foreach($recentCycles as $cycle)<tr><td><a href="{{ route('admin.cycles.show',$cycle) }}"><strong>{{ $cycle->cycle_no }}</strong></a></td><td>{{ $cycle->source_database }}</td><td><span class="status {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span></td><td>{{ $cycle->created_at->format('d/m/Y H:i') }}</td></tr>@endforeach</tbody></table></div>
</section>
@endsection
