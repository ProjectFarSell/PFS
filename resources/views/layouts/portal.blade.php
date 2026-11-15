<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal | FarSell')</title>
    <x-primary-font />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="min-h-screen font-sans antialiased transition-colors duration-200"
      style="background-color: rgb(var(--color-surface-muted)); color: rgb(var(--color-text-base));">
    @php($portalRole = auth()->user()->role)
    @php($hasApprovedShop = $hasApprovedShop ?? ($portalRole === \App\Enums\UserRole::Seller && auth()->user()->shop !== null))

    <header class="sticky top-0 z-40 border-b"
            style="background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border));">
        <div class="mx-auto flex min-h-16 w-full max-w-screen-2xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ match ($portalRole) {
                    \App\Enums\UserRole::Admin => route('admin.dashboard'),
                    \App\Enums\UserRole::Seller => route('seller.dashboard'),
                    \App\Enums\UserRole::Rider => route('rider.dashboard'),
                    default => route('account.profile'),
                } }}" class="flex shrink-0 items-center gap-2 font-bold" style="color: rgb(var(--color-accent));">
                <x-brand-mark />
                <span>FarSell <span class="hidden sm:inline">{{ ucfirst($portalRole->value) }}</span></span>
            </a>

            <nav class="portal-navigation order-last flex w-full flex-wrap gap-1 text-sm xl:order-none xl:ml-auto xl:w-auto" aria-label="Portal navigation">
                @if($portalRole === \App\Enums\UserRole::Admin)
                    <a href="{{ route('admin.dashboard') }}" class="portal-nav-link" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
                    <a href="{{ route('admin.logistics.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.logistics.*')) aria-current="page" @endif>Logistics</a>
                    <a href="{{ route('admin.payouts.index') }}" class="portal-nav-link">Payouts</a>
                    <a href="{{ route('admin.vouchers.index') }}" class="portal-nav-link">Vouchers</a>
                    <a href="{{ route('admin.remittances.index') }}" class="portal-nav-link">COD remittances</a>
                    <a href="{{ route('admin.marketplace-settings.edit') }}" class="portal-nav-link">Fees</a>
                    <a href="{{ route('admin.products.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.products.*')) aria-current="page" @endif>Products</a>
                    <a href="{{ route('admin.categories.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.categories.*')) aria-current="page" @endif>Categories</a>
                    <a href="{{ route('admin.partners.index') }}" class="portal-nav-link" @if(request()->routeIs('admin.partners.*', 'admin.sellers.*') || (request()->routeIs('admin.riders.*') && !request()->routeIs('admin.riders.barangays.*'))) aria-current="page" @endif>Partners</a>
                    <details class="relative shrink-0" @keydown.escape="$el.open = false">
                        <summary class="portal-nav-link cursor-pointer list-none" aria-label="More admin pages">More <span aria-hidden="true" class="ml-1">⌄</span></summary>
                        <div class="absolute right-0 top-full z-50 mt-2 grid min-w-56 rounded-xl border border-surface-border bg-surface p-2 shadow-lg">
                            <a href="{{ route('admin.riders.barangays.index') }}" class="portal-nav-link">Barangay assignments</a>
                            <a href="{{ route('admin.rider-records.index') }}" class="portal-nav-link">Rider records</a>
                            <a href="{{ route('admin.reviews.index') }}" class="portal-nav-link">Reviews</a>
                            <a href="{{ route('admin.notifications.index') }}" class="portal-nav-link">Notifications</a>
                            <a href="{{ route('fulfillments.index') }}" class="portal-nav-link">Dispatch</a>
                        </div>
                    </details>
                @elseif($portalRole === \App\Enums\UserRole::Seller)
                    <a href="{{ route('seller.dashboard') }}" class="portal-nav-link">Dashboard</a>
                    @if($hasApprovedShop)
                    <a href="{{ route('seller.products.create') }}" class="portal-nav-link">Add Product</a>
                    <a href="{{ route('seller.payouts.index') }}" class="portal-nav-link">Payout history</a>
                    <a href="{{ route('seller.vouchers.index') }}" class="portal-nav-link">Vouchers</a>
                    <a href="{{ route('fulfillments.index') }}" class="portal-nav-link" @if(request()->routeIs('fulfillments.*')) aria-current="page" @endif>Orders</a>
                    <a href="{{ route('chat.index') }}" class="portal-nav-link">Chat @if($chatUnreadCount)<span class="ml-1 rounded-full bg-accent px-1.5 py-0.5 text-[10px] text-white">{{ $chatUnreadCount > 99 ? '99+' : $chatUnreadCount }}</span>@endif</a>
                    <a href="{{ route('home') }}" class="portal-nav-link">View Storefront</a>
                    @endif
                @elseif($portalRole === \App\Enums\UserRole::Rider)
                    <a href="{{ route('rider.dashboard') }}" class="portal-nav-link">Dashboard</a>
                    <a href="{{ route('rider.dashboard') }}#delivery-requests" class="portal-nav-link">Delivery Requests</a>
                    <a href="{{ route('rider.earnings.index') }}" class="portal-nav-link" @if(request()->routeIs('rider.earnings.*')) aria-current="page" @endif>Earnings</a>
                    <a href="{{ route('rider.cod.index') }}" class="portal-nav-link">COD owed</a>
                    <a href="{{ route('rider.history.index') }}" class="portal-nav-link" @if(request()->routeIs('rider.history.*')) aria-current="page" @endif>History</a>
                    <a href="{{ route('fulfillments.index') }}" class="portal-nav-link" @if(request()->routeIs('fulfillments.*')) aria-current="page" @endif>Deliveries</a>
                    <a href="{{ route('rider.profile') }}" class="portal-nav-link">Rider Profile</a>
                @endif

                @if($portalRole === \App\Enums\UserRole::Buyer)
                    <a href="{{ route('chat.index') }}" class="portal-nav-link">Chat @if($chatUnreadCount)<span class="ml-1 rounded-full bg-accent px-1.5 py-0.5 text-[10px] text-white">{{ $chatUnreadCount > 99 ? '99+' : $chatUnreadCount }}</span>@endif</a>
                @endif

            </nav>
            <div class="ml-auto flex shrink-0 items-center gap-2">
                <a href="{{ route('account.profile') }}" class="portal-nav-link" aria-label="My account" title="My account"><x-ui.icon name="account" class="sm:hidden" /><span class="hidden sm:inline">My account</span></a>
                <x-theme-toggle />
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.button variant="tertiary" type="submit" class="min-h-11">Sign out</x-ui.button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:py-8">
        @if(session('status'))
            <div data-flash-message role="status" class="mb-5 rounded-xl border px-4 py-3 text-sm"
                 style="background-color: rgb(var(--color-surface)); border-color: rgb(var(--color-surface-border));">
                {{ session('status') }}
            </div>
        @endif
        @yield('content')
    </main>

    @stack('scripts')
    @if ($portalRole === \App\Enums\UserRole::Buyer || ($portalRole === \App\Enums\UserRole::Seller && $hasApprovedShop))
        @include('chat.partials.dock')
    @endif
</body>
</html>
