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
        <div class="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
            <a href="{{ match ($portalRole) {
                    \App\Enums\UserRole::Admin => route('admin.dashboard'),
                    \App\Enums\UserRole::Seller => route('seller.dashboard'),
                    \App\Enums\UserRole::Rider => route('rider.dashboard'),
                    default => route('account.profile'),
                } }}" class="flex shrink-0 items-center gap-2 font-bold" style="color: rgb(var(--color-accent));">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-black"
                      style="background-color: rgb(var(--color-accent)); color: rgb(var(--color-accent-text));">F</span>
                <span>FarSell <span class="hidden sm:inline">{{ ucfirst($portalRole->value) }}</span></span>
            </a>

            <nav class="portal-navigation order-last flex w-full gap-1 overflow-x-auto text-sm xl:order-none xl:ml-auto xl:w-auto" aria-label="Portal navigation">
                @if($portalRole === \App\Enums\UserRole::Admin)
                    <a href="{{ route('admin.dashboard') }}" class="portal-nav-link" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
                    <a href="{{ route('admin.products.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.products.*')) aria-current="page" @endif>Products</a>
                    <a href="{{ route('admin.categories.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.categories.*')) aria-current="page" @endif>Categories</a>
                    <a href="{{ route('admin.partners.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.partners.*', 'admin.sellers.*') || (request()->routeIs('admin.riders.*') && !request()->routeIs('admin.riders.barangays.*'))) aria-current="page" @endif>Partners</a>
                    <a href="{{ route('admin.riders.barangays.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.riders.barangays.index')) aria-current="page" @endif>Barangay Assignments</a>
                    <a href="{{ route('admin.rider-records.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.rider-records.*')) aria-current="page" @endif>Rider Records</a>
                    <a href="{{ route('admin.reviews.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.reviews.*')) aria-current="page" @endif>Reviews</a>
                    <a href="{{ route('admin.notifications.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.notifications.*')) aria-current="page" @endif>Notifications</a>
                    <a href="{{ route('fulfillments.index') }}" class="portal-nav-link" @if(request()->routeIs('fulfillments.*')) aria-current="page" @endif>Dispatch</a>
                @elseif($portalRole === \App\Enums\UserRole::Seller)
                    <a href="{{ route('seller.dashboard') }}" class="portal-nav-link">Dashboard</a>
                    <a href="{{ route('seller.products.create') }}" class="portal-nav-link">Add Product</a>
                    <a href="{{ route('fulfillments.index') }}" class="portal-nav-link" @if(request()->routeIs('fulfillments.*')) aria-current="page" @endif>Orders</a>
                    <a href="{{ route('chat.index') }}" class="portal-nav-link">Chat @if($chatUnreadCount)<span class="ml-1 rounded-full bg-accent px-1.5 py-0.5 text-[10px] text-white">{{ $chatUnreadCount > 99 ? '99+' : $chatUnreadCount }}</span>@endif</a>
                    <a href="{{ route('home') }}" class="portal-nav-link">View Storefront</a>
                @elseif($portalRole === \App\Enums\UserRole::Rider)
                    <a href="{{ route('rider.dashboard') }}" class="portal-nav-link">Dashboard</a>
                    <a href="{{ route('rider.dashboard') }}#delivery-requests" class="portal-nav-link">Delivery Requests</a>
                    <a href="{{ route('rider.earnings.index') }}" class="portal-nav-link" @if(request()->routeIs('rider.earnings.*')) aria-current="page" @endif>Earnings</a>
                    <a href="{{ route('rider.history.index') }}" class="portal-nav-link" @if(request()->routeIs('rider.history.*')) aria-current="page" @endif>History</a>
                    <a href="{{ route('fulfillments.index') }}" class="portal-nav-link" @if(request()->routeIs('fulfillments.*')) aria-current="page" @endif>Deliveries</a>
                    <a href="{{ route('rider.profile') }}" class="portal-nav-link">Rider Profile</a>
                @endif

                @if($portalRole === \App\Enums\UserRole::Buyer)
                    <a href="{{ route('chat.index') }}" class="portal-nav-link">Chat @if($chatUnreadCount)<span class="ml-1 rounded-full bg-accent px-1.5 py-0.5 text-[10px] text-white">{{ $chatUnreadCount > 99 ? '99+' : $chatUnreadCount }}</span>@endif</a>
                @endif

            </nav>
            <div class="ml-auto flex shrink-0 items-center gap-2 xl:ml-1">
                <a href="{{ route('account.profile') }}" class="portal-nav-link" aria-label="My Account"><svg class="h-5 w-5 sm:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg><span class="hidden sm:inline">My Account</span></a>
                <x-theme-toggle />
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-outline min-h-11 px-3 py-2">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6">
        @if(session('status'))
            <div data-flash-message role="status" class="mb-5 rounded-xl border px-4 py-3 text-sm"
                 style="background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border));">
                {{ session('status') }}
            </div>
        @endif
        @yield('content')
    </main>

    @stack('scripts')
    @if (in_array($portalRole, [\App\Enums\UserRole::Buyer, \App\Enums\UserRole::Seller], true))
        @include('chat.partials.dock')
    @endif
</body>
</html>
