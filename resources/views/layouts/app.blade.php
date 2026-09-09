<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Stock Opname') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    @auth
        @php
            $homeRoute = auth()->user()->isAdmin() ? route('admin.dashboard') : (auth()->user()->isGerai() ? route('gerai.sampling.home') : route('checker.home'));
        @endphp
        <header class="topbar">
            <a href="{{ $homeRoute }}" class="brand">
                <span class="brand-mark">SO</span>
                <span>
                    <strong>Stock Opname</strong>
                    <small>{{ auth()->user()->role }}</small>
                </span>
            </a>

            <nav class="top-actions">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.cycles.index') }}">Cycle</a>
                    <a href="{{ route('admin.sampling.index') }}">Sampling</a>
                    <a href="{{ route('admin.barcodes.index') }}">Barcode</a>
                    <a href="{{ route('admin.ratios.index') }}">Ratio</a>
                    <a href="{{ route('admin.users.index') }}">User</a>
                @elseif(auth()->user()->isGerai())
                    <a href="{{ route('gerai.sampling.home') }}">Sampling Gerai</a>
                @endif

                <span>
                    <a href="{{ route('password.edit') }}" title="Ganti Password">Password</a>
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="link-button" type="submit">Keluar</button>
                </form>
            </nav>
        </header>
    @endauth

    <main class="page-wrap @yield('page-class')">
        @if(session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert danger">
                <strong>Ada yang perlu diperiksa.</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
