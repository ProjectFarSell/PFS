@php
    $cartCount     = $cartCount     ?? 0;
    $navCategories = $navCategories ?? collect();
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FarSell')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Apply stored theme before first paint to prevent flash --}}
    <script src="{{ asset('js/theme.js') }}"></script>
</head>

<body class="min-h-screen antialiased font-sans transition-colors duration-200"
      style="background-color: rgb(var(--color-surface)); color: rgb(var(--color-text-base));">

    {{-- ══════════════════════════════════════════════════════════════
         TOP NAVIGATION  —  Extremerate style
         Desktop: logo | category dropdowns | search | icons | toggle
         Mobile:  logo | search | hamburger
         ══════════════════════════════════════════════════════════════ --}}
    <header class="sticky top-0 z-40 border-b transition-colors duration-200"
            style="background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border) / 0.5);"
            x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false">

        {{-- ── Top utility bar (desktop only) ─────────────────────── --}}
        <div class="hidden md:block border-b text-xs"
             style="background-color: rgb(var(--color-surface-muted)); border-color: rgb(var(--color-surface-border) / 0.4); color: rgb(var(--color-text-muted));">
            <div class="mx-auto max-w-7xl px-4 py-1.5 flex items-center justify-between">
                <span>Doorzo-style auction lots · Shopee-fast checkout</span>
                <div class="flex items-center gap-4">
                    @guest
                        <a href="{{ route('login') }}" class="hover:underline" style="color: rgb(var(--color-accent));">Sign in</a>
                        <span style="color: rgb(var(--color-surface-border));">/</span>
                        <a href="{{ route('register') }}" class="hover:underline" style="color: rgb(var(--color-accent));">Register</a>
                    @endguest
                    @auth
                        <a href="{{ route('account.profile') }}" class="hover:underline" style="color: rgb(var(--color-accent));">{{ auth()->user()->name }}</a>
                    @endauth
                </div>
            </div>
        </div>

        {{-- ── Main nav bar ─────────────────────────────────────────── --}}
        <div class="mx-auto max-w-7xl px-4">
            <div class="flex items-center gap-4 h-14">

                {{-- Logo --}}
                <a href="{{ route('home') }}"
                   class="shrink-0 flex items-center gap-2 text-xl font-bold"
                   style="color: rgb(var(--color-accent));">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-black"
                          style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">F</span>
                    <span class="hidden sm:inline">FarSell</span>
                </a>

                {{-- ── Desktop: category dropdown nav ────────────────── --}}
                <nav class="hidden lg:flex items-center gap-1" aria-label="Category navigation">

                    {{-- All Products --}}
                    <a href="{{ route('catalog.index') }}"
                       class="nav-link px-3 py-1"
                       @if(request()->routeIs('catalog.*')) aria-current="page" @endif>
                        Browse
                    </a>

                    {{-- Categories mega-dropdown --}}
                    <div class="relative" x-data="{ open: false }"
                         @mouseenter="open = true" @mouseleave="open = false">
                        <button @keydown.escape.window="open = false" @click.outside="open = false" @click="open = !open"
                                class="nav-link px-3 py-1 flex items-center gap-1"
                                :aria-expanded="open" aria-haspopup="true">
                            Categories
                            <svg class="h-3.5 w-3.5 transition-transform duration-150"
                                 :class="open && 'rotate-180'"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="dropdown-panel w-56"
                             style="display:none;">
                            @foreach ($navCategories as $cat)
                                <a href="{{ route('catalog.index', ['category' => $cat->id]) }}"
                                   class="dropdown-item">
                                    <span class="text-base leading-none w-5 text-center">{{ $cat->icon }}</span>
                                    {{ $cat->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Shops --}}
                    <a href="{{ route('shops.index') }}"
                       class="nav-link px-3 py-1" @if(request()->routeIs('shops.*')) aria-current="page" @endif>Shops</a>

                    {{-- Rider --}}
                    <a href="{{ auth()->check()
                            ? (auth()->user()->isRider() ? route('rider.dashboard') : route('rider.register'))
                            : route('register', ['intent' => 'rider']) }}"
                       class="nav-link px-3 py-1"
                       @if(request()->routeIs('rider.*')) aria-current="page" @endif>
                        {{ auth()->check() && auth()->user()->isRider() ? 'Rider Dashboard' : 'Become a Rider' }}
                    </a>
                </nav>

                {{-- ── Expandable search (flex-1 on desktop) ─────────── --}}
                <div class="min-w-0 flex-1 max-w-xl mx-2 lg:mx-4"
                     x-data="{ focused: false }">
                    <form action="{{ route('catalog.index') }}" method="get"
                          class="relative" role="search">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 transition-colors"
                             :style="focused ? 'color:rgb(var(--color-accent))' : 'color:rgb(var(--color-text-muted))'"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="m21 21-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        <input type="search" name="q" value="{{ request('q') }}"
                               placeholder="Search Japan surplus, brands, lots…"
                               aria-label="Search products"
                               @focus="focused = true" @blur="focused = false"
                               class="fs-input pl-10 pr-4 py-2 rounded-full"
                               style="padding-top:0.5rem; padding-bottom:0.5rem;">
                    </form>
                </div>

                {{-- ── Right icons cluster ─────────────────────────────── --}}
                <div class="flex items-center gap-1.5 shrink-0">

                    {{-- Cart --}}
                    <a href="{{ route('cart.index') }}"
                       class="theme-toggle relative"
                       aria-label="Cart{{ $cartCount > 0 ? " ($cartCount items)" : '' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                        </svg>
                        @if ($cartCount > 0)
                            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center
                                         rounded-full text-[9px] font-bold leading-none"
                                  style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">
                                {{ $cartCount > 9 ? '9+' : $cartCount }}
                            </span>
                        @endif
                    </a>

                    {{-- Profile (auth only, desktop) --}}
                    @auth
                        <div class="relative hidden lg:block" x-data="{ open: false }"
                             @click.outside="open = false">
                            <button @click="open = !open"
                                    class="theme-toggle"
                                    :aria-expanded="open"
                                    aria-label="Account menu">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                                </svg>
                            </button>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="dropdown-panel right-0 left-auto w-48"
                                 style="display:none;">

                                @if(auth()->user()->isRider())
                                    <a href="{{ route('rider.dashboard') }}" class="dropdown-item" @if(request()->routeIs('rider.dashboard')) aria-current="page" @endif>Rider Dashboard</a>
                                @endif
                                @if(auth()->user()->role === \App\Enums\UserRole::Seller)
                                    <a href="{{ route('seller.dashboard') }}" class="dropdown-item" @if(request()->routeIs('seller.*')) aria-current="page" @endif>Seller Dashboard</a>
                                @endif
                                @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                                    <a href="{{ route('admin.dashboard') }}" class="dropdown-item" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Admin Dashboard</a>
                                @endif
                                <a href="{{ route('account.profile') }}" class="dropdown-item">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0"/></svg>
                                    My Profile
                                </a>

                                @if(!in_array(auth()->user()->role, [\App\Enums\UserRole::Seller, \App\Enums\UserRole::Rider], true))
                                <a href="{{ route('account.addresses.index') }}" class="dropdown-item">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                                    My Addresses
                                </a>
                                @endif
                                <div class="my-1 border-t" style="border-color: rgb(var(--color-surface-border) / 0.4);"></div>
                                <form method="post" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item w-full text-left"
                                            style="color: rgb(var(--color-error));">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                                        Sign out
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endauth

                    {{-- Theme toggle --}}
                    <button class="theme-toggle"
                            @click="$theme.toggle()"
                            :aria-label="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                            x-data>
                        {{-- Sun (shown in dark mode) --}}
                        <svg x-show="$theme.isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
                        </svg>
                        {{-- Moon (shown in light mode) --}}
                        <svg x-show="!$theme.isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75 9.75 9.75 0 0 1 8.25 6c0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 12c0 5.385 4.365 9.75 9.75 9.75 4.797 0 8.818-3.462 9.75-8.002-.083.084-.166.165-.25.252v-.002Z"/>
                        </svg>
                    </button>

                    {{-- Mobile hamburger --}}
                    <button class="theme-toggle lg:hidden"
                            @click="mobileOpen = !mobileOpen"
                            :aria-expanded="mobileOpen"
                            aria-label="Toggle menu">
                        <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                        <svg x-show="mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2" style="display:none;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Mobile drawer menu ───────────────────────────────────── --}}
        <div x-show="mobileOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="lg:hidden border-t pb-4"
             style="display:none; background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border) / 0.4);">
            <nav class="mx-auto max-w-7xl px-4 pt-3 space-y-1" aria-label="Mobile navigation">
                <a href="{{ route('home') }}" class="dropdown-item rounded-xl"
                   @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
                <a href="{{ route('catalog.index') }}" class="dropdown-item rounded-xl"
                   @if(request()->routeIs('catalog.*')) aria-current="page" @endif>Browse All</a>
                <a href="{{ route('shops.index') }}" class="dropdown-item rounded-xl"
                   @if(request()->routeIs('shops.*')) aria-current="page" @endif>Shops</a>

                {{-- Category links (collapsed by default) --}}
                <div x-data="{ catOpen: false }">
                    <button @click="catOpen = !catOpen"
                            class="dropdown-item rounded-xl w-full text-left justify-between">
                        <span>Categories</span>
                        <svg class="h-4 w-4 transition-transform" :class="catOpen && 'rotate-180'"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="catOpen" class="pl-4 space-y-0.5 mt-1" style="display:none;">
                        @foreach ($navCategories as $cat)
                            <a href="{{ route('catalog.index', ['category' => $cat->id]) }}"
                               class="dropdown-item rounded-xl text-sm">
                                <span>{{ $cat->icon }}</span> {{ $cat->name }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <a href="{{ route('cart.index') }}" class="dropdown-item rounded-xl"
                   @if(request()->routeIs('cart.*')) aria-current="page" @endif>
                    Cart @if($cartCount > 0)
                        <span class="ml-auto badge badge-accent">{{ $cartCount }}</span>
                    @endif
                </a>

                @auth
                                @if(auth()->user()->isRider())
                                    <a href="{{ route('rider.dashboard') }}" class="dropdown-item" @if(request()->routeIs('rider.dashboard')) aria-current="page" @endif>Rider Dashboard</a>
                                @endif
                                @if(auth()->user()->role === \App\Enums\UserRole::Seller)
                                    <a href="{{ route('seller.dashboard') }}" class="dropdown-item" @if(request()->routeIs('seller.*')) aria-current="page" @endif>Seller Dashboard</a>
                                @endif
                                @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                                    <a href="{{ route('admin.dashboard') }}" class="dropdown-item" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Admin Dashboard</a>
                                @endif
                    <a href="{{ route('account.profile') }}" class="dropdown-item rounded-xl"
                       @if(request()->routeIs('account.profile')) aria-current="page" @elseif(request()->routeIs('orders.*', 'account.addresses.*')) aria-current="true" @endif>My Profile</a>

                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item rounded-xl w-full text-left"
                                style="color: rgb(var(--color-error));">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="dropdown-item rounded-xl">Sign in</a>
                    <a href="{{ route('register') }}" class="dropdown-item rounded-xl">Create account</a>
                @endauth
            </nav>
        </div>

        {{-- Guest browse banner --}}
        @if (!empty($isGuestBrowse) && auth()->guest() && empty($checkoutIntent))
            <div class="border-t py-1.5 text-center text-xs"
                 style="background-color: rgb(var(--color-accent-subtle)); border-color: rgb(var(--color-surface-border) / 0.4); color: rgb(var(--color-accent));">
                Guest mode — cart is saved on this device ·
                <a href="{{ route('register') }}" class="font-semibold underline underline-offset-2">
                    Create an account
                </a>
            </div>
        @endif
    </header>

    {{-- ── Flash status ────────────────────────────────────────────── --}}
    @if (session('status'))
        <div class="mx-auto max-w-7xl px-4 pt-4">
            <p class="alert-success">{{ session('status') }}</p>
        </div>
    @endif

    {{-- ── Page content ────────────────────────────────────────────── --}}
    <main class="mx-auto max-w-7xl px-4 py-5">
        @yield('content')
    </main>

    @stack('scripts')

    {{-- ══════════════════════════════════════════════════════════════
         FOOTER  —  Doorzo style: multi-column + payment icons
         ══════════════════════════════════════════════════════════════ --}}
    <footer style="background-color: rgb(var(--color-footer-bg)); color: rgb(var(--color-footer-text));">

        {{-- ── Main columns ──────────────────────────────────────────── --}}
        <div class="mx-auto max-w-7xl px-4 pt-12 pb-8">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8">

                {{-- Brand --}}
                <div class="col-span-2 md:col-span-1 lg:col-span-1">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 mb-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-black"
                              style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">F</span>
                        <span class="text-lg font-bold text-white">FarSell</span>
                    </a>
                    <p class="text-xs leading-relaxed" style="color: rgb(var(--color-footer-text) / 0.7);">
                        Doorzo-style auction surplus. Buy Japan lots at everyday Philippine prices.
                    </p>
                </div>

                {{-- Shopping --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-widest text-white mb-3">Shopping</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('catalog.index') }}" class="hover:text-white transition-colors">Browse All</a></li>
                        <li><a href="{{ route('shops.index') }}" class="hover:text-white transition-colors">Shops</a></li>

                        @foreach ($navCategories->take(4) as $cat)
                            <li>
                                <a href="{{ route('catalog.index', ['category' => $cat->id]) }}"
                                   class="hover:text-white transition-colors">
                                    {{ $cat->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Account --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-widest text-white mb-3">Account</h3>
                    <ul class="space-y-2 text-sm">
                        @auth
                            <li><a href="{{ route('account.profile') }}" class="hover:text-white transition-colors">My Profile</a></li>

                            @if(auth()->user()->role === \App\Enums\UserRole::Seller)
                                <li><a href="{{ route('seller.dashboard') }}" class="hover:text-white transition-colors">Seller Dashboard</a></li>
                            @elseif(auth()->user()->role === \App\Enums\UserRole::Rider)
                                <li><a href="{{ route('rider.dashboard') }}" class="hover:text-white transition-colors">Rider Dashboard</a></li>
                            @else
                                <li><a href="{{ route('account.addresses.index') }}" class="hover:text-white transition-colors">My Addresses</a></li>
                            @endif
                            <li><a href="{{ route('cart.index') }}" class="hover:text-white transition-colors">Cart</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Sign In</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Register</a></li>
                        @endauth
                    </ul>
                </div>

                {{-- Services --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-widest text-white mb-3">Services</h3>
                    <ul class="space-y-2 text-sm">
                        <li>
                            <a href="{{ auth()->check()
                                    ? (auth()->user()->isRider() ? route('rider.dashboard') : route('rider.register'))
                                    : route('register', ['intent' => 'rider']) }}"
                               class="hover:text-white transition-colors">{{ auth()->check() && auth()->user()->isRider() ? 'Rider Dashboard' : 'Become a Rider' }}</a>
                        </li>
                        <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Sell on FarSell</a></li>
                        <li><span class="text-xs">Buyer Protection (coming soon)</span></li>
                        <li><span class="text-xs">Help Center (coming soon)</span></li>
                    </ul>
                </div>

                {{-- About --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-widest text-white mb-3">About</h3>
                    <ul class="space-y-2 text-sm">
                        <li><span class="text-xs">About FarSell (coming soon)</span></li>
                        <li><span class="text-xs">Privacy Policy (coming soon)</span></li>
                        <li><span class="text-xs">Terms of Service (coming soon)</span></li>
                        <li><span class="text-xs">Contact Us (coming soon)</span></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- ── Divider ─────────────────────────────────────────────── --}}
        <div class="mx-auto max-w-7xl px-4">
            <div class="border-t" style="border-color: rgb(255 255 255 / 0.08);"></div>
        </div>

        {{-- ── Bottom row: payment icons + copyright ─────────────────── --}}
        <div class="mx-auto max-w-7xl px-4 py-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">

                {{-- Payment method icons (SVG placeholder badges) --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs mr-1" style="color: rgb(var(--color-footer-text) / 0.5);">Payment:</span>
                    @foreach (['COD', 'Card / e-wallet (demo only)'] as $method)
                        <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold border"
                              style="border-color: rgb(255 255 255 / 0.15); color: rgb(var(--color-footer-text) / 0.7);">
                            {{ $method }}
                        </span>
                    @endforeach
                </div>

                {{-- Copyright + theme toggle --}}
                <div class="flex items-center gap-3">
                    <p class="text-xs" style="color: rgb(var(--color-footer-text) / 0.5);">
                        &copy; {{ date('Y') }} FarSell. All rights reserved.
                    </p>
                    {{-- Compact theme toggle in footer --}}
                    <button class="theme-toggle text-white/40 hover:text-white"
                            @click="$theme.toggle()"
                            x-data
                            :aria-label="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'">
                        <svg x-show="$theme.isDark" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
                        </svg>
                        <svg x-show="!$theme.isDark" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2" style="display:none;">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75 9.75 9.75 0 0 1 8.25 6c0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 12c0 5.385 4.365 9.75 9.75 9.75 4.797 0 8.818-3.462 9.75-8.002-.083.084-.166.165-.25.252v-.002Z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
