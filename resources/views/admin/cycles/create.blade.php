@extends('layouts.app')
@section('title','Buat Cycle')
@section('content')
<div class="page-heading"><div><h1>Buat Cycle Baru</h1><p>Pilih database dan beberapa warehouse yang berada dalam area fisik stock opname.</p></div></div>
<section class="panel narrow"><form method="POST" action="{{ route('admin.cycles.store') }}" class="stack-form" id="cycle-form">@csrf
<label>Database ERP<select name="source_database" id="cycle-db" required><option value="">Pilih database</option>@foreach($databases as $db)<option value="{{ $db }}" @selected(old('source_database')===$db)>{{ $db }}</option>@endforeach</select></label>
<label>Tanggal Cutoff<input type="date" name="cutoff_date" value="{{ old('cutoff_date', now()->format('Y-m-d')) }}" required></label>
<div><div class="label-title">Warehouse aktif</div><div id="warehouse-list" class="warehouse-picker"><div class="empty compact">Pilih database untuk mengambil warehouse.</div></div></div>
<button class="btn primary full" type="submit">Buat Cycle & Lanjut Assignment</button></form></section>
@endsection
@push('scripts')
<script>
const dbSelect=document.getElementById('cycle-db'), list=document.getElementById('warehouse-list');
async function loadWarehouses(){ const db=dbSelect.value; if(!db){list.innerHTML='<div class="empty compact">Pilih database untuk mengambil warehouse.</div>';return;} list.innerHTML='<div class="empty compact">Mengambil warehouse ERP...</div>'; try{const r=await fetch(`{{ route('admin.erp.warehouses') }}?source_database=${encodeURIComponent(db)}`,{headers:{'Accept':'application/json'}}); const j=await r.json(); if(!r.ok) throw new Error(j.message||'Gagal'); list.innerHTML=j.data.map(w=>`<label class="warehouse-option"><input type="checkbox" name="warehouse_ids[]" value="${w.warehouse_id}"><span><strong>${w.warehouse_code}</strong><small>${w.warehouse_name}</small></span></label>`).join('')||'<div class="empty compact">Tidak ada warehouse aktif.</div>'; }catch(e){list.innerHTML=`<div class="danger-lite">${e.message}</div>`;} }
dbSelect.addEventListener('change',loadWarehouses); if(dbSelect.value) loadWarehouses();
</script>
@endpush
