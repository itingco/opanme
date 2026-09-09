@extends('layouts.app')

@section('title', 'Ganti Password')

@section('content')
<div class="page-heading">
    <div>
        <h1>Ganti Password</h1>
        <p>Ubah password untuk akun <strong>{{ auth()->user()->username }}</strong>.</p>
    </div>
</div>

<section class="panel narrow">
    <div class="panel-head">
        <h2>Password Akun</h2>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="stack-form">
        @csrf
        @method('PUT')

        <label>
            Password Lama
            <input
                type="password"
                name="current_password"
                autocomplete="current-password"
                required
                autofocus
                placeholder="Masukkan password lama"
            >
        </label>

        <label>
            Password Baru
            <input
                type="password"
                name="password"
                autocomplete="new-password"
                required
                minlength="6"
                placeholder="Minimal 6 karakter"
            >
        </label>

        <label>
            Konfirmasi Password Baru
            <input
                type="password"
                name="password_confirmation"
                autocomplete="new-password"
                required
                minlength="6"
                placeholder="Ulangi password baru"
            >
        </label>

        <div style="margin-top: 6px;">
            <button type="submit" class="btn primary">
                Simpan Password
            </button>
        </div>
    </form>
</section>
@endsection
