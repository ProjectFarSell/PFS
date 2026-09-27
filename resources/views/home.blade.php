@extends('layouts.app')

@section('title', 'FarSell — Surplus marketplace')

@section('content')
@php
    // ── Carousel slides ───────────────────────────────────────────────────────
    $slides = [
        [
            'title'    => 'Auction surplus. Everyday prices.',
            'subtitle' => 'Discover Japan lots from local shops. Browse freely and sign in when you are ready to order.',
            'cta_text' => 'Browse products',
            'cta_href' => route('catalog.index'),
            'bg'       => 'from-violet-600 to-indigo-700',
        ],
        [
            'title'    => 'Open your shop today',
            'subtitle' => 'Sell surplus inventory to thousands of buyers across the Philippines.',
            'cta_text' => 'Become a Seller',
            'cta_href' => route('seller.apply'),
            'bg'       => 'from-emerald-600 to-teal-700',
        ],
        [
            'title'    => 'Deliver & earn with FarSell',
            'subtitle' => 'Join our rider network and earn on every completed delivery in your city.',
            'cta_text' => 'Become a Rider',
            'cta_href' => route('rider.register'),
            'bg'       => 'from-orange-500 to-pink-600',
        ],
    ];

    // ── Category SVG icon map (keyed by slug) ─────────────────────────────────
    $categoryIcons = [
        'womens'     => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z"/></svg>',
        'mens'       => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0"/></svg>',
        'kids'       => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75s.168-.75.375-.75.375.336.375.75Zm4.875 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Z"/></svg>',
        'home'       => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>',
        'beauty'     => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>',
        'sports'     => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c0 0-4 4-4 9s4 9 4 9M12 3c0 0 4 4 4 9s-4 9-4 9M3 12h18"/></svg>',
        'gadgets'    => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V8.25a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 8.25v9a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z"/></svg>',
        'surplus'    => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>',
        'fashion'    => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z"/></svg>',
        'electronics'=> '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V8.25a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 8.25v9a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z"/></svg>',
        'home-living'=> '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>',
    ];

    // ── Flash deal countdown end time (midnight tonight) ──────────────────────
    $midnight = strtotime('tomorrow midnight');
@endphp

{{-- ═══════════════════════════════════════════════════════════════
     HERO CAROUSEL  (Requirement 3)
     ═══════════════════════════════════════════════════════════════ --}}
<div x-data="carousel({{ count($slides) }}, 5000)"
     @mouseenter="pause()"
     @mouseleave="play()"
     @touchstart.passive="onTouchStart($event)"
     @touchend.passive="onTouchEnd($event)"
     class="relative rounded-2xl overflow-hidden mb-5"
     :class="total === 0 && 'h-0'">

    {{-- Slides --}}
    @foreach ($slides as $i => $slide)
    <div x-show="isActive({{ $i }})"
         x-transition:enter="transition-opacity duration-500"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="min-h-[240px] lg:min-h-[400px] bg-gradient-to-r {{ $slide['bg'] }}
                text-white p-6 lg:p-12 flex flex-col justify-end"
         style="{{ $i > 0 ? 'display:none;' : '' }}">
        <h2 class="text-2xl lg:text-4xl font-bold leading-tight max-w-2xl">{{ $slide['title'] }}</h2>
        <p class="mt-2 text-sm lg:text-base text-white/80 max-w-xl">{{ $slide['subtitle'] }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ $slide['cta_href'] }}"
               class="rounded-full bg-white/20 border border-white/40
                      text-white text-sm font-semibold px-5 py-2.5 hover:bg-white/30 transition-colors">
                {{ $slide['cta_text'] }}
            </a>
            @if ($i === 0)
                <a href="{{ route('shops.index') }}"
                   class="rounded-full border border-white/60 text-white text-sm font-semibold px-5 py-2.5 hover:bg-white/20 transition-colors">
                    Explore shops
                </a>
            @endif
        </div>
    </div>
    @endforeach

    {{-- Prev / Next arrows (hidden when total ≤ 1) --}}
    <button x-show="total > 1"
            @click="prev()"
            aria-label="Previous slide"
            class="absolute left-3 top-1/2 -translate-y-1/2 z-10 flex h-9 w-9 items-center
                   justify-center rounded-full bg-black/40 backdrop-blur-sm text-white
                   hover:bg-black/60 transition-colors focus:outline-none"
            style="display:none;">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
    </button>
    <button x-show="total > 1"
            @click="next()"
            aria-label="Next slide"
            class="absolute right-3 top-1/2 -translate-y-1/2 z-10 flex h-9 w-9 items-center
                   justify-center rounded-full bg-black/40 backdrop-blur-sm text-white
                   hover:bg-black/60 transition-colors focus:outline-none"
            style="display:none;">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        </svg>
    </button>

    {{-- Play / Pause toggle --}}
    <button @click="togglePlay()"
            :aria-label="paused ? 'Play slideshow' : 'Pause slideshow'"
            class="absolute bottom-3 right-3 z-10 flex h-7 w-7 items-center justify-center
                   rounded-full bg-black/40 backdrop-blur-sm text-white hover:bg-black/60
                   transition-colors focus:outline-none">
        {{-- Pause icon when playing --}}
        <svg x-show="!paused" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
            <path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 0 1 .75-.75H9a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75H7.5a.75.75 0 0 1-.75-.75V5.25Zm7 0a.75.75 0 0 1 .75-.75H15a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75V5.25Z" clip-rule="evenodd"/>
        </svg>
        {{-- Play icon when paused --}}
        <svg x-show="paused" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" style="display:none;">
            <path fill-rule="evenodd" d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z" clip-rule="evenodd"/>
        </svg>
    </button>

    {{-- Pagination dots (hidden when total ≤ 1) --}}
    <div x-show="total > 1"
         class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10"
         style="display:none;">
        @foreach ($slides as $i => $_)
        <button @click="goTo({{ $i }})"
                :class="isActive({{ $i }})
                    ? 'w-6 h-2 rounded-full bg-purple-400 transition-all duration-200'
                    : 'w-2 h-2 rounded-full bg-white/50'"
                :aria-label="'Go to slide {{ $i + 1 }}'">
        </button>
        @endforeach
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     CATEGORY PILLS  (Requirement 7a — SVG icons with emoji fallback)
     ═══════════════════════════════════════════════════════════════ --}}
