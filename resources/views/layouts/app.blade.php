@php
    $cartCount     = $cartCount     ?? 0;
    $navCategories = $navCategories ?? collect();
    $storefrontUser = auth()->user();
    $canUseCart = $storefrontUser === null || $storefrontUser->role === \App\Enums\UserRole::Buyer;
    $canApplyAsRider = $storefrontUser === null || $storefrontUser->role === \App\Enums\UserRole::Buyer;
    $riderApplicationUrl = $storefrontUser === null
        ? route('register', ['intent' => 'rider'])
        : ($storefrontUser->riderProfile ? route('rider.profile') : route('rider.register'));
    $riderApplicationLabel = $storefrontUser?->riderProfile ? 'Rider Application' : 'Become a Rider';
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FarSell')</title>
    <x-primary-font />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Apply stored theme before first paint to prevent flash --}}
    <script src="{{ asset('js/theme.js') }}"></script>
</head>

<body class="min-h-screen flex flex-col antialiased font-sans transition-colors duration-200 {{ $__env->hasSection('buyer-action-bar') ? 'pb-[calc(9rem+env(safe-area-inset-bottom))] md:pb-0' : ($canUseCart ? 'pb-20 md:pb-0' : '') }}"
      style="background-color: rgb(var(--color-surface)); color: rgb(var(--color-text-base));"
      x-data="{
          drawerOpen: false,
          openDrawer() {
              this.drawerOpen = true;
              document.body.style.overflow = 'hidden';
              this.$nextTick(() => { if (this.$refs.drawerClose) this.$refs.drawerClose.focus(); });
          },
          closeDrawer() {
              this.drawerOpen = false;
              document.body.style.overflow = '';
              this.$nextTick(() => { if (this.$refs.profilePill) this.$refs.profilePill.focus(); });
          }
      }"
      @keydown.escape.window="drawerOpen && closeDrawer()">
    <x-ui.button variant="primary" size="lg" href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60]">Skip to content</x-ui.button>

    {{-- ══════════════════════════════════════════════════════════════
         TOP NAVIGATION  —  Extremerate style
         Desktop: logo | category dropdowns | search | icons | toggle
         Mobile:  logo | search | hamburger
         ══════════════════════════════════════════════════════════════ --}}
    <header class="sticky top-0 z-40 border-b transition-colors duration-200"
            style="background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border) / 0.5);"
            x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false">

        {{-- ── Main nav bar ─────────────────────────────────────────── --}}
        <div class="mx-auto max-w-7xl px-4">
            <div class="flex flex-wrap md:flex-nowrap items-center gap-2 md:gap-3 py-2">

                {{-- Logo --}}
                <a href="{{ route('home') }}"
                   class="shrink-0 flex items-center gap-2 text-xl font-bold"
                   style="color: rgb(var(--color-accent));">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-black"
                          style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">F</span>
                    <span class="hidden sm:inline">FarSell</span>
                </a>

                {{-- ── Desktop: category dropdown nav ────────────────── --}}
                <nav class="hidden xl:flex items-center gap-2" aria-label="Category navigation">

                    {{-- All Products --}}
                    <a href="{{ route('catalog.index') }}"
                       class="nav-link px-3 py-1"
                       @if(request()->routeIs('catalog.*')) aria-current="page" @endif>
                        Browse
                    </a>

                    {{-- Categories mega-dropdown --}}
                    <div class="relative" x-data="{ open: false }">
                        <x-ui.button variant="tertiary" type="button" @keydown.escape.window="open = false" @click.outside="open = false" @click="open = !open"
                                class="nav-link px-3 py-1 flex items-center gap-1 justify-start"
                                ::aria-expanded="open" aria-haspopup="true">
                            Categories
                            <x-ui.icon name="expand" size="sm" class="transition-transform" x-bind:class="open && 'rotate-180'" />
                        </x-ui.button>

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
                                    <x-ui.icon name="browse" size="sm" />
                                    {{ $cat->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Shops --}}
                    <a href="{{ route('shops.index') }}"
                       class="nav-link px-3 py-1" @if(request()->routeIs('shops.*')) aria-current="page" @endif>Shops</a>

                    @if($canApplyAsRider)
                        <a href="{{ $riderApplicationUrl }}" class="nav-link px-3 py-1"
                           @if(request()->routeIs('rider.*')) aria-current="page" @endif>
                            {{ $riderApplicationLabel }}
                        </a>
                    @endif
                </nav>

                {{-- ── Expandable search (flex-1 on desktop) ─────────── --}}
                <div class="order-last w-full min-w-0 md:order-none md:w-auto md:flex-1 md:max-w-xl md:mx-2 relative"
                     x-data="marketplaceSearch(@js(request('q', '')))" @click.outside="focused = false" @keydown.escape="focused = false">
                    <form action="{{ route('catalog.index') }}" method="get"
                          x-ref="searchForm" @submit="remember()" class="relative" role="search">
                        <x-ui.icon name="search" size="sm" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted" x-bind:class="{ 'text-accent': focused }" />
                        <input type="search" name="q" value="{{ request('q') }}" x-model="query" x-ref="searchInput" maxlength="120" autocomplete="off"
                               placeholder="Search items, shops, brands, surplus lots..."
                               aria-label="Search products"
                               @focus="focused = true" @focusout="if (!$el.parentElement.parentElement.contains($event.relatedTarget)) focused = false"
                               class="fs-input min-h-11 pl-10 pr-11 rounded-full">
                        <x-ui.icon-button variant="tertiary" label="Clear search" icon="close" type="button" x-show="query.length > 0" @click="query = ''; $refs.searchInput.focus()" class="absolute right-0 top-0 theme-toggle" style="display:none;"></x-ui.icon-button>
                    </form>
                    <div x-show="focused && !query.trim() && recent.length" class="search-recents" style="display:none;" @focusout="if (!$el.parentElement.contains($event.relatedTarget)) focused = false">
                        <div class="flex items-center justify-between px-2"><p class="text-xs font-semibold text-text-muted">Recent searches</p><x-ui.button variant="tertiary" size="sm" type="button" @click="clearRecent()" class="min-h-11 px-2 text-xs text-accent">Clear history</x-ui.button></div>
                        <template x-for="term in recent" :key="term"><x-ui.button variant="tertiary" type="button" @click="choose(term)" class="dropdown-item w-full rounded-xl text-left justify-start" x-text="term"></x-ui.button></template>
                    </div>
                </div>

                {{-- ── Right icons cluster ─────────────────────────────── --}}
                <div class="ml-auto flex shrink-0 items-center gap-2">
                    @guest
                        {{-- Guest pill --}}
                        <div class="hidden sm:flex items-center gap-1 rounded-full border min-h-11 px-3 py-1.5 text-sm transition-colors"
                             style="border-color: rgb(var(--color-surface-border) / 0.7); background-color: rgb(var(--color-surface));">
                            <x-ui.button variant="tertiary" :href="route('login')" class="text-accent">Sign in</x-ui.button>
                            <span class="mx-1" style="color: rgb(var(--color-surface-border));">|</span>
                            <x-ui.button variant="tertiary" :href="route('register')" class="text-accent">Register</x-ui.button>
                        </div>
                    @endguest
                    @auth
                        {{-- Auth pill — opens drawer --}}
                        <x-ui.button variant="tertiary" type="button" x-ref="profilePill"
                                @click="openDrawer()"
                                ::aria-expanded="drawerOpen"
                                aria-haspopup="dialog"
                                class="hidden sm:flex items-center gap-2 rounded-full border min-h-11 px-3 py-1.5 text-sm font-medium transition-colors hover:border-accent max-w-[160px]"
                                style="border-color: rgb(var(--color-surface-border) / 0.7); background-color: rgb(var(--color-surface));">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                                  style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">
                                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="truncate max-w-[120px]">{{ auth()->user()->name }}</span>
                        </x-ui.button>
                    @endauth

                    @if($canUseCart)
                    <x-buyer-notification-bell />
                    {{-- Cart --}}
                    <x-ui.icon-button :href="route('cart.index')" class="relative"
                       :label="$cartCount > 0 ? 'Cart ('.$cartCount.' items)' : 'Cart'">
                        <x-ui.icon name="cart" />
                        @if ($cartCount > 0)
                            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center
                                         rounded-full text-[9px] font-bold leading-none"
                                  style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">
                                {{ $cartCount > 9 ? '9+' : $cartCount }}
                            </span>
                        @endif
                    </x-ui.icon-button>
                    @endif

                    {{-- Compact account trigger below sm --}}
                    @auth
                        {{-- xs-only icon trigger (< sm, where pill is hidden) --}}
                        <x-ui.icon-button variant="tertiary" label="Open account panel" type="button" @click="openDrawer()"
                                class="theme-toggle sm:hidden"
                               >
                            <x-ui.icon name="account" />
                        </x-ui.icon-button>
                    @endauth

                    {{-- Theme toggle --}}
                    <x-ui.button variant="tertiary" type="button" class="theme-toggle"
                            @click="$theme.toggle()"
                            ::aria-label="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                            x-data>
                        {{-- Sun (shown in dark mode) --}}
                        <x-ui.icon name="light" x-show="$theme.isDark" x-cloak />
                        {{-- Moon (shown in light mode) --}}
                        <x-ui.icon name="dark" x-show="!$theme.isDark" />
                    </x-ui.button>

                    {{-- Mobile hamburger --}}
                    <x-ui.icon-button variant="tertiary" label="Toggle menu" type="button" class="theme-toggle xl:hidden"
                            @click="mobileOpen = !mobileOpen"
                            ::aria-expanded="mobileOpen"
                           >
                        <x-ui.icon name="menu" x-show="!mobileOpen" />
                        <x-ui.icon name="close" x-show="mobileOpen" x-cloak />
                    </x-ui.icon-button>
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
             class="xl:hidden border-t pb-4"
             style="display:none; background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border) / 0.4);">
            <nav class="mx-auto max-w-7xl space-y-2 px-4 pt-3" aria-label="Mobile navigation">
                <a href="{{ route('home') }}" class="dropdown-item rounded-xl"
                   @if(request()->routeIs('home', 'welcome')) aria-current="page" @endif>Home</a>
                <a href="{{ route('catalog.index') }}" class="dropdown-item rounded-xl"
                   @if(request()->routeIs('catalog.*')) aria-current="page" @endif>Browse All</a>
                <a href="{{ route('shops.index') }}" class="dropdown-item rounded-xl"
                   @if(request()->routeIs('shops.*')) aria-current="page" @endif>Shops</a>

                {{-- Category links (collapsed by default) --}}
                <div x-data="{ catOpen: false }">
                    <x-ui.button variant="tertiary" type="button" @click="catOpen = !catOpen"
                            class="dropdown-item rounded-xl w-full text-left justify-between justify-start">
                        <span>Categories</span>
                        <x-ui.icon name="expand" size="sm" class="transition-transform" x-bind:class="catOpen && 'rotate-180'" />
                    </x-ui.button>
                    <div x-show="catOpen" class="mt-2 space-y-2 pl-4" style="display:none;">
                        @foreach ($navCategories as $cat)
                            <a href="{{ route('catalog.index', ['category' => $cat->id]) }}"
                               class="dropdown-item rounded-xl text-sm">
                                <x-ui.icon name="browse" size="sm" /> {{ $cat->name }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @if($canUseCart)
                <a href="{{ route('cart.index') }}" class="dropdown-item rounded-xl"
                   @if(request()->routeIs('cart.*')) aria-current="page" @endif>
                    Cart @if($cartCount > 0)
                        <span class="ml-auto badge badge-accent">{{ $cartCount }}</span>
                    @endif
                </a>
                @endif

                @auth
                                @if(in_array(auth()->user()->role, [\App\Enums\UserRole::Buyer, \App\Enums\UserRole::Seller], true))
                                    <a href="{{ route('chat.index') }}" class="dropdown-item rounded-xl" @if(request()->routeIs('chat.*')) aria-current="page" @endif>Chat @if($chatUnreadCount)<span class="ml-auto rounded-full bg-accent px-2 py-0.5 text-[10px] text-white">{{ $chatUnreadCount > 99 ? '99+' : $chatUnreadCount }}</span>@endif</a>
                                @endif
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
                        <x-ui.button variant="tertiary" type="submit" class="dropdown-item rounded-xl w-full text-left justify-start"
                                style="color: rgb(var(--color-error));">Sign out</x-ui.button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="dropdown-item rounded-xl">Sign in</a>
                    <a href="{{ route('register') }}" class="dropdown-item rounded-xl">Create account</a>
                @endauth
            </nav>
        </div>

    </header>

    {{-- ── Flash status ────────────────────────────────────────────── --}}
    @if (session('status'))
        <div data-flash-message role="status">
            <p class="alert-success">{{ session('status') }}</p>
        </div>
    @endif

    {{-- ── Page content ────────────────────────────────────────────── --}}
    <div :inert="drawerOpen">
    @if(auth()->check() && auth()->user()->role === \App\Enums\UserRole::Rider)
        <nav aria-label="Rider workspace" class="mx-auto max-w-7xl px-4 pt-4 flex flex-wrap gap-3">
            <x-ui.button :href="route('rider.dashboard')" :aria-current="request()->routeIs('rider.dashboard') ? 'page' : null">Rider dashboard</x-ui.button>
            @if(auth()->user()->riderProfile?->isApproved())
                <x-ui.button :href="route('fulfillments.index')" variant="primary" size="lg" :aria-current="request()->routeIs('fulfillments.*') ? 'page' : null">Pickups &amp; deliveries</x-ui.button>
            @endif
        </nav>
    @endif
    <main id="main-content" class="w-full flex-1 mx-auto max-w-7xl px-4 py-6 sm:py-8">
        @yield('content')
    </main>

    @stack('scripts')
    </div>{{-- /inert wrapper --}}

    {{-- ══════════════════════════════════════════════════════════════
         FOOTER — marketplace links and payments
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
                        Discover verified marketplace finds, local surplus, and auction lots delivered straight to your door.
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
                            @elseif(auth()->user()->role === \App\Enums\UserRole::Admin)
                                <li><a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">Admin Dashboard</a></li>
                            @elseif(auth()->user()->role === \App\Enums\UserRole::Buyer)
                                <li><a href="{{ route('account.addresses.index') }}" class="hover:text-white transition-colors">My Addresses</a></li>
                            @endif
                            @if($canUseCart)
                                <li><a href="{{ route('cart.index') }}" class="hover:text-white transition-colors">Cart</a></li>
                            @endif
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
                        @if($canApplyAsRider)
                            <li><a href="{{ $riderApplicationUrl }}" class="hover:text-white transition-colors">{{ $riderApplicationLabel }}</a></li>
                        @endif
                            <li><a href="{{ auth()->check() ? (auth()->user()->role === \App\Enums\UserRole::Seller ? route('seller.dashboard') : route('account.profile')) : route('register', ['intent' => 'seller']) }}" class="hover:text-white transition-colors">Sell on FarSell</a></li>
                        <li><span class="text-xs">Buyer Protection (coming soon)</span></li>
                        <li><span class="text-xs">Help Center (coming soon)</span></li>
                    </ul>
                </div>

                {{-- About --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-widest text-white mb-3">About</h3>
                    <ul class="space-y-2 text-sm">
                        <li><span class="text-xs">About FarSell (coming soon)</span></li>
                        <li><a href="{{ route('privacy') }}" class="text-xs hover:text-white">Privacy notice</a></li>
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
                    <span class="text-xs mr-1 text-footer-text">Payment:</span>
                    @foreach (['COD', 'Card / e-wallet (demo only)'] as $method)
                        <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold border"
                              style="border-color: rgb(255 255 255 / 0.15); color: rgb(var(--color-footer-text) / 0.7);">
                            {{ $method }}
                        </span>
                    @endforeach
                </div>

                {{-- Copyright + theme toggle --}}
                <div class="flex items-center gap-3">
                    <p class="text-xs text-footer-text">
                        &copy; {{ date('Y') }} FarSell. All rights reserved.
                    </p>
                    {{-- Compact theme toggle in footer --}}
                    <x-ui.icon-button variant="tertiary" label="Switch color theme" ::title="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'" type="button" class="theme-toggle text-white/40 hover:text-white"
                            @click="$theme.toggle()"
                            x-data
                            ::aria-label="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'">
                        <x-ui.icon name="light" size="sm" x-show="$theme.isDark" x-cloak />
                        <x-ui.icon name="dark" size="sm" x-show="!$theme.isDark" />
                    </x-ui.icon-button>
                </div>
            </div>
        </div>
    </footer>

    {{-- ══════════════════════════════════════════════════════════════
         RIGHT SLIDE-OVER DRAWER  (Requirement 2)
         Backdrop + panel driven by drawerOpen state on <body x-data>
         ══════════════════════════════════════════════════════════════ --}}

    {{-- Backdrop --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="closeDrawer()"
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm"
         aria-hidden="true"
         style="display:none;"></div>

    {{-- Drawer panel --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-[250ms]"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         role="dialog"
         aria-modal="true"
         aria-label="Account panel"
         :inert="!drawerOpen"
         class="fixed inset-y-0 right-0 z-50 flex flex-col overflow-y-auto
                w-full sm:w-80 shadow-card-md transform"
         style="background-color: rgb(var(--color-surface)); display:none;">

        {{-- Drawer header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b"
             style="border-color: rgb(var(--color-surface-border) / 0.5);">
            <p class="text-sm font-semibold" style="color: rgb(var(--color-text-base));">Account</p>
            <x-ui.icon-button variant="tertiary" label="Close account panel" type="button" x-ref="drawerClose"
                    @click="closeDrawer()"
                    class="theme-toggle"
                   >
                <x-ui.icon name="close" />
            </x-ui.icon-button>
        </div>

        @auth
            {{-- ── Avatar block ──────────────────────────────────────── --}}
            <div class="px-5 py-5">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full
                                text-xl font-black"
                         style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold truncate" style="color: rgb(var(--color-text-base));">
                            {{ auth()->user()->name }}
                        </p>
                        <p class="text-sm truncate" style="color: rgb(var(--color-text-muted));">
                            {{ Str::before(auth()->user()->email, '@') }}
                        </p>
                        <p class="text-xs mt-0.5" style="color: rgb(var(--color-text-muted));">
                            Member since {{ auth()->user()->created_at->year }}
                        </p>
                    </div>
                </div>
                <div class="mt-3 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                     style="background-color: rgb(var(--color-accent-subtle)); color: rgb(var(--color-accent));">
                    <x-ui.icon name="rating" size="sm" variant="solid" />
                    0 pts &mdash; Loyalty Points
                </div>
            </div>

            <div class="mx-5 border-t" style="border-color: rgb(var(--color-surface-border) / 0.4);"></div>

            {{-- ── Quick links ───────────────────────────────────────── --}}
            <div class="px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2"
                   style="color: rgb(var(--color-text-muted));">Quick Links</p>
                <nav class="space-y-2 [&>a]:min-h-target">
                    @if(auth()->user()->role === \App\Enums\UserRole::Buyer)
                    {{-- Wishlist / Saved Items --}}
                    <a href="{{ route('account.saved.index') }}"
                       @click="closeDrawer()"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors"
                       style="color: rgb(var(--color-text-base));"
                       onmouseover="this.style.backgroundColor='rgb(var(--color-surface-muted))'"
                       onmouseout="this.style.backgroundColor=''">
                        <x-ui.icon name="save" size="sm" />
                        <span class="text-sm">Wishlist</span>
                    </a>
                    @else
                    {{-- Wishlist (placeholder for non-buyers) --}}
                    <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 cursor-not-allowed opacity-50"
                         title="Available for buyers">
                        <x-ui.icon name="save" size="sm" />
                        <span class="text-sm">Wishlist</span>
                    </div>
                    @endif

                    @if(auth()->user()->role === \App\Enums\UserRole::Buyer)
                    {{-- Order History --}}
                    <a href="{{ route('orders.index') }}"
                       @click="closeDrawer()"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors"
                       style="color: rgb(var(--color-text-base));"
                       onmouseover="this.style.backgroundColor='rgb(var(--color-surface-muted))'"
                       onmouseout="this.style.backgroundColor=''">
                        <x-ui.icon name="order" size="sm" />
                        <span class="text-sm">Order History</span>
                    </a>

                    {{-- Saved Addresses --}}
                    <a href="{{ route('account.addresses.index') }}"
                       @click="closeDrawer()"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors"
                       style="color: rgb(var(--color-text-base));"
                       onmouseover="this.style.backgroundColor='rgb(var(--color-surface-muted))'"
                       onmouseout="this.style.backgroundColor=''">
                        <x-ui.icon name="location" size="sm" />
                        <span class="text-sm">Saved Addresses</span>
                    </a>
                    @endif

                    {{-- My Profile --}}
                    <a href="{{ route('account.profile') }}"
                       @click="closeDrawer()"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors"
                       style="color: rgb(var(--color-text-base));"
                       onmouseover="this.style.backgroundColor='rgb(var(--color-surface-muted))'"
                       onmouseout="this.style.backgroundColor=''">
                        <x-ui.icon name="account" size="sm" />
                        <span class="text-sm">My Profile</span>
                    </a>
                </nav>
            </div>

            <div class="mx-5 border-t" style="border-color: rgb(var(--color-surface-border) / 0.4);"></div>

            {{-- ── Sign Out ───────────────────────────────────────────── --}}
            <div class="px-5 py-4 mt-auto">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.button variant="tertiary" type="submit"
                            class="w-full rounded-xl py-2.5 text-sm font-semibold text-white transition-colors"
                            style="background-color: rgb(var(--color-accent));"
                            onmouseover="this.style.backgroundColor='rgb(var(--color-accent-hover))'"
                            onmouseout="this.style.backgroundColor='rgb(var(--color-accent))'">
                        Sign Out
                    </x-ui.button>
                </form>
            </div>

        @else
            {{-- ── Guest state ────────────────────────────────────────── --}}
            <div class="flex flex-1 flex-col items-center justify-center px-5 py-10 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full mb-4"
                     style="background-color: rgb(var(--color-surface-muted));">
                    <x-ui.icon name="account" size="lg" class="text-text-muted" />
                </div>
                <p class="font-semibold mb-1" style="color: rgb(var(--color-text-base));">You're not signed in</p>
                <p class="text-sm mb-6" style="color: rgb(var(--color-text-muted));">Sign in to view your account and orders.</p>
                <x-ui.button variant="primary" size="lg" :href="route('login')" @click="closeDrawer()"
                   class="w-full mb-3">Sign In</x-ui.button>
                <x-ui.button variant="tertiary" :href="route('register')" @click="closeDrawer()" class="text-accent">Create account</x-ui.button>
            </div>
        @endauth

    </div>{{-- /drawer panel --}}

    @if ($canUseCart)
        @include('layouts.partials.mobile-navigation')
    @endif
    @include('chat.partials.dock', ['hasBuyerActionBar' => $__env->hasSection('buyer-action-bar')])

</body>
</html>
