@extends('layouts.app')
@section('title','Cycle Stock Opname')

@push('styles')
<style>
.cycle-list-page{max-width:1480px;margin:0 auto}.cycle-list-page .cycle-heading{display:flex;justify-content:space-between;gap:18px;align-items:center;margin-bottom:20px;flex-wrap:wrap}.cycle-list-page .cycle-heading h1{margin:0 0 5px;font-size:25px;line-height:1.2}.cycle-list-page .cycle-heading p{margin:0;color:#64748b}.cycle-list-page .cycle-filter-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;margin-bottom:16px;box-shadow:0 4px 18px rgba(15,23,42,.04)}.cycle-list-page .cycle-filter-grid{display:grid;grid-template-columns:minmax(180px,1.5fr) minmax(160px,1.3fr) minmax(145px,1.2fr) minmax(130px,.9fr) minmax(130px,.9fr) minmax(90px,.7fr);gap:12px;align-items:end}.cycle-list-page .cycle-field{display:flex;flex-direction:column;gap:7px;min-width:0;font-size:12px;font-weight:700;color:#475569;letter-spacing:.01em}.cycle-list-page .cycle-field input,.cycle-list-page .cycle-field select{width:100%;height:42px;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:9px;background:#fff;padding:0 11px;color:#0f172a;font-size:13px;font-weight:500;box-shadow:none}.cycle-list-page .cycle-field input:focus,.cycle-list-page .cycle-field select:focus{outline:2px solid #bfdbfe;border-color:#2563eb}.cycle-list-page .cycle-filter-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9}.cycle-list-page .cycle-filter-actions .btn{min-height:38px;display:inline-flex;align-items:center;justify-content:center;padding:7px 17px}.cycle-list-page .cycle-results{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(15,23,42,.04)}.cycle-list-page .cycle-table{width:100%;border-collapse:collapse}.cycle-list-page .cycle-table th{background:#f8fafc;color:#64748b;font-size:11px;letter-spacing:.06em;text-transform:uppercase;padding:14px 15px;text-align:left;white-space:nowrap;border-bottom:1px solid #e2e8f0}.cycle-list-page .cycle-table td{padding:15px;border-bottom:1px solid #f1f5f9;vertical-align:middle;font-size:13px}.cycle-list-page .cycle-table tr:last-child td{border-bottom:0}.cycle-list-page .cycle-table tbody tr:hover{background:#fafcff}.cycle-list-page .cycle-link{font-weight:750;color:#1554ca;text-decoration:none}.cycle-list-page .cycle-sub{display:block;margin-top:4px;font-size:11px;color:#64748b}.cycle-list-page .cycle-tags{display:flex;flex-wrap:wrap;gap:5px}.cycle-list-page .cycle-tag{background:#f1f5f9;color:#334155;padding:5px 8px;border-radius:7px;max-width:270px;font-size:11px;font-weight:650;overflow-wrap:anywhere}.cycle-list-page .cycle-muted{color:#94a3b8}.cycle-list-page .cycle-footer{padding:0 16px;border-top:1px solid #e2e8f0}.cycle-list-page .cycle-count{font-size:12px;color:#64748b;padding:13px 16px 0}.cycle-list-page .cycle-empty{text-align:center;padding:35px!important;color:#64748b}.cycle-list-page .cycle-database{font-size:12px;font-weight:700;color:#475569}.cycle-list-page .cycle-status{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:800;letter-spacing:.03em;background:#e2e8f0;color:#475569}.cycle-list-page .cycle-status.open{background:#dcfce7;color:#166534}.cycle-list-page .cycle-status.closed{background:#dbeafe;color:#1d4ed8}.cycle-list-page .cycle-status.finalized{background:#0f172a;color:#fff}.cycle-list-page .cycle-status.draft{background:#fef3c7;color:#92400e}@media(max-width:1150px){.cycle-list-page .cycle-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.cycle-list-page .cycle-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cycle-list-page .cycle-filter-card{padding:14px}.cycle-list-page .cycle-table{min-width:900px}.cycle-list-page .cycle-heading h1{font-size:21px}}@media(max-width:440px){.cycle-list-page .cycle-filter-grid{grid-template-columns:minmax(0,1fr)}.cycle-list-page .cycle-filter-actions{justify-content:stretch}.cycle-list-page .cycle-filter-actions .btn{flex:1}}
</style>
@endpush

@section('content')
<div class="cycle-list-page">
  <header class="cycle-heading">
    <div><h1>Cycle Stock Opname</h1><p>Monitor periode opname, gerai, dan checker yang bertugas.</p></div>
    <a class="btn primary" href="{{ route('admin.cycles.create') }}">+ Buat Cycle</a>
  </header>

  <section class="cycle-filter-card" aria-label="Filter cycle opname">
    <form method="GET" action="{{ route('admin.cycles.index') }}">
      <div class="cycle-filter-grid">
        <label class="cycle-field">Cycle<input type="search" name="q" value="{{ $q }}" placeholder="Nomor cycle / database"></label>
        <label class="cycle-field">Gerai / Warehouse<input type="search" name="warehouse" value="{{ $warehouse }}" placeholder="Kode atau nama gerai"></label>
        <label class="cycle-field">Checker<input type="search" name="checker" value="{{ $checker }}" placeholder="Nama atau username"></label>
        <label class="cycle-field">Database<select name="source_database"><option value="">Semua database</option><option value="AS_INGCO" @selected($database === 'AS_INGCO')>AS_INGCO</option><option value="AS_SMI" @selected($database === 'AS_SMI')>AS_SMI</option></select></label>
        <label class="cycle-field">Status<select name="status"><option value="">Semua status</option>@foreach(['DRAFT','OPEN','CLOSED','FINALIZED'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>@endforeach</select></label>
        <label class="cycle-field">Per halaman<select name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} data</option>@endforeach</select></label>
      </div>
      <div class="cycle-filter-actions"><a class="btn" href="{{ route('admin.cycles.index') }}">Reset</a><button class="btn primary" type="submit">Terapkan Filter</button></div>
    </form>
  </section>

  <section class="cycle-results">
    <div class="cycle-count">Total <strong>{{ number_format($cycles->total()) }}</strong> cycle ditemukan</div>
    <div class="table-wrap"><table class="cycle-table">
      <thead><tr><th>Cycle</th><th>Database</th><th>Gerai / Warehouse</th><th>Checker</th><th>Status</th><th>Mulai</th></tr></thead>
      <tbody>
      @forelse($cycles as $cycle)
        @php
          $whs = $cycle->warehouses;
          $checkers = $cycle->assignments->pluck('checker')->filter()->unique('id')->values();
        @endphp
        <tr>
          <td><a class="cycle-link" href="{{ route('admin.cycles.show', $cycle) }}">{{ $cycle->cycle_no }}</a><small class="cycle-sub">{{ $cycle->warehouses_count }} warehouse · {{ $cycle->assignments_count }} assignment</small></td>
          <td><span class="cycle-database">{{ $cycle->source_database }}</span></td>
          <td><div class="cycle-tags">@foreach($whs->take(3) as $wh)<span class="cycle-tag" title="{{ $wh->warehouse_name }}">{{ $wh->warehouse_code }} · {{ $wh->warehouse_name }}</span>@endforeach</div>@if($whs->count()>3)<small class="cycle-sub">+{{ $whs->count()-3 }} lainnya</small>@endif</td>
          <td><div class="cycle-tags">@forelse($checkers->take(4) as $person)<span class="cycle-tag">{{ $person->name }}</span>@empty<span class="cycle-muted">Belum ditugaskan</span>@endforelse</div>@if($checkers->count()>4)<small class="cycle-sub">+{{ $checkers->count()-4 }} checker lain</small>@endif</td>
          <td><span class="cycle-status {{ strtolower($cycle->status) }}">{{ $cycle->status }}</span></td>
          <td>{{ optional($cycle->started_at)->format('d/m/Y H:i') ?: '—' }}</td>
        </tr>
      @empty
        <tr><td class="cycle-empty" colspan="6">Tidak ditemukan cycle yang sesuai filter.</td></tr>
      @endforelse
      </tbody>
    </table></div>
    <div class="cycle-footer">@include('admin.partials.table-pagination', ['paginator' => $cycles])</div>
  </section>
</div>
@endsection
