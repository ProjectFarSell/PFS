@php
    $mobileLinks = [
        ['label' => 'Home', 'route' => 'home', 'active' => request()->routeIs('home', 'welcome'), 'icon' => 'home'],
        ['label' => 'Browse', 'route' => 'catalog.index', 'active' => request()->routeIs('catalog.*', 'products.*'), 'icon' => 'browse'],
        ['label' => 'Orders', 'route' => 'orders.index', 'active' => request()->routeIs('orders.*'), 'icon' => 'order'],
        ['label' => 'Cart', 'route' => 'cart.index', 'active' => request()->routeIs('cart.*', 'checkout.*'), 'icon' => 'cart'],
    ];
@endphp
<nav aria-label="Mobile shopping navigation" :inert="drawerOpen" class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 gap-2 border-t border-surface-border/50 bg-surface/95 px-2 pt-1 backdrop-blur-md md:hidden" style="padding-bottom: max(.25rem, env(safe-area-inset-bottom));">
    @foreach ($mobileLinks as $link)
        <a href="{{ route($link['route']) }}" class="relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-medium {{ $link['active'] ? 'text-accent bg-surface-muted' : 'text-text-muted' }}" @if ($link['active']) aria-current="page" @endif>
            <x-ui.icon :name="$link['icon']" />
            {{ $link['label'] }}
            @if ($link['label'] === 'Cart' && $cartCount > 0)
                <span class="absolute top-0 left-1/2 ml-2 rounded-full bg-accent px-1.5 text-[10px] text-accent-text">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
            @endif
        </a>
    @endforeach
</nav>
