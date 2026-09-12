<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QR Label 10x10')</title>
    <link rel="stylesheet" href="{{ asset('label-assets/css/label-app.css') }}">
    @stack('head')
</head>
<body class="app-body">
    @yield('content')
    @stack('scripts')
</body>
</html>
