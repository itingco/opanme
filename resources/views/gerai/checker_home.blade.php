@extends('layouts.app')
@section('title','Tugas Checker Gerai')
@section('content')
<div class="page-heading"><div><h1>Tugas Checker Gerai</h1><p>Cycle dari semua gudang yang ditugaskan ke akun Anda, termasuk lintas database.</p></div></div>
<section class="panel">
    <div class="table-wrap"><table><thead><tr><th>Cycle</th><th>Database / Gudang</th><th>Dibuat Oleh</th><th>Lokasi</th><th>Mulai</th><th>Status</th><th class="num">Item</th><th></th></tr></thead><tbody>
    @forelse($cycles as $cycle)
        <tr>
            <td><strong>{{ $cycle->cycle_no }}</strong></td>
            <td><strong>{{ $cycle->source_database }} · {{ $cycle->warehouse_code }}</strong><small>{{ $cycle->warehouse_name }}</small></td>
            <td>{{ $cycle->creator?->name ?? '-' }}</td>
            <td>{{ $cycle->location }}</td>
            <td>{{ $cycle->started_at?->format('d/m/Y H:i') }}</td>
            <td><span class="status {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span></td>
            <td class="num">{{ number_format($cycle->checks_count) }}</td>
            <td>@if($cycle->isOpen())<a class="btn small primary" href="{{ route('gerai.checker.scan',$cycle) }}">Mulai / Lanjut Cek</a>@else<a class="btn small" href="{{ route('gerai.checker.scan',$cycle) }}">Lihat</a>@endif</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">Belum ada cycle yang ditugaskan ke Anda.</td></tr>
    @endforelse
    </tbody></table></div>
    {{ $cycles->links() }}
</section>
@endsection
