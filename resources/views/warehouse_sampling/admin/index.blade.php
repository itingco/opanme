@extends('layouts.app')
@section('title','Sampling Gudang')
@section('content')
@vite('resources/css/warehouse-sampling.css')
<div class="page-heading"><div><h1>Sampling Gudang</h1><p>Buat periode, tentukan target, pilih item, lalu serahkan daftar ke Checker Gudang.</p></div></div>
<div class="ws-grid">
    <section class="panel">
        <h2>Buat Periode Baru</h2>
        <form method="POST" action="{{ route('warehouse.admin.store') }}" class="stack-form" id="ws-create-form">@csrf
            <label>Database ERP<select name="source_database" id="ws-source" required><option value="">Pilih database</option>@foreach($databases as $db)<option value="{{ $db }}" @selected(old('source_database')===$db)>{{ $db }}</option>@endforeach</select></label>
            <label>Gudang<select name="erp_warehouse_id" id="ws-warehouse" required><option value="">Pilih database dulu</option></select></label>
            <div class="ws-form-grid">
                <label>Target Checker (%)<input name="target_percentage" type="number" min="1" max="100" step="0.01" value="{{ old('target_percentage',100) }}" required></label>
                <label>Checker Gudang<select name="assigned_checker_id" required><option value="">Pilih checker</option>@foreach($checkers as $checker)<option value="{{ $checker->id }}" @selected((string)old('assigned_checker_id')===(string)$checker->id)>{{ $checker->name }} · {{ $checker->username }}</option>@endforeach</select></label>
            </div>
            <label>Area / Lokasi<input name="location" value="{{ old('location') }}" maxlength="255" placeholder="Contoh: Gudang Utama / Rak A-C" required></label>
            <label>Catatan<textarea name="notes" rows="3" placeholder="Opsional">{{ old('notes') }}</textarea></label>
            <button class="btn primary" type="submit">Buat Periode & Pilih Item</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Daftar Periode</h2><span class="ws-note">{{ number_format($periods->total()) }} periode</span></div>
        @forelse($periods as $period)
            @php
                $pct = $period->items_count > 0 ? min(100, ($period->checked_items_count / $period->items_count) * 100) : 0;
                $targetCount = $period->items_count > 0 ? (int) ceil($period->items_count * ((float)$period->target_percentage / 100)) : 0;
                $reached = $period->checked_items_count >= $targetCount && $targetCount > 0;
            @endphp
            <article class="ws-period-card">
                <div class="ws-period-head"><div><strong>{{ $period->cycle_no }}</strong><small>{{ $period->warehouse_code }} · {{ $period->warehouse_name }}</small></div><span class="ws-badge {{ strtolower($period->status) }}">{{ $period->status }}</span></div>
                <div class="ws-period-meta"><span><b>{{ $period->items_count }}</b>Baris item</span><span><b>{{ $period->checked_items_count }}</b>Sudah dicek</span><span><b>{{ number_format((float)$period->target_percentage,0) }}%</b>Target ({{ $targetCount }} baris)</span></div>
                <div><div class="ws-progress {{ $reached ? 'target-reached':'' }}"><span style="width:{{ $pct }}%"></span></div><small class="ws-note">Progress {{ number_format($pct,1) }}% · Checker: {{ $period->checker?->name ?? '-' }}</small></div>
                <a class="btn" href="{{ route('warehouse.admin.show',$period) }}">Kelola Periode</a>
            </article>
        @empty
            <div class="empty">Belum ada periode sampling gudang.</div>
        @endforelse
        {{ $periods->links() }}
    </section>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const source=document.getElementById('ws-source'), warehouse=document.getElementById('ws-warehouse'), url=@json(route('warehouse.admin.warehouses'));
 async function loadWarehouses(selected=''){
   warehouse.innerHTML='<option value="">Memuat...</option>';
   if(!source.value){warehouse.innerHTML='<option value="">Pilih database dulu</option>';return;}
   try{const r=await fetch(`${url}?source_database=${encodeURIComponent(source.value)}`,{headers:{Accept:'application/json'}});const j=await r.json();warehouse.innerHTML='<option value="">Pilih gudang</option>';(j.data||[]).forEach(w=>{const o=document.createElement('option');o.value=w.warehouse_id;o.textContent=`${w.warehouse_code} · ${w.warehouse_name}`;if(String(selected)===String(w.warehouse_id))o.selected=true;warehouse.appendChild(o);});}catch(e){warehouse.innerHTML='<option value="">Gagal memuat gudang</option>';}
 }
 source?.addEventListener('change',()=>loadWarehouses()); if(source?.value) loadWarehouses(@json(old('erp_warehouse_id','')));
});
</script>
@endpush
