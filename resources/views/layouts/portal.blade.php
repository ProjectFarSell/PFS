<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FarSell — Auction surplus. Everyday prices.')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Apply stored theme before first paint --}}
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="h-full font-sans antialiased transition-colors duration-200"
      style="background-color: rgb(var(--color-surface)); color: rgb(var(--color-text-base));">
    @yield('content')
</body>
</html>
