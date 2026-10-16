@php
    $mobileLinks = [
        ['label' => 'Home', 'route' => 'home', 'active' => request()->routeIs('home', 'welcome'), 'path' => 'm3 10 9-7 9 7v10H3Zm6 10v-7h6v7'],
        ['label' => 'Browse', 'route' => 'catalog.index', 'active' => request()->routeIs('catalog.*', 'products.*'), 'path' => 'M3 3h7v7H3Zm11 0h7v7h-7ZM3 14h7v7H3Zm11 0h7v7h-7Z'],
        ['label' => 'Orders', 'route' => 'orders.index', 'active' => request()->routeIs('orders.*'), 'path' => 'M7 3h10l3 5v13H4V8Zm-3 5h16M9 12h6'],
        ['label' => 'Cart', 'route' => 'cart.index', 'active' => request()->routeIs('cart.*', 'checkout.*'), 'path' => 'M3 3h2l3 12h11l2-8H6M9 20h.01M18 20h.01'],
    ];
@endphp
<nav aria-label="Mobile shopping navigation" :inert="drawerOpen" class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t border-surface-border/50 bg-surface/95 px-2 pt-1 backdrop-blur-md md:hidden" style="padding-bottom: max(.25rem, env(safe-area-inset-bottom));">
    @foreach ($mobileLinks as $link)
        <a href="{{ route($link['route']) }}" class="relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-medium {{ $link['active'] ? 'text-accent bg-surface-muted' : 'text-text-muted' }}" @if ($link['active']) aria-current="page" @endif>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $link['path'] }}"/></svg>
            {{ $link['label'] }}
            @if ($link['label'] === 'Cart' && $cartCount > 0)
                <span class="absolute top-0 left-1/2 ml-2 rounded-full bg-accent px-1.5 text-[10px] text-accent-text">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
            @endif
        </a>
    @endforeach
</nav>
