@extends('layouts.app')
@section('title', 'Discover your next find · FarSell')

@section('content')
@php
    $slides = [
        ['eyebrow' => 'Good finds. Closer to home.', 'title' => 'Curated Surplus. Unbeatable Everyday Prices.', 'subtitle' => 'Explore local shops, discover something unexpected, and sign in when you are ready to order.', 'cta_text' => 'Browse products', 'cta_href' => route('catalog.index')],
        ['eyebrow' => 'Make room for your next obsession', 'title' => 'Small upgrades. Big personality.', 'subtitle' => 'From custom keyboards to collectible figures, find the pieces that make a space yours.', 'cta_text' => 'Explore gadgets', 'cta_href' => route('catalog.index', ['q' => 'keyboard'])],
        ['eyebrow' => 'Meet your next favorite shop', 'title' => 'Find local. Shop with confidence.', 'subtitle' => 'Explore shop profiles and product details before deciding what comes home with you.', 'cta_text' => 'Explore shops', 'cta_href' => route('shops.index')],
    ];
    $interests = [['Trading Cards', 'trading card'], ['Figures & Models', 'figure'], ['Custom Keyboards', 'keyboard'], ['Gaming & Audio', 'gaming'], ['Anime Merch', 'anime'], ['Desk Accessories', 'desk']];
    $collections = [
        ['title' => 'Build your happy place', 'copy' => 'Keyboards, desk finds, and the little details.', 'query' => 'keyboard', 'label' => 'For your setup', 'symbol' => 'keyboard'],
        ['title' => 'One more for the collection', 'copy' => 'Discover figures with a little personality.', 'query' => 'figure', 'label' => 'For your shelf', 'symbol' => 'figure'],
        ['title' => 'Find your next favorite card', 'copy' => 'Explore trading cards from local shops.', 'query' => 'trading card', 'label' => 'For your collection', 'symbol' => 'cards'],
    ];
@endphp

<div class="space-y-10 sm:space-y-12">
    <x-carousel :slides="$slides" />
    <section aria-labelledby="interests-heading">
        <div class="discovery-heading">
            <div><p class="section-eyebrow">A good place to start</p><h2 id="interests-heading" class="section-title">What are you into?</h2></div>
            <a href="{{ route('catalog.index') }}" class="discovery-link">Browse all <span aria-hidden="true">→</span></a>
        </div>
        <nav class="scroll-x pt-1" aria-label="Explore interests">
            @foreach ($interests as [$label, $query])
                <a href="{{ route('catalog.index', ['q' => $query]) }}" class="interest-pill">{{ $label }} <span aria-hidden="true" class="text-accent">→</span></a>
            @endforeach
        </nav>
        @if ($categories->isNotEmpty())
            <details class="mt-3 text-sm text-text-muted">
                <summary class="cursor-pointer w-fit py-2 hover:text-accent">Browse marketplace categories</summary>
                <nav class="flex flex-wrap gap-2 pt-2" aria-label="Marketplace categories">
                    @foreach ($categories as $category)
                        <a href="{{ route('catalog.index', ['category' => $category->id]) }}" class="interest-pill text-xs">{{ $category->name }} <span class="text-text-muted tabular-nums">{{ $category->products_count }}</span></a>
                    @endforeach
                </nav>
            </details>
        @endif
    </section>

    @if ($flash->isNotEmpty())
        <section aria-labelledby="deals-heading">
            <div class="discovery-heading">
                <div><p class="section-eyebrow">Worth a closer look</p><h2 id="deals-heading" class="section-title">Deals to discover</h2></div>
                <span class="badge badge-accent">Selected deals</span>
            </div>
            <div class="product-rail">
                @foreach ($flash as $product)
                    <div class="min-w-0">
                        @include('catalog.partials.card', ['product' => $product])
                        <p class="mt-2 px-1 text-xs text-text-muted"><strong class="text-text-base tabular-nums">{{ $product->availableStock() }}</strong> available</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section aria-labelledby="featured-heading" class="deferred-section">
        <div class="discovery-heading"><div><p class="section-eyebrow">A little inspiration</p><h2 id="featured-heading" class="section-title">Featured collections</h2></div></div>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($collections as $collection)
                <a href="{{ route('catalog.index', ['q' => $collection['query']]) }}" class="collection-card group">
                    <div class="collection-art" aria-hidden="true"><x-discovery-art :kind="$collection['symbol']" /></div>
                    <div class="relative p-5">
                        <p class="section-eyebrow">{{ $collection['label'] }}</p>
                        <h3 class="mt-2 text-xl font-semibold tracking-tight">{{ $collection['title'] }}</h3>
                        <p class="mt-2 text-sm text-text-muted leading-relaxed">{{ $collection['copy'] }}</p>
                        <span class="mt-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-accent">Explore collection <span aria-hidden="true" class="transition-transform group-hover:translate-x-1">→</span></span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="popular-heading" class="deferred-section">
        <div class="discovery-heading"><div><p class="section-eyebrow">On the community’s radar</p><h2 id="popular-heading" class="section-title">Popular right now</h2><p class="mt-1 text-sm text-text-muted">Most saved by FarSell shoppers.</p></div></div>
        @if ($popular->isNotEmpty())
            <div class="product-rail">
                @foreach ($popular as $product)
                    <div class="min-w-0">
                        @include('catalog.partials.card', ['product' => $product])
                        <p class="mt-2 px-1 text-xs text-text-muted"><strong class="text-accent tabular-nums">{{ $product->saved_items_count }}</strong> {{ Str::plural('save', $product->saved_items_count) }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="fs-card p-6 sm:p-8 flex flex-wrap items-center justify-between gap-4">
                <div><h3 class="font-semibold">Help the next great find stand out.</h3><p class="mt-1 text-sm text-text-muted">Save items you love. Community favorites will appear here.</p></div>
                <a href="{{ route('catalog.index') }}" class="btn-outline">Discover products</a>
            </div>
        @endif
    </section>

    <section aria-labelledby="latest-heading" class="deferred-section">
        <div class="discovery-heading">
            <div><p class="section-eyebrow">Fresh on FarSell</p><h2 id="latest-heading" class="section-title">New finds</h2><p class="mt-1 text-sm text-text-muted">The latest listings from local shops.</p></div>
            <a href="{{ route('catalog.index', ['sort' => 'newest']) }}" class="discovery-link">View all <span aria-hidden="true">→</span></a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
            @forelse ($products as $product)
                @include('catalog.partials.card', ['product' => $product])
            @empty
                <div class="col-span-full fs-card p-8 text-center">
                    <h3 class="font-semibold">No products are available yet.</h3>
                    <p class="mt-2 text-sm text-text-muted">Check back soon for new listings, or explore our shop directory.</p>
                    <a href="{{ route('shops.index') }}" class="btn-outline mt-5">Explore shops</a>
                </div>
            @endforelse
        </div>
        @if ($products->isNotEmpty())
            <div class="mt-7 text-center"><a href="{{ route('catalog.index', ['sort' => 'newest']) }}" class="btn-outline">Keep exploring <span aria-hidden="true">→</span></a></div>
        @endif
    </section>
</div>
@endsection
