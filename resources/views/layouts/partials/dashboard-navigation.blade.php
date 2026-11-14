@php
    $admin = $portalRole === \App\Enums\UserRole::Admin;
    $dashboardRoute = $admin ? 'admin.dashboard' : 'seller.dashboard';
    $navigation = $admin ? [
        ['Dashboard', 'home', 'admin.dashboard', request()->routeIs('admin.dashboard')],
        ['Logistics', 'delivery', 'admin.logistics.index', request()->routeIs('admin.logistics.*')],
        ['Products', 'product', 'admin.products.index', request()->routeIs('admin.products.*')],
        ['Categories', 'browse', 'admin.categories.index', request()->routeIs('admin.categories.*')],
        ['Partners', 'buyers', 'admin.partners.index', request()->routeIs('admin.partners.*', 'admin.sellers.*') || (request()->routeIs('admin.riders.*') && !request()->routeIs('admin.riders.barangays.*'))],
    ] : [
        ['Dashboard', 'home', 'seller.dashboard', request()->routeIs('seller.dashboard')],
        ['Add product', 'add', 'seller.products.create', request()->routeIs('seller.products.create')],
        ['Orders', 'order', 'fulfillments.index', request()->routeIs('fulfillments.*')],
        ['Chat', 'chat', 'chat.index', request()->routeIs('chat.*')],
        ['Storefront', 'browse', 'home', false],
    ];
    $overflow = $admin ? [
        ['Barangay assignments', 'location', 'admin.riders.barangays.index', request()->routeIs('admin.riders.barangays.*')],
        ['Rider records', 'account', 'admin.rider-records.index', request()->routeIs('admin.rider-records.*')],
        ['Reviews', 'rating', 'admin.reviews.index', request()->routeIs('admin.reviews.*')],
        ['Dispatch', 'delivery', 'fulfillments.index', request()->routeIs('fulfillments.*')],
    ] : [];
    $overflowActive = in_array(true, array_column($overflow, 3), true);
    $notificationsActive = $admin && request()->routeIs('admin.notifications.*');
@endphp

