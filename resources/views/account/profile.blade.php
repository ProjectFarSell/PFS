@extends($isBuyerProfile ? 'layouts.app' : 'layouts.portal')

@section('title', ($isBuyerProfile ? 'My Account' : ucfirst($user->role->value).' Profile').' · FarSell')

@section('content')
@php
    // Tab → OrderStatus value mapping
    $tabMap = [
        'all'                 => null,
        'pending_payment'     => ['pending_payment'],
        'paid_packed'         => ['paid', 'packed', 'confirmed'],
        'assigned_in_transit' => ['assigned', 'in_transit'],
        'delivered'           => ['delivered', 'completed'],
        'cancelled'           => ['cancelled'],
    ];

    // Sidebar navigation definition
    $accountItems = [
        ['key' => 'profile', 'label' => 'My Profile', 'active' => true],
    ];

    if ($isBuyerProfile) {
        $accountItems = array_merge($accountItems, [
            ['key' => 'addresses', 'label' => 'My Addresses',          'active' => true],
            ['key' => null,        'label' => 'Bank & Cards',          'active' => false],
            ['key' => null,        'label' => 'Privacy Settings',      'active' => false],
            ['key' => null,        'label' => 'Notification Settings', 'active' => false],
        ]);
    }

    $purchaseItems = $isBuyerProfile ? [
        ['key' => 'all',                 'label' => 'All'],
        ['key' => 'pending_payment',     'label' => 'To Pay'],
        ['key' => 'paid_packed',         'label' => 'To Ship'],
        ['key' => 'assigned_in_transit', 'label' => 'To Receive'],
        ['key' => 'delivered',           'label' => 'Completed'],
        ['key' => 'cancelled',           'label' => 'Cancelled'],
        ['key' => null,                  'label' => 'Return/Refund'],
        ['key' => 'review',              'label' => 'To Review'],
    ] : [];
@endphp

