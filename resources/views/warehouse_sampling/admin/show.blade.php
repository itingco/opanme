@extends('layouts.app')
@section('title','Detail Sampling Gudang')
@section('content')
@vite('resources/css/warehouse-sampling.css')
@php
$total=(int)$period->items_count; $checked=(int)$period->checked_items_count;
$pct=$total>0?min(100,($checked/$total)*100):0;
$targetCount=$total>0?(int)ceil($total*((float)$period->target_percentage/100)):0;
$reached=$targetCount>0 && $checked >= $targetCount;
@endphp
<div class="page-heading"><div><h1>{{ $period->cycle_no }}</h1><p>{{ $period->source_database }} · {{ $period->warehouse_code }} · {{ $period->warehouse_name }}</p></div><a class="btn" href="{{ route('warehouse.admin.index') }}">← Kembali</a></div>
<div class="ws-metrics"><div class="ws-metric"><span>Status</span><strong>{{ $period->status }}</strong></div><div class="ws-metric"><span>Item Dipilih</span><strong>{{ $total }}</strong></div><div class="ws-metric"><span>Sudah Dicek</span><strong>{{ $checked }}</strong></div><div class="ws-metric"><span>Target</span><strong>{{ number_format((float)$period->target_percentage,0) }}%</strong></div></div>
<section class="panel"><div class="ws-progress {{ $reached?'target-reached':'' }}"><span style="width:{{ $pct }}%"></span></div><p class="ws-note">Progress {{ number_format($pct,1) }}% · target minimal {{ $targetCount }} dari {{ $total }} baris. {{ $reached ? 'Target sudah tercapai.' : 'Target belum tercapai.' }}</p></section>

<div class="ws-grid">
<section class="panel">
<h2>Pengaturan Periode</h2>
<form method="POST" action="{{ route('warehouse.admin.update',$period) }}" class="stack-form">@csrf @method('PUT')
<div class="ws-form-grid"><label>Target (%)<input name="target_percentage" type="number" min="1" max="100" step="0.01" value="{{ $period->target_percentage }}" required></label><label>Checker Gudang<select name="assigned_checker_id" required>@foreach($checkers as $checker)<option value="{{ $checker->id }}" @selected($period->assigned_checker_id===$checker->id)>{{ $checker->name }} · {{ $checker->username }}</option>@endforeach</select></label></div>
<label>Area / Lokasi<input name="location" value="{{ $period->location }}" required></label><label>Catatan<textarea name="notes" rows="3">{{ $period->notes }}</textarea></label>
@if(!$period->isClosed())<button class="btn" type="submit">Simpan Pengaturan</button>@endif
</form>
<div class="ws-actions" style="margin-top:14px">
@if($period->isDraft())<form method="POST" action="{{ route('warehouse.admin.release',$period) }}" onsubmit="return confirm('Kunci daftar item dan serahkan ke checker? Setelah OPEN daftar item tidak dapat diubah.')">@csrf<button class="btn primary">Serahkan ke Checker</button></form>@endif
@if(!$period->isClosed())<form method="POST" action="{{ route('warehouse.admin.close',$period) }}" onsubmit="return confirm('Tutup periode sampling ini?')">@csrf<button class="btn danger">Tutup Periode</button></form>@endif
</div>
</section>

<section class="panel">
<h2>Tambah Item Sampling</h2>
@if($period->isDraft())
<p class="ws-lock-note">Item yang sama boleh ditambahkan berulang kali. Setiap penambahan menjadi baris sampling terpisah.</p>
<div class="ws-search-box"><label class="stack-form">Cari item ERP<input id="ws-item-search" autocomplete="off" placeholder="Ketik minimal 2 karakter kode / nama item"></label><div id="ws-item-results" class="ws-search-results" hidden></div></div>
<form method="POST" action="{{ route('warehouse.admin.items.store',$period) }}" id="ws-add-item-form">@csrf<input type="hidden" name="item_id" id="ws-item-id"></form>
@else
<div class="ws-lock-note">Daftar item sudah dikunci karena periode telah diserahkan ke checker.</div>
@endif
<p class="ws-note">Qty sistem disnapshot saat item ditambahkan. Checker hanya menginput qty fisik yang ditemukan.</p>
</section>
</div>

<section class="panel"><div class="panel-head"><h2>Daftar Item Terpilih</h2><span class="ws-note">{{ number_format($items->total()) }} baris</span></div><div class="table-wrap"><table><thead><tr><th>#</th><th>Item</th><th>UOM</th><th class="num">Sistem</th><th class="num">Fisik</th><th class="num">Selisih</th><th>Hasil</th><th>Dicek</th><th></th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->line_no }}</td><td><strong>{{ $item->item_code }}</strong><small>{{ $item->item_name }}</small></td><td>{{ $item->uom_code }}</td><td class="num">{{ number_format((float)$item->system_qty,4,'.',',') }}</td><td class="num">{{ $item->checked_at ? number_format((float)$item->physical_qty,4,'.',',') : '-' }}</td><td class="num">{{ $item->checked_at ? number_format((float)$item->physical_qty-(float)$item->system_qty,4,'.',',') : '-' }}</td><td>{{ $item->result ?? '-' }}</td><td>{{ $item->checked_at?->format('d/m/Y H:i') ?? '-' }}@if($item->checker)<small>{{ $item->checker->name }}</small>@endif</td><td>@if($period->isDraft())<form method="POST" action="{{ route('warehouse.admin.items.destroy',[$period,$item]) }}" onsubmit="return confirm('Hapus baris ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form>@endif</td></tr>@empty<tr><td colspan="9" class="empty">Belum ada item. Cari item di atas lalu tambahkan.</td></tr>@endforelse
</tbody></table></div>{{ $items->links() }}</section>
@endsection
@push('scripts')
@if($period->isDraft())
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const input=document.getElementById('ws-item-search'), results=document.getElementById('ws-item-results'), form=document.getElementById('ws-add-item-form'), hidden=document.getElementById('ws-item-id'), url=@json(route('warehouse.admin.items.search',$period)); let timer;
 const close=()=>{results.hidden=true;results.innerHTML='';};
 input?.addEventListener('input',()=>{clearTimeout(timer);const q=input.value.trim();if(q.length<2){close();return;}timer=setTimeout(async()=>{results.hidden=false;results.innerHTML='<div class="empty compact">Mencari...</div>';try{const r=await fetch(`${url}?q=${encodeURIComponent(q)}`,{headers:{Accept:'application/json'}});const j=await r.json();results.innerHTML='';if(!(j.data||[]).length){results.innerHTML='<div class="empty compact">Item tidak ditemukan.</div>';return;}(j.data||[]).forEach(i=>{const b=document.createElement('button');b.type='button';b.className='ws-search-result';b.innerHTML=`<span><strong>${escapeHtml(i.item_code)}</strong><small>${escapeHtml(i.item_name)}</small></span><span>${escapeHtml(i.uom_code)}</span>`;b.addEventListener('click',()=>{hidden.value=i.item_id;form.submit();});results.appendChild(b);});}catch(e){results.innerHTML='<div class="empty compact">Gagal mengambil item ERP.</div>'; }},300);});
 document.addEventListener('click',e=>{if(!results.contains(e.target)&&e.target!==input)close();});
 function escapeHtml(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
});
</script>
@endif
@endpush