<header data-dashboard-shell
    class="sticky top-0 z-40 border-b border-dashboard-light-border bg-dashboard-light-surface dark:border-dashboard-dark-border dark:bg-dashboard-dark-surface [&_:focus-visible]:!ring-dashboard-light-accent dark:[&_:focus-visible]:!ring-dashboard-dark-accent"
    x-data="{ sheetOpen: false }"
    @resize.window="if (sheetOpen && window.matchMedia('(min-width: 1024px)').matches) $refs.sheet.close()">
    <div class="mx-auto flex min-h-14 max-w-screen-2xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <a href="{{ route($dashboardRoute) }}" class="inline-flex min-h-target shrink-0 items-center gap-2 rounded-ui font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 lg:min-h-target-desktop">
            <span aria-hidden="true" class="grid size-7 place-items-center rounded-lg bg-dashboard-light-accent text-sm text-white dark:bg-dashboard-dark-accent dark:text-dashboard-dark-page">F</span>
            <span>FarSell <span class="text-xs font-medium text-dashboard-light-muted dark:text-dashboard-dark-muted">{{ ucfirst($portalRole->value) }}</span></span>
        </a>

        <nav aria-label="Portal navigation" class="hidden items-center gap-1 lg:flex">
            @foreach ($navigation as [$label, $icon, $routeName, $active])
                <x-dashboard.nav-link :href="route($routeName)" :label="$label" :icon="$icon" :active="$active"
                    :count="$routeName === 'chat.index' ? $chatUnreadCount : 0" class="!px-2.5" />
            @endforeach

            <div class="relative" x-data="{ open: false }" @click.outside="open = false"
                @keydown.escape="if (open) { $event.stopPropagation(); open = false; $refs.more.focus() }">
                <x-ui.button variant="tertiary" size="sm" icon="expand" icon-position="right" x-ref="more"
                    aria-controls="dashboard-more" ::aria-expanded="open" @click="open = !open"
                    class="lg:min-h-target-desktop !px-2.5 !text-sm {{ $overflowActive ? 'bg-dashboard-light-active dark:bg-dashboard-dark-active' : '' }}">More</x-ui.button>
                <div id="dashboard-more" x-show="open" x-cloak
                    class="absolute right-0 top-full mt-2 w-64 rounded-dashboard-card border border-dashboard-light-border bg-dashboard-light-surface p-2 shadow-card-md dark:border-dashboard-dark-border dark:bg-dashboard-dark-surface">
                    @foreach ($overflow as [$label, $icon, $routeName, $active])
                        <x-dashboard.nav-link :href="route($routeName)" :label="$label" :icon="$icon" :active="$active"
                            class="w-full !justify-start" />
                    @endforeach
                    @include('layouts.partials.dashboard-account-actions')
                </div>
            </div>
        </nav>

        <div class="hidden shrink-0 items-center gap-1 lg:flex">
            <x-ui.icon-button icon="help" label="Help" tooltip="Help center unavailable" disabled class="lg:min-h-target-desktop lg:min-w-target-desktop" />
            <x-ui.icon-button icon="settings" label="Settings" :href="route('account.profile.edit')"
                :aria-current="request()->routeIs('account.profile.edit') ? 'page' : null" class="lg:min-h-target-desktop lg:min-w-target-desktop" />
            <x-ui.icon-button icon="notification" label="Notifications" :href="$admin ? route('admin.notifications.index') : null"
                :tooltip="$admin ? null : 'Seller notifications unavailable'" :disabled="!$admin"
                :aria-current="$notificationsActive ? 'page' : null"
                class="lg:min-h-target-desktop lg:min-w-target-desktop {{ $notificationsActive ? 'bg-dashboard-light-active dark:bg-dashboard-dark-active' : '' }}" />
            <x-ui.icon-button icon="account" label="Profile" :href="route('account.profile')" class="lg:min-h-target-desktop lg:min-w-target-desktop" />
        </div>

        <x-ui.icon-button icon="menu" label="Open navigation" class="lg:hidden" x-ref="menu"
            aria-controls="dashboard-navigation-sheet" aria-haspopup="dialog" ::aria-expanded="sheetOpen"
            @click="$refs.sheet.showModal(); sheetOpen = true" />
    </div>

    <dialog id="dashboard-navigation-sheet" x-ref="sheet" aria-labelledby="dashboard-navigation-title"
        @close="sheetOpen = false; if ($refs.menu.getClientRects().length) $refs.menu.focus()"
        class="fixed inset-x-0 top-0 m-0 max-h-[100dvh] w-full max-w-none overflow-y-auto rounded-b-dashboard-card border-b border-dashboard-light-border bg-dashboard-light-surface px-4 pb-4 text-dashboard-light-ink shadow-card-md backdrop:bg-black/40 dark:border-dashboard-dark-border dark:bg-dashboard-dark-surface dark:text-dashboard-dark-ink sm:px-6">
        <div class="flex min-h-14 items-center justify-between border-b border-dashboard-light-border dark:border-dashboard-dark-border">
            <h2 id="dashboard-navigation-title" class="font-semibold">Navigation</h2>
            <x-ui.icon-button icon="close" label="Close navigation" autofocus @click="$refs.sheet.close()" />
        </div>
        <nav aria-label="Mobile portal navigation" class="mt-2 grid gap-2">
            @foreach ([...$navigation, ...$overflow] as [$label, $icon, $routeName, $active])
                <x-dashboard.nav-link :href="route($routeName)" :label="$label" :icon="$icon" :active="$active"
                    :count="$routeName === 'chat.index' ? $chatUnreadCount : 0" class="w-full !justify-start" />
            @endforeach
        </nav>
        <div class="mt-3 grid gap-2 border-t border-dashboard-light-border pt-3 dark:border-dashboard-dark-border">
            <x-ui.button variant="tertiary" icon="help" class="w-full !justify-start" disabled>Help unavailable</x-ui.button>
            <x-dashboard.nav-link :href="route('account.profile.edit')" label="Settings" icon="settings"
                :active="request()->routeIs('account.profile.edit')" class="w-full !justify-start" />
            @if ($admin)
                <x-dashboard.nav-link :href="route('admin.notifications.index')" label="Notifications" icon="notification"
                    :active="$notificationsActive" class="w-full !justify-start" />
            @else
                <x-ui.button variant="tertiary" icon="notification" class="w-full !justify-start" disabled>Notifications unavailable</x-ui.button>
            @endif
            <x-dashboard.nav-link :href="route('account.profile')" label="Profile" icon="account" class="w-full !justify-start" />
            @include('layouts.partials.dashboard-account-actions')
        </div>
    </dialog>
</header>
