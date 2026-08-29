@extends('layouts.app')
@section('title','Login')
@section('page-class','auth-page')
@section('content')
<section class="login-card">
    <div class="login-symbol">SO</div>
    <h1>Stock Opname</h1>
    <p>Masuk sebagai Admin atau Checker.</p>
    <form method="POST" action="{{ route('login.store') }}" class="stack-form">@csrf
        <label>Username<input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn primary full" type="submit">Masuk</button>
    </form>
    <div class="demo-note"><strong>Demo:</strong> admin / admin123 · checker1 / checker123</div>
</section>
@endsection
