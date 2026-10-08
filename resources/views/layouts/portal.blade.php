<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FarSell Portal')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="min-h-screen font-sans antialiased transition-colors duration-200"
      style="background-color: rgb(var(--color-surface-muted)); color: rgb(var(--color-text-base));">
    @php($portalRole = auth()->user()->role)

    <header class="sticky top-0 z-40 border-b"
            style="background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border));">
        <div class="mx-auto flex min-h-16 max-w-7xl items-center gap-4 px-4 py-3">
            <a href="{{ match ($portalRole) {
                    \App\Enums\UserRole::Admin => route('admin.dashboard'),
                    \App\Enums\UserRole::Seller => route('seller.dashboard'),
                    \App\Enums\UserRole::Rider => route('rider.dashboard'),
                    default => route('account.profile'),
                } }}" class="flex shrink-0 items-center gap-2 font-bold" style="color: rgb(var(--color-accent));">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-black"
                      style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">F</span>
                <span>FarSell {{ ucfirst($portalRole->value) }}</span>
            </a>

            <nav class="ml-auto flex flex-wrap items-center justify-end gap-1 text-sm" aria-label="Portal navigation">
                @if($portalRole === \App\Enums\UserRole::Admin)
                    <a href="{{ route('admin.dashboard') }}" class="nav-link px-3 py-2">Dashboard</a>
                    <a href="{{ route('admin.sellers.index') }}" class="nav-link px-3 py-2">Seller Approvals</a>
                    <a href="{{ route('admin.riders.index') }}" class="nav-link px-3 py-2">Rider Approvals</a>
                    <a href="{{ route('fulfillments.index') }}" class="nav-link px-3 py-2">Dispatch</a>
                @elseif($portalRole === \App\Enums\UserRole::Seller)
                    <a href="{{ route('seller.dashboard') }}" class="nav-link px-3 py-2">Dashboard</a>
                    <a href="{{ route('seller.products.create') }}" class="nav-link px-3 py-2">Add Product</a>
                    <a href="{{ route('fulfillments.index') }}" class="nav-link px-3 py-2">Orders</a>
                    <a href="{{ route('chat.index') }}" class="nav-link px-3 py-2">Chat @if($chatUnreadCount)<span class="ml-1 rounded-full bg-accent px-1.5 py-0.5 text-[10px] text-white">{{ $chatUnreadCount > 99 ? '99+' : $chatUnreadCount }}</span>@endif</a>
                    <a href="{{ route('home') }}" class="nav-link px-3 py-2">View Storefront</a>
                @elseif($portalRole === \App\Enums\UserRole::Rider)
                    <a href="{{ route('rider.dashboard') }}" class="nav-link px-3 py-2">Dashboard</a>
                    <a href="{{ route('rider.delivery-requests') }}" class="nav-link px-3 py-2">Delivery Requests</a>
                    <a href="{{ route('fulfillments.index') }}" class="nav-link px-3 py-2">Deliveries</a>
                    <a href="{{ route('rider.profile') }}" class="nav-link px-3 py-2">Rider Profile</a>
                @endif

                @if($portalRole === \App\Enums\UserRole::Buyer)
                    <a href="{{ route('chat.index') }}" class="nav-link px-3 py-2">Chat @if($chatUnreadCount)<span class="ml-1 rounded-full bg-accent px-1.5 py-0.5 text-[10px] text-white">{{ $chatUnreadCount > 99 ? '99+' : $chatUnreadCount }}</span>@endif</a>
                @endif

                <a href="{{ route('account.profile') }}" class="nav-link px-3 py-2">My Account</a>
                <button class="theme-toggle" @click="$theme.toggle()" x-data aria-label="Toggle color theme">
                    <span x-show="!$theme.isDark">Dark</span>
                    <span x-show="$theme.isDark" style="display:none">Light</span>
                </button>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full px-3 py-2 text-sm font-semibold text-white"
                            style="background-color: rgb(var(--color-accent));">Sign out</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6">
        @if(session('status'))
            <div class="mb-5 rounded-xl border px-4 py-3 text-sm"
                 style="background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border));">
                {{ session('status') }}
            </div>
        @endif
        @yield('content')
    </main>

    @stack('scripts')
    @include('chat.partials.dock')
</body>
</html>
