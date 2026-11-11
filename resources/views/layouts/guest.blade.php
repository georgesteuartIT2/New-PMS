<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · GS ProjectFlow</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/workspace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/premium.css') }}?v={{ filemtime(public_path('css/premium.css')) }}">
    <script defer src="{{ asset('js/app.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/app-dialog.css') }}?v={{ filemtime(public_path('css/app-dialog.css')) }}">
    <script src="{{ asset('js/app-dialog.js') }}?v={{ filemtime(public_path('js/app-dialog.js')) }}"></script>
</head>
<body class="guest-body">
    @yield('content')
</body>
</html>
