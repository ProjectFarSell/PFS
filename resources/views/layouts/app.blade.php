@php
    $cartCount = $cartCount ?? 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FarSell')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased pb-20">
    <header class="sticky top-0 z-30 bg-orange-500 text-white">
        <div class="mx-auto max-w-5xl px-3 py-2 flex items-center gap-2">
            <a href="{{ route('home') }}" class="font-semibold tracking-tight text-lg shrink-0">FarSell</a>
            <form action="{{ route('catalog.index') }}" method="get" class="flex-1">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search Japan surplus, brands, lots"
                       class="w-full rounded-full border-0 bg-white px-4 py-2 text-sm text-stone-900 placeholder:text-stone-400 focus:ring-2 focus:ring-orange-200">
            </form>
            <a href="{{ route('cart.index') }}" class="relative shrink-0 text-sm font-medium hidden sm:inline">
                Cart
                @if($cartCount > 0)
                    <span class="absolute -right-2 -top-1 rounded-full bg-white text-orange-600 text-[10px] px-1.5">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
        @if(!empty($isGuestBrowse) && auth()->guest())
            <div class="bg-orange-600/80 text-center text-xs py-1">Guest mode · cart stays on this device · <a class="underline" href="{{ route('register') }}">create account</a></div>
        @endif
    </header>

    @if (session('status'))
        <div class="mx-auto max-w-5xl px-3 pt-3">
            <p class="rounded-lg bg-emerald-50 text-emerald-800 text-sm px-3 py-2">{{ session('status') }}</p>
        </div>
    @endif

    <main class="mx-auto max-w-5xl px-3 py-4">
        @yield('content')
    </main>

    <nav class="fixed bottom-0 inset-x-0 z-30 border-t border-stone-200 bg-white">
        <div class="mx-auto max-w-5xl grid grid-cols-5 text-[11px] text-stone-500">
            <a href="{{ route('home') }}" class="flex flex-col items-center py-2 {{ request()->routeIs('home') ? 'text-orange-600 font-semibold' : '' }}">Home</a>
            <a href="{{ route('catalog.index') }}" class="flex flex-col items-center py-2 {{ request()->routeIs('catalog.*') ? 'text-orange-600 font-semibold' : '' }}">Search</a>
            <a href="{{ route('cart.index') }}" class="flex flex-col items-center py-2 relative {{ request()->routeIs('cart.*') ? 'text-orange-600 font-semibold' : '' }}">
                Cart
                @if($cartCount > 0)
                    <span class="absolute top-1 right-1/4 h-1.5 w-1.5 rounded-full bg-orange-500"></span>
                @endif
            </a>
            <a href="{{ auth()->check() ? (auth()->user()->riderProfile ? route('rider.profile') : route('rider.register')) : route('register', ['intent' => 'rider']) }}" class="flex flex-col items-center py-2 {{ request()->routeIs('rider.*') ? 'text-orange-600 font-semibold' : '' }}">Rider</a>
            @auth
                <form method="post" action="{{ route('logout') }}" class="flex flex-col items-center py-2">
                    @csrf
                    <button type="submit" class="text-[11px]">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="flex flex-col items-center py-2">Account</a>
            @endauth
        </div>
    </nav>
</body>
</html>