<div x-data="{ activeTab: 'profile' }" class="flex flex-col md:flex-row gap-5 items-start">

    {{-- ══════════════════════════════════════════════════════
         MOBILE: horizontal pill-tab bar (visible below md)
         ══════════════════════════════════════════════════════ --}}
    <div class="flex gap-2 overflow-x-auto pb-2 md:hidden w-full"
         style="scrollbar-width:none; -ms-overflow-style:none;">
        @foreach (array_merge($accountItems, $purchaseItems) as $item)
            @if ($item['key'] !== null && ($item['active'] ?? true) !== false)
            <button @click="activeTab = '{{ $item['key'] }}'"
                    :class="activeTab === '{{ $item['key'] }}'
                        ? 'text-white'
                        : 'border text-text-muted'"
                    :style="activeTab === '{{ $item['key'] }}'
                        ? 'background-color:rgb(var(--color-accent)); border-color:transparent;'
                        : 'border-color:rgb(var(--color-surface-border));'"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors">
                {{ $item['label'] }}
            </button>
            @endif
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════════════
         DESKTOP: left sidebar (hidden on mobile)
         ══════════════════════════════════════════════════════ --}}
    <aside class="hidden md:flex flex-col w-56 shrink-0 gap-0.5">

        {{-- My Account group --}}
        <p class="text-xs font-semibold uppercase tracking-widest px-3 py-2 mb-1"
           style="color:rgb(var(--color-text-muted));">My Account</p>

        @foreach ($accountItems as $item)
            @if ($item['active'])
            <button @click="activeTab = '{{ $item['key'] }}'"
                    :class="activeTab === '{{ $item['key'] }}'
                        ? 'font-semibold'
                        : 'hover:text-accent'"
                    :style="activeTab === '{{ $item['key'] }}'
                        ? 'background-color:rgb(var(--color-accent-subtle)); color:rgb(var(--color-accent));'
                        : 'color:rgb(var(--color-text-muted));'"
                    class="text-left rounded-xl px-3 py-2.5 text-sm w-full transition-colors">
                {{ $item['label'] }}
            </button>
            @else
            <button disabled
                    class="text-left rounded-xl px-3 py-2.5 text-sm w-full opacity-40 cursor-not-allowed"
                    style="color:rgb(var(--color-text-muted));">
                {{ $item['label'] }}
                <span class="ml-1 text-[10px] rounded-full px-1.5 py-0.5"
                      style="background-color:rgb(var(--color-surface-muted)); color:rgb(var(--color-text-muted));">Soon</span>
            </button>
            @endif
        @endforeach

        @if ($isBuyerProfile)
        {{-- My Orders group --}}
        <p class="text-xs font-semibold uppercase tracking-widest px-3 py-2 mt-4 mb-1"
           style="color:rgb(var(--color-text-muted));">My Orders</p>

        @foreach ($purchaseItems as $item)
            @if ($item['key'] !== null)
            <button @click="activeTab = '{{ $item['key'] }}'"
                    :class="activeTab === '{{ $item['key'] }}'
                        ? 'font-semibold'
                        : 'hover:text-accent'"
                    :style="activeTab === '{{ $item['key'] }}'
                        ? 'background-color:rgb(var(--color-accent-subtle)); color:rgb(var(--color-accent));'
                        : 'color:rgb(var(--color-text-muted));'"
                    class="text-left rounded-xl px-3 py-2.5 text-sm w-full transition-colors">
                {{ $item['label'] }}
            </button>
            @else
            <button disabled
                    class="text-left rounded-xl px-3 py-2.5 text-sm w-full opacity-40 cursor-not-allowed"
                    style="color:rgb(var(--color-text-muted));">
                {{ $item['label'] }}
                <span class="ml-1 text-[10px] rounded-full px-1.5 py-0.5"
                      style="background-color:rgb(var(--color-surface-muted)); color:rgb(var(--color-text-muted));">Soon</span>
            </button>
            @endif
        @endforeach
        @endif

        {{-- Role-specific shortcuts --}}
        @if ($user->role === \App\Enums\UserRole::Admin)
            <div class="mt-4 pt-4 border-t" style="border-color:rgb(var(--color-surface-border)/0.5);">
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
                   style="color:rgb(var(--color-accent));">
                    Admin Dashboard
                </a>
            </div>
        @elseif ($isSellerProfile)
            <div class="mt-4 pt-4 border-t" style="border-color:rgb(var(--color-surface-border)/0.5);">
                <a href="{{ route('seller.dashboard') }}"
                   class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
                   style="color:rgb(var(--color-accent));">
                    Seller Dashboard
                </a>
            </div>
        @elseif ($isRiderProfile)
            <div class="mt-4 pt-4 border-t" style="border-color:rgb(var(--color-surface-border)/0.5);">
                <a href="{{ route('rider.dashboard') }}"
                   class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
                   style="color:rgb(var(--color-accent));">
                    Rider Dashboard
                </a>
            </div>
        @endif
    </aside>

    {{-- ══════════════════════════════════════════════════════
         RIGHT CONTENT PANEL
         ══════════════════════════════════════════════════════ --}}
    <div class="flex-1 min-w-0">

        {{-- Flash status --}}
        @if (session('status'))
            <p class="alert-success mb-4">{{ session('status') }}</p>
        @endif

        {{-- ── PROFILE panel ──────────────────────────────────────── --}}
        <div x-show="activeTab === 'profile'">
            <div class="fs-card p-5">
                <h2 class="text-base font-semibold mb-4" style="color:rgb(var(--color-text-base));">
                    Account details
                </h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex gap-4">
                        <dt class="w-24 shrink-0" style="color:rgb(var(--color-text-muted));">Name</dt>
                        <dd class="break-words" style="color:rgb(var(--color-text-base));">{{ $user->name }}</dd>
                    </div>
                    <div class="flex gap-4">
                        <dt class="w-24 shrink-0" style="color:rgb(var(--color-text-muted));">Email</dt>
                        <dd class="break-words" style="color:rgb(var(--color-text-base));">{{ $user->email }}</dd>
                    </div>
                    <div class="flex gap-4">
                        <dt class="w-24 shrink-0" style="color:rgb(var(--color-text-muted));">Phone</dt>
                        <dd style="color:rgb(var(--color-text-base));">{{ $user->phone ?: 'Not provided' }}</dd>
                    </div>
                    <div class="flex gap-4">
                        <dt class="w-24 shrink-0" style="color:rgb(var(--color-text-muted));">Role</dt>
                        <dd>
                            <span class="badge badge-accent">{{ ucfirst($user->role->value) }}</span>
                        </dd>
                    </div>
                </dl>
                <a href="{{ route('account.profile.edit') }}" class="btn-accent mt-5 inline-flex">
                    Edit profile &amp; security
                </a>
            </div>

            {{-- Buyer: Become a Seller card --}}
            @if ($user->role === \App\Enums\UserRole::Buyer)
            <a href="{{ route('seller.apply') }}"
               class="fs-card p-4 mt-3 block hover:border-accent transition-colors">
                <p class="font-semibold text-sm" style="color:rgb(var(--color-accent));">
                    {{ $user->sellerApplication ? 'Seller Application' : 'Open a shop' }}
                </p>
                <p class="mt-1 text-sm" style="color:rgb(var(--color-text-muted));">
                    {{ $user->sellerApplication
                        ? 'Status: ' . ucfirst($user->sellerApplication->status) . '. View or update your application.'
                        : 'Apply for admin approval to list and sell your products.' }}
                </p>
            </a>
            @endif

            {{-- Seller storefront card --}}
            @if ($isSellerProfile && $user->shop?->is_active)
            <a href="{{ route('shops.show', $user->shop) }}"
               class="fs-card p-4 mt-3 block hover:border-accent transition-colors">
                <p class="font-semibold text-sm" style="color:rgb(var(--color-accent));">My Storefront</p>
                <p class="mt-1 text-sm" style="color:rgb(var(--color-text-muted));">
                    View {{ $user->shop->name }} as customers see it.
                </p>
            </a>
            @elseif ($isSellerProfile && $user->shop)
            <div class="fs-card p-4 mt-3">
                <p class="font-semibold text-sm" style="color:rgb(var(--color-text-base));">Shop inactive</p>
                <p class="mt-1 text-sm" style="color:rgb(var(--color-text-muted));">
                    Your storefront is not public right now. Open the seller dashboard to review your listings.
                </p>
            </div>
            @elseif ($isSellerProfile)
            <div class="fs-card p-4 mt-3">
                <p class="font-semibold text-sm" style="color:rgb(var(--color-text-base));">No shop linked yet</p>
                <p class="mt-1 text-sm" style="color:rgb(var(--color-text-muted));">
                    Open the seller dashboard to finish setting up your approved shop.
                </p>
            </div>
            @endif

            @if ($isRiderProfile)
            <a href="{{ route('rider.profile') }}"
               class="fs-card p-4 mt-3 block hover:border-accent transition-colors">
                <p class="font-semibold text-sm" style="color:rgb(var(--color-accent));">Rider Application</p>
                <p class="mt-1 text-sm" style="color:rgb(var(--color-text-muted));">
                    View your application details and approval status.
                </p>
            </a>
            @endif
        </div>

        @if ($isBuyerProfile)
        {{-- ── ADDRESSES panel ─────────────────────────────────────── --}}
        <div x-show="activeTab === 'addresses'" style="display:none;">
            <div class="fs-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold" style="color:rgb(var(--color-text-base));">
                        Saved addresses
                    </h2>
                    <a href="{{ route('account.addresses.create') }}"
                       class="text-sm font-medium" style="color:rgb(var(--color-accent));">
                        + Add new
                    </a>
                </div>

                @forelse ($addresses as $address)
                <div class="rounded-xl border p-3 mb-2 flex items-start justify-between gap-3"
                     style="border-color:rgb(var(--color-surface-border)/0.6); background-color:rgb(var(--color-on-surface));">
                    <div class="min-w-0">
                        <p class="text-sm font-medium flex items-center gap-2 flex-wrap"
                           style="color:rgb(var(--color-text-base));">
                            {{ $address->label }}
                            @if ($address->is_default)
                                <span class="badge badge-accent text-[10px]">Default</span>
                            @endif
                        </p>
                        <p class="text-xs mt-0.5 truncate" style="color:rgb(var(--color-text-muted));">
                            {{ $address->formatted() }}
                        </p>
                    </div>
                    <a href="{{ route('account.addresses.edit', $address) }}"
                       class="text-xs shrink-0 font-medium" style="color:rgb(var(--color-accent));">
                        Edit
                    </a>
                </div>
                @empty
                <p class="text-sm" style="color:rgb(var(--color-text-muted));">No saved addresses yet.</p>
                @endforelse

                @if ($addresses->isNotEmpty())
                <a href="{{ route('account.addresses.index') }}"
                   class="mt-3 block text-sm text-center font-medium"
                   style="color:rgb(var(--color-accent));">
                    Manage all addresses →
                </a>
                @endif
            </div>
        </div>

        {{-- ── ORDER panels (all / pending_payment / paid_packed / …) ──── --}}
        @foreach ($tabMap as $tabKey => $statusValues)
        <div x-show="activeTab === '{{ $tabKey }}'" style="display:none;" class="space-y-3">

            @php
                if ($statusValues === null) {
                    $tabOrders = $orders;
                } else {
                    $tabOrders = $orders->filter(
                        fn ($o) => in_array($o->status->value, $statusValues, true)
                    );
                }
            @endphp

            @forelse ($tabOrders as $order)
            <a href="{{ route('orders.show', $order) }}" class="fs-card p-4 flex items-center justify-between gap-3 block hover:border-accent transition-colors">
                <div class="min-w-0">
                    <p class="text-sm font-semibold" style="color:rgb(var(--color-text-base));">
                        #{{ $order->number }}
                    </p>
                    <p class="text-xs mt-0.5" style="color:rgb(var(--color-text-muted));">
                        {{ $order->created_at->format('M j, Y') }}
                        · {{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}
                    </p>
                </div>
                <span class="badge badge-accent shrink-0">{{ $order->status->label() }}</span>
            </a>
            @empty
            <div class="fs-card p-6 text-center">
                <p class="text-sm" style="color:rgb(var(--color-text-muted));">No orders in this category.</p>
            </div>
            @endforelse
            <a href="{{ route('orders.index') }}"
               class="block text-center text-sm font-medium py-2"
               style="color:rgb(var(--color-accent));">
                View all orders →
            </a>
        </div>
        @endforeach

        {{-- ── TO REVIEW placeholder ──────────────────────────────────── --}}
        <div x-show="activeTab === 'review'" style="display:none;">
            <div class="fs-card p-6 text-center">
                <p class="text-base font-semibold mb-2" style="color:rgb(var(--color-text-base));">Coming soon</p>
                <p class="text-sm" style="color:rgb(var(--color-text-muted));">
                    Seller reviews via Google Reviews are planned for a future release.
                </p>
            </div>
        </div>
        @endif

    </div>{{-- /right content panel --}}

</div>{{-- /x-data --}}
@endsection
