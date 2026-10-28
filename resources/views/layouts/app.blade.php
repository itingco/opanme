<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Stock Opname') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=20260923">
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar.css') }}?v=20260923">
    @stack('styles')
</head>
<body>
@auth
@php
    $u = auth()->user();
    $menuPermission = app(\App\Services\MenuPermissionService::class);
    $menuDefinitions = config('menu_permissions.menus', []);
    $homeName = $menuPermission->firstAllowedRoute((string)$u->role);
    $homeRoute = $homeName ? route($homeName) : route('password.edit');
    $icons = ['dashboard'=>'⌂','gerai_admin'=>'◎','gerai_reports'=>'≡','gerai_checker'=>'▦','warehouse_admin'=>'▦','warehouse_history'=>'≡','warehouse_checker'=>'✓','warehouse_legacy'=>'◫','cycles'=>'◫','legacy_checker'=>'✓','barcodes'=>'▥','label'=>'▤','ratios'=>'⇄','users'=>'♙'];
@endphp
<div class="app-shell app-shell-sidebar" id="app-shell">
    <aside class="app-sidebar" id="app-sidebar" aria-label="Menu utama">
        <a href="{{ $homeRoute }}" class="sidebar-brand">
            <span class="brand-mark">SO</span>
            <span class="sidebar-brand-copy"><strong>Stock Opname</strong><small>{{ $u->role }}</small></span>
        </a>
        <nav class="sidebar-nav">
            @foreach($menuDefinitions as $key => $menu)
                @if($menuPermission->allowed((string)$u->role, $key) && \Illuminate\Support\Facades\Route::has($menu['route']))
                    <a class="sidebar-link {{ collect($menu['match'])->contains(fn($pattern) => request()->routeIs($pattern)) ? 'active' : '' }}" href="{{ route($menu['route']) }}">
                        <span class="sidebar-icon">{{ $icons[$key] ?? '▤' }}</span>
                        <span class="sidebar-label">{{ $menu['label'] }}</span>
                    </a>
                @endif
            @endforeach
            @if($u->isAdmin())
                <a class="sidebar-link {{ request()->routeIs('admin.menu-permissions.*') ? 'active' : '' }}" href="{{ route('admin.menu-permissions.index') }}">
                    <span class="sidebar-icon">⚙</span><span class="sidebar-label">Hak Akses Menu</span>
                </a>
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
            @if($errors->any())<div class="alert danger"><strong>Ada yang perlu diperiksa.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
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
<script src="{{ asset('assets/vendor/zxing-browser.min.js') }}?v=20260923"></script>
<script src="{{ asset('assets/js/app.js') }}?v=20260923"></script>
@stack('scripts')
</body>
</html>
