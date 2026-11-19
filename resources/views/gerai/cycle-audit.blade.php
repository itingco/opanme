@extends('layouts.app')
@section('title','Audit Cycle Read Only')
@section('content')
@if(auth()->user()->isAdmin())
<details class="panel" style="margin-bottom:16px;padding:14px">
<summary><strong>Buat User Auditor Cycle (Read Only)</strong></summary>
<form method="POST" action="{{ route('cycle-audit.auditors.store') }}" class="stack-form" style="margin-top:12px">@csrf
<label>Nama<input name="name" required maxlength="150"></label>
<label>Username<input name="username" required maxlength="100"></label>
<label>Password (minimal 8 karakter)<input name="password" type="password" minlength="8" required></label>
<button class="btn primary" type="submit">Buat User Auditor</button>
</form>
</details>
@endif
<div class="page-heading"><div><h1>Riwayat Sampling & Cycle Final</h1><p>Hanya sampling CLOSED dan cycle opname FINALIZED. Urut waktu terbaru.</p></div></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Jenis</th><th>Cycle</th><th>Database</th><th>Gudang</th><th>Selisih</th><th>Detail</th></tr></thead><tbody>
@forelse($history as $r)
<tr><td>{{ $r['date']?->format('d/m/Y H:i:s') }}</td><td>{{ $r['type']==='gerai' ? 'Sampling CLOSED' : 'Opname FINALIZED' }}</td><td>{{ $r['number'] }}</td><td>{{ $r['database'] }}</td><td>{{ $r['warehouse'] }}</td><td>{{ $r['variance'] ?? '-' }}</td><td><a class="btn small" href="{{ $r['type']==='gerai' ? route('cycle-audit.gerai',$r['id']) : route('cycle-audit.final',$r['id']) }}">Lihat Selisih</a></td></tr>
@empty <tr><td colspan="7">Tidak ada cycle yang selesai.</td></tr> @endforelse
</tbody></table></div></section>
@endsection
