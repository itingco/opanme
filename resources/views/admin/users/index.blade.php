@extends('layouts.app')
@section('title','User')
@section('content')
<div class="page-heading"><div><h1>User</h1><p>Kelola akun Admin dan Checker.</p></div></div>
<div class="split-grid">
<section class="panel"><div class="panel-head"><h2>Tambah User</h2></div><form method="POST" action="{{ route('admin.users.store') }}" class="stack-form">@csrf
<label>Nama<input name="name" required></label><label>Username<input name="username" required></label><label>Password<input type="password" name="password" minlength="6" required></label><label>Role<select name="role"><option>CHECKER</option><option>ADMIN</option></select></label><button class="btn primary" type="submit">Simpan User</button></form></section>
<section class="panel"><div class="panel-head"><h2>Daftar User</h2></div><div class="card-list">@foreach($users as $user)<form method="POST" action="{{ route('admin.users.update',$user) }}" class="edit-card">@csrf @method('PUT')<div class="grid-2"><label>Nama<input name="name" value="{{ $user->name }}"></label><label>Username<input name="username" value="{{ $user->username }}"></label><label>Role<select name="role"><option @selected($user->role==='CHECKER')>CHECKER</option><option @selected($user->role==='ADMIN')>ADMIN</option></select></label><label>Password baru<input type="password" name="password" placeholder="Kosong = tidak berubah"></label></div><label class="check"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Aktif</label><button class="btn small" type="submit">Update</button></form>@endforeach</div>{{ $users->links() }}</section>
</div>
@endsection
