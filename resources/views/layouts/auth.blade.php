<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FarSell')</title>
    <x-primary-font />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased transition-colors duration-200"
      style="background-color: rgb(var(--color-surface)); color: rgb(var(--color-text-base));">
    <main class="flex-1">
        @yield('content')
    </main>
</body>
</html>
