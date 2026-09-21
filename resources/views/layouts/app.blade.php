<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Stock Opname') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css','resources/css/sidebar.css','resources/js/app.js'])
</head>
<body>
@auth
@php
    $u = auth()->user();
    $homeRoute = $u->isAdmin() ? route('admin.dashboard')
        : ($u->isAdminGudang() ? route('warehouse.admin.index')
        : ($u->isCheckerGudang() ? route('warehouse.checker.index')
        : ($u->isGerai() ? route('gerai.sampling.home') : route('checker.home'))));
@endphp
<div class="app-shell app-shell-sidebar" id="app-shell">
    <aside class="app-sidebar" id="app-sidebar" aria-label="Menu utama">
        <a href="{{ $homeRoute }}" class="sidebar-brand">
            <span class="brand-mark">SO</span>
            <span class="sidebar-brand-copy"><strong>Stock Opname</strong><small>{{ $u->role }}</small></span>
        </a>

        <nav class="sidebar-nav">
            @if($u->isAdmin())
                <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="sidebar-icon">⌂</span><span class="sidebar-label">Dashboard</span></a>
                <a class="sidebar-link {{ request()->routeIs('admin.cycles.*') ? 'active' : '' }}" href="{{ route('admin.cycles.index') }}"><span class="sidebar-icon">◫</span><span class="sidebar-label">Cycle Opname</span></a>
                <a class="sidebar-link {{ request()->routeIs('warehouse.admin.*') ? 'active' : '' }}" href="{{ route('warehouse.admin.index') }}"><span class="sidebar-icon">▦</span><span class="sidebar-label">Sampling Gudang</span></a>
                <a class="sidebar-link {{ request()->routeIs('admin.sampling.*') ? 'active' : '' }}" href="{{ route('admin.sampling.index') }}"><span class="sidebar-icon">◎</span><span class="sidebar-label">Sampling Gerai</span></a>
                <a class="sidebar-link {{ request()->routeIs('admin.barcodes.*') ? 'active' : '' }}" href="{{ route('admin.barcodes.index') }}"><span class="sidebar-icon">▥</span><span class="sidebar-label">Barcode</span></a>
                <a class="sidebar-link {{ request()->routeIs('admin.ratios.*') ? 'active' : '' }}" href="{{ route('admin.ratios.index') }}"><span class="sidebar-icon">⇄</span><span class="sidebar-label">Ratio</span></a>
                <a class="sidebar-link {{ request()->routeIs('label.*') ? 'active' : '' }}" href="{{ route('label.index') }}"><span class="sidebar-icon">▤</span><span class="sidebar-label">Label</span></a>
                <a class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><span class="sidebar-icon">♙</span><span class="sidebar-label">User</span></a>
            @elseif($u->isAdminGudang())
                <a class="sidebar-link {{ request()->routeIs('warehouse.admin.*') ? 'active' : '' }}" href="{{ route('warehouse.admin.index') }}"><span class="sidebar-icon">▦</span><span class="sidebar-label">Sampling Gudang</span></a>
            @elseif($u->isCheckerGudang())
                <a class="sidebar-link {{ request()->routeIs('warehouse.checker.*') ? 'active' : '' }}" href="{{ route('warehouse.checker.index') }}"><span class="sidebar-icon">✓</span><span class="sidebar-label">Tugas Sampling</span></a>
            @elseif($u->isGerai())
                <a class="sidebar-link {{ request()->routeIs('gerai.sampling.*') ? 'active' : '' }}" href="{{ route('gerai.sampling.home') }}"><span class="sidebar-icon">◎</span><span class="sidebar-label">Sampling Gerai</span></a>
                <a class="sidebar-link {{ request()->routeIs('label.*') ? 'active' : '' }}" href="{{ route('label.index') }}"><span class="sidebar-icon">▤</span><span class="sidebar-label">Label</span></a>
            @else
                <a class="sidebar-link {{ request()->routeIs('checker.*') ? 'active' : '' }}" href="{{ route('checker.home') }}"><span class="sidebar-icon">✓</span><span class="sidebar-label">Opname Saya</span></a>
            @endif
        </nav>

        <div class="sidebar-footer">
            <a class="sidebar-link {{ request()->routeIs('password.*') ? 'active' : '' }}" href="{{ route('password.edit') }}"><span class="sidebar-icon">⚿</span><span class="sidebar-label">Ganti Password</span></a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="sidebar-link sidebar-logout" type="submit"><span class="sidebar-icon">↪</span><span class="sidebar-label">Keluar</span></button></form>
        </div>
    </aside>
    <button class="sidebar-overlay" id="sidebar-overlay" type="button" aria-label="Tutup menu"></button>

    <div class="app-main">
        <header class="app-topbar">
            <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Buka atau tutup menu" aria-controls="app-sidebar" aria-expanded="true">☰</button>
            <div class="topbar-title"><strong>@yield('title', 'Stock Opname')</strong><small>{{ $u->name }}</small></div>
            <span class="topbar-role">{{ $u->role }}</span>
        </header>

        <main class="page-wrap @yield('page-class')">
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if($errors->any())
                <div class="alert danger"><strong>Ada yang perlu diperiksa.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script>
(() => {
    const shell = document.getElementById('app-shell');
    const toggle = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    if (!shell || !toggle) return;
    const mobile = () => window.matchMedia('(max-width: 900px)').matches;
    if (!mobile() && localStorage.getItem('stock-opname-sidebar-collapsed') === '1') shell.classList.add('sidebar-collapsed');
    const sync = () => toggle.setAttribute('aria-expanded', String(mobile() ? shell.classList.contains('sidebar-open') : !shell.classList.contains('sidebar-collapsed')));
    toggle.addEventListener('click', () => {
        if (mobile()) shell.classList.toggle('sidebar-open');
        else {
            shell.classList.toggle('sidebar-collapsed');
            localStorage.setItem('stock-opname-sidebar-collapsed', shell.classList.contains('sidebar-collapsed') ? '1' : '0');
        }
        sync();
    });
    overlay?.addEventListener('click', () => { shell.classList.remove('sidebar-open'); sync(); });
    window.addEventListener('resize', () => { if (!mobile()) shell.classList.remove('sidebar-open'); sync(); });
    sync();
})();
</script>
@else
<div class="app-shell"><main class="page-wrap @yield('page-class')">@yield('content')</main></div>
@endauth
@stack('scripts')
</body>
</html>
