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
        <header class="topbar">
            <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('checker.home') }}" class="brand">
                <span class="brand-mark">SO</span>
                <span><strong>Stock Opname</strong><small>{{ auth()->user()->role }}</small></span>
            </a>
            <nav class="top-actions">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.cycles.index') }}">Cycle</a>
                    <a href="{{ route('admin.ratios.index') }}">Ratio</a>
                    <a href="{{ route('admin.users.index') }}">User</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="link-button" type="submit">Keluar</button></form>
            </nav>
        </header>
    @endauth
    <main class="page-wrap @yield('page-class')">
        @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert danger"><strong>Ada yang perlu diperiksa.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
