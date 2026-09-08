@extends('layouts.app')
@section('title','Sampling Gerai')
@section('content')
<div class="page-heading"><div><h1>Sampling Stok Gerai</h1><p>Ambil barang bebas di rak, lalu cocokkan stok fisik dengan stok sistem.</p></div></div>
<section class="panel">
    <h2>Mulai Sample Cycle</h2>
    <form method="POST" action="{{ route('gerai.sampling.create') }}" class="stack-form" id="sampling-create-form">@csrf
        @if($user->hasGeraiWarehouseBinding())
            <div class="alert success"><strong>Gudang tetap:</strong> {{ $user->source_database }} · {{ $user->warehouse_code }} · {{ $user->warehouse_name }}</div>
        @else
            <label>Database ERP<select name="source_database" id="sampling-source" required><option value="">Pilih database</option>@foreach($databases as $db)<option value="{{ $db }}">{{ $db }}</option>@endforeach</select></label>
            <label>Gudang<select name="erp_warehouse_id" id="sampling-warehouse" required><option value="">Pilih database dulu</option></select></label>
        @endif
        <label>Lokasi / Rak<input name="location" maxlength="255" placeholder="Contoh: Rak A, Etalase Depan, Gudang Belakang" required></label>
        <button class="btn primary" type="submit">Generate Sample Cycle</button>
    </form>
</section>
<section class="panel">
    <h2>Riwayat Sample Cycle</h2>
    <div class="table-wrap"><table><thead><tr><th>Cycle</th><th>Mulai</th><th>Gudang</th><th>Lokasi Terakhir</th><th>Status</th><th class="num">Item</th><th></th></tr></thead><tbody>
    @forelse($cycles as $cycle)<tr><td><strong>{{ $cycle->cycle_no }}</strong></td><td>{{ $cycle->started_at?->format('d/m/Y H:i') }}</td><td>{{ $cycle->warehouse_code }} · {{ $cycle->warehouse_name }}</td><td>{{ $cycle->location }}</td><td>{{ $cycle->status }}</td><td class="num">{{ number_format($cycle->checks_count) }}</td><td>@if($cycle->status==='OPEN')<a class="btn small" href="{{ route('gerai.sampling.scan',$cycle) }}">Lanjut Scan</a>@endif</td></tr>@empty<tr><td colspan="7" class="empty">Belum ada sample cycle.</td></tr>@endforelse
    </tbody></table></div>{{ $cycles->links() }}
</section>
@endsection
@if(!$user->hasGeraiWarehouseBinding())
@push('scripts')<script>
document.addEventListener('DOMContentLoaded',()=>{const source=document.getElementById('sampling-source'), wh=document.getElementById('sampling-warehouse'), url=@json(route('gerai.sampling.warehouses'));source?.addEventListener('change',async()=>{wh.innerHTML='<option value="">Memuat...</option>';if(!source.value){wh.innerHTML='<option value="">Pilih database dulu</option>';return;}const r=await fetch(`${url}?source_database=${encodeURIComponent(source.value)}`,{headers:{Accept:'application/json'}});const j=await r.json();wh.innerHTML='<option value="">Pilih gudang</option>';(j.data||[]).forEach(w=>{const o=document.createElement('option');o.value=w.warehouse_id;o.textContent=`${w.warehouse_code} · ${w.warehouse_name}`;wh.appendChild(o);});});});
</script>@endpush
@endif
