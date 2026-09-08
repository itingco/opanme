@extends('layouts.app')
@section('title','User Management')
@section('content')
@php
    $q = $search ?? '';
    $hasFilters = $q !== '' || $role !== '' || $status !== '';
@endphp
<div class="page-heading master-page-heading">
    <div><h1>User Management</h1><p>Kelola Admin, Checker, dan User Gerai beserta binding gudangnya.</p></div>
    <button class="btn primary" type="button" id="user-create-open">+ Tambah User</button>
</div>
<section class="panel master-table-panel">
    <form method="GET" action="{{ route('admin.users.index') }}" class="table-filter-bar" id="user-filter-form">
        <label class="filter-field-wide"><span class="filter-label">Cari</span><input type="search" name="q" value="{{ $q }}" placeholder="Nama, username, gudang..."></label>
        <label class="filter-field"><span class="filter-label">Role</span><select name="role">
            <option value="">Semua</option><option value="ADMIN" @selected($role==='ADMIN')>ADMIN</option><option value="CHECKER" @selected($role==='CHECKER')>CHECKER</option><option value="GERAI" @selected($role==='GERAI')>GERAI</option>
        </select></label>
        <label class="filter-field"><span class="filter-label">Status</span><select name="status"><option value="">Semua</option><option value="active" @selected($status==='active')>Aktif</option><option value="inactive" @selected($status==='inactive')>Nonaktif</option></select></label>
        <label class="filter-field"><span class="filter-label">Baris</span><select name="per_page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected($perPage===$size)>{{ $size }}</option>@endforeach</select></label>
        <div class="filter-actions"><button class="btn primary">Terapkan</button><a class="btn" href="{{ route('admin.users.index') }}">Reset</a></div>
    </form>
    <div class="table-wrap"><table class="master-table"><thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Gudang Gerai</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @forelse($users as $user)
        <tr>
            <td><strong>{{ $user->name }}</strong></td><td>{{ $user->username }}</td><td><span class="role-badge {{ strtolower($user->role) }}">{{ $user->role }}</span></td>
            <td>@if($user->role==='GERAI') {{ $user->erp_warehouse_id ? (($user->warehouse_code ?? '-').' · '.($user->warehouse_name ?? '-')) : 'Bebas pilih gudang' }} @else - @endif</td>
            <td>{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</td>
            <td><button type="button" class="btn small user-edit-btn" data-user-edit data-action="{{ route('admin.users.update',$user) }}" data-name="{{ $user->name }}" data-username="{{ $user->username }}" data-role="{{ $user->role }}" data-active="{{ $user->is_active ? '1':'0' }}" data-source="{{ $user->source_database }}" data-warehouse="{{ $user->erp_warehouse_id }}">Edit</button></td>
        </tr>
    @empty <tr><td colspan="6" class="empty">Belum ada user.</td></tr> @endforelse
    </tbody></table></div>
    @include('admin.partials.table-pagination',['paginator'=>$users])
</section>

@php($databases = [\App\Models\StockOpnameCycle::DB_INGCO, \App\Models\StockOpnameCycle::DB_SMI])
<div class="user-modal" id="user-create-modal" hidden><div class="user-modal-backdrop" data-create-close></div><div class="user-modal-dialog"><div class="user-modal-head"><div><h2>Tambah User</h2><p>Untuk GERAI, gudang boleh dikosongkan agar user bebas memilih.</p></div><button type="button" class="user-modal-close" data-create-close>×</button></div>
<form method="POST" action="{{ route('admin.users.store') }}" class="stack-form gerai-user-form">@csrf
<label>Nama<input name="name" required></label><label>Username<input name="username" required></label><label>Password<input type="password" name="password" minlength="6" required></label>
<label>Role<select name="role" data-role-select><option value="CHECKER">CHECKER</option><option value="GERAI">GERAI</option><option value="ADMIN">ADMIN</option></select></label>
<div data-gerai-binding hidden><label>Database ERP<select name="source_database" data-source-select><option value="">-- Bebas pilih saat sampling --</option>@foreach($databases as $db)<option value="{{ $db }}">{{ $db }}</option>@endforeach</select></label><label>Gudang<select name="erp_warehouse_id" data-warehouse-select><option value="">-- Tidak diikat / bebas --</option></select></label><small>Jika gudang dipilih, User Gerai hanya dapat sampling gudang tersebut.</small></div>
<div class="user-modal-actions"><button type="button" class="btn" data-create-close>Batal</button><button class="btn primary">Simpan User</button></div></form></div></div>

<div class="user-modal" id="user-edit-modal" hidden><div class="user-modal-backdrop" data-edit-close></div><div class="user-modal-dialog"><div class="user-modal-head"><div><h2>Edit User</h2></div><button type="button" class="user-modal-close" data-edit-close>×</button></div>
<form method="POST" id="user-edit-form" class="stack-form gerai-user-form">@csrf @method('PUT')
<label>Nama<input id="edit-name" name="name" required></label><label>Username<input id="edit-username" name="username" required></label><label>Role<select id="edit-role" name="role" data-role-select><option value="CHECKER">CHECKER</option><option value="GERAI">GERAI</option><option value="ADMIN">ADMIN</option></select></label>
<div data-gerai-binding hidden><label>Database ERP<select id="edit-source" name="source_database" data-source-select><option value="">-- Bebas pilih saat sampling --</option>@foreach($databases as $db)<option value="{{ $db }}">{{ $db }}</option>@endforeach</select></label><label>Gudang<select id="edit-warehouse" name="erp_warehouse_id" data-warehouse-select><option value="">-- Tidak diikat / bebas --</option></select></label></div>
<label>Password Baru<input type="password" name="password" minlength="6" placeholder="Kosong = tidak berubah"></label><label><input id="edit-active" type="checkbox" name="is_active" value="1"> Akun Aktif</label>
<div class="user-modal-actions"><button type="button" class="btn" data-edit-close>Batal</button><button class="btn primary">Simpan Perubahan</button></div></form></div></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const warehouseUrl = @json(route('admin.erp.warehouses'));
  async function populate(form, selected='') {
    const role=form.querySelector('[data-role-select]'); const box=form.querySelector('[data-gerai-binding]'); const source=form.querySelector('[data-source-select]'); const wh=form.querySelector('[data-warehouse-select]');
    box.hidden = role.value !== 'GERAI'; if (box.hidden) return;
    const src=source.value; wh.innerHTML='<option value="">-- Tidak diikat / bebas --</option>'; if(!src) return;
    const r=await fetch(`${warehouseUrl}?source_database=${encodeURIComponent(src)}`,{headers:{Accept:'application/json'}}); if(!r.ok) return; const j=await r.json();
    (j.data||[]).forEach(w=>{const o=document.createElement('option');o.value=w.warehouse_id;o.textContent=`${w.warehouse_code} · ${w.warehouse_name}`;if(String(w.warehouse_id)===String(selected))o.selected=true;wh.appendChild(o);});
  }
  document.querySelectorAll('.gerai-user-form').forEach(form=>{form.querySelector('[data-role-select]')?.addEventListener('change',()=>populate(form));form.querySelector('[data-source-select]')?.addEventListener('change',()=>populate(form));});
  const cm=document.getElementById('user-create-modal'), em=document.getElementById('user-edit-modal'), ef=document.getElementById('user-edit-form');
  const show=m=>{m.hidden=false;document.body.classList.add('modal-open')}; const hide=m=>{m.hidden=true;document.body.classList.remove('modal-open')};
  document.getElementById('user-create-open')?.addEventListener('click',()=>show(cm)); cm?.querySelectorAll('[data-create-close]').forEach(x=>x.addEventListener('click',()=>hide(cm)));
  em?.querySelectorAll('[data-edit-close]').forEach(x=>x.addEventListener('click',()=>hide(em)));
  document.querySelectorAll('[data-user-edit]').forEach(btn=>btn.addEventListener('click',async()=>{ef.action=btn.dataset.action;document.getElementById('edit-name').value=btn.dataset.name||'';document.getElementById('edit-username').value=btn.dataset.username||'';document.getElementById('edit-role').value=btn.dataset.role||'CHECKER';document.getElementById('edit-active').checked=btn.dataset.active==='1';document.getElementById('edit-source').value=btn.dataset.source||'';show(em);await populate(ef,btn.dataset.warehouse||'');}));
});
</script>
@endpush