<div class="flex gap-3 overflow-x-auto pb-3 -mx-1 px-1 mb-2"
     style="scrollbar-width:none; -ms-overflow-style:none;">
    @foreach ($categories as $category)
    <a href="{{ route('catalog.index', ['category' => $category->id]) }}"
       class="shrink-0 w-16 text-center group">
        <div class="h-14 w-14 mx-auto rounded-2xl fs-card flex items-center justify-center
                    group-hover:border-accent transition-colors"
             style="color: rgb(var(--color-text-muted));">
            @if (isset($categoryIcons[$category->slug]))
                {!! $categoryIcons[$category->slug] !!}
            @else
                <span class="text-lg">{{ $category->icon }}</span>
            @endif
        </div>
        <p class="mt-1 text-[11px] text-text-muted truncate group-hover:text-accent transition-colors">
            {{ $category->name }}
        </p>
    </a>
    @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════
     FLASH DEALS  (countdown timers + stock bars)
     ═══════════════════════════════════════════════════════════════ --}}
@if ($flash->isNotEmpty())
    <h2 class="text-base font-semibold mt-2 mb-2" style="color: rgb(var(--color-text-base));">
        ⚡ Flash deals
    </h2>
    <div class="flex gap-3 overflow-x-auto pb-3" style="scrollbar-width:none; -ms-overflow-style:none;">
        @foreach ($flash as $product)
        @php $pct = min(100, round(($product->stock / 50) * 100)); @endphp
        <div x-data="countdown({{ $midnight }})" class="w-36 shrink-0">
            @include('catalog.partials.card', ['product' => $product, 'compact' => true])
            {{-- Countdown timer --}}
            <p class="text-center text-[11px] font-mono mt-1"
               style="color: rgb(var(--color-accent));"
               x-text="display"></p>
            {{-- Stock progress bar --}}
            <div class="mt-1 h-1.5 rounded-full overflow-hidden"
                 style="background-color: rgb(var(--color-surface-muted));">
                <div class="h-full rounded-full transition-all"
                     style="width: {{ $pct }}%; background-color: rgb(var(--color-accent));"></div>
            </div>
            <p class="text-[10px] text-center mt-0.5" style="color: rgb(var(--color-text-muted));">
                {{ $product->stock }} left
            </p>
        </div>
        @endforeach
    </div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     FOR YOU GRID  (skeleton shimmers while Alpine initialises)
     ═══════════════════════════════════════════════════════════════ --}}
<h2 class="text-base font-semibold mt-2 mb-2" style="color: rgb(var(--color-text-base));">For you</h2>

<div x-data="{ ready: false }" x-init="$nextTick(() => ready = true)"
     class="grid grid-cols-2 sm:grid-cols-4 gap-3">

    {{-- Skeleton shimmer cards shown until Alpine boots --}}
    <template x-if="!ready">
        <div class="contents">
            @for ($i = 0; $i < 4; $i++)
            <div class="fs-card animate-pulse overflow-hidden">
                <div class="aspect-square"
                     style="background-color: rgb(var(--color-surface-muted));"></div>
                <div class="p-2 space-y-2">
                    <div class="h-2.5 rounded w-3/4"
                         style="background-color: rgb(var(--color-surface-muted));"></div>
                    <div class="h-2.5 rounded w-1/2"
                         style="background-color: rgb(var(--color-surface-muted));"></div>
                </div>
            </div>
            @endfor
        </div>
    </template>

    {{-- Real product cards --}}
    @forelse ($products as $product)
        @include('catalog.partials.card', ['product' => $product])
    @empty
        <p class="col-span-full rounded-xl border p-6 text-sm"
           style="border-color: rgb(var(--color-surface-border)); background-color: rgb(var(--color-surface)); color: rgb(var(--color-text-muted));">
            No products are available yet. Check back soon for new listings.
        </p>
    @endforelse
</div>
@endsection
