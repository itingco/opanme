@extends('layouts.app')
@section('title',$cycle->cycle_no)
@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $cycle->cycle_no }}</h1>
        <p>{{ $cycle->source_database }} · Cutoff {{ $cycle->cutoff_date->format('d/m/Y') }}</p>
    </div>
    <span class="status large {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span>
</div>

@if($cycle->status === \App\Models\StockOpnameCycle::STATUS_FINALIZED)
    <div class="alert finalized-note">
        <strong>Cycle sudah FINALIZED dan terkunci permanen.</strong>
        <div>Finalisasi: {{ optional($cycle->finalized_at)->format('d/m/Y H:i:s') ?: '-' }} · {{ optional($cycle->finalizer)->name ?: 'Admin' }}</div>
    </div>
@endif

<div class="metric-grid">
    <article class="metric"><span>Warehouse</span><strong>{{ $cycle->warehouses->count() }}</strong></article>
    <article class="metric"><span>Checker Assignment</span><strong>{{ $cycle->assignments->count() }}</strong></article>
    <article class="metric"><span>Total Scan</span><strong>{{ number_format($scanCount) }}</strong></article>
</div>

<div class="action-strip">
    @if($cycle->status === \App\Models\StockOpnameCycle::STATUS_DRAFT)
        <a class="btn" href="{{ route('admin.cycles.assignments.edit',$cycle) }}">Atur Assignment</a>
        <form method="POST" action="{{ route('admin.cycles.start',$cycle) }}" onsubmit="return confirm('Mulai cycle dan ambil snapshot stok ERP sekarang?')">
            @csrf
            <button class="btn primary">Start Stock Opname</button>
        </form>
    @endif

    @if($cycle->status === \App\Models\StockOpnameCycle::STATUS_OPEN)
        <form method="POST" action="{{ route('admin.cycles.close',$cycle) }}" onsubmit="return confirm('Tutup cycle? Checker tidak akan bisa scan lagi.')">
            @csrf
            <button class="btn danger">Selesai / Close Cycle</button>
        </form>
    @endif

    <a class="btn" href="{{ route('admin.cycles.summary',$cycle) }}">
        {{ $cycle->status === \App\Models\StockOpnameCycle::STATUS_CLOSED ? 'Review & Finalisasi' : 'Lihat Summary' }}
    </a>

    @if($cycle->status === \App\Models\StockOpnameCycle::STATUS_FINALIZED)
        <a class="btn primary" href="{{ route('admin.cycles.final-report',$cycle) }}">Download Laporan Final PDF</a>
    @endif
</div>

<div class="split-grid">
    <section class="panel">
        <div class="panel-head"><h2>Warehouse & Checker</h2></div>
        @foreach($cycle->warehouses as $wh)
            <div class="warehouse-line">
                <div><strong>{{ $wh->warehouse_code }}</strong><small>{{ $wh->warehouse_name }}</small></div>
                <div class="mini-tags">
                    @foreach($cycle->assignments->where('warehouse_id',$wh->id) as $a)<span>{{ $a->checker->name }}</span>@endforeach
                </div>
            </div>
        @endforeach
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Sesi Aktif</h2></div>
        @forelse($activeSessions as $session)
            <div class="session-row">
                <div><strong>{{ $session->checker->name }}</strong><small>{{ $session->warehouse->warehouse_code }} · {{ $session->location }}</small></div>
                <time>{{ $session->started_at->format('H:i') }}</time>
            </div>
        @empty
            <div class="empty compact">Tidak ada sesi checker aktif.</div>
        @endforelse
    </section>
</div>
@endsection
