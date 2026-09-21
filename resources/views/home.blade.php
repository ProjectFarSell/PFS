@extends('layouts.app')

@section('title', 'FarSell — Surplus marketplace')

@section('content')
<section class="rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 text-white p-5 mb-4">
    <p class="text-xs uppercase tracking-wide text-violet-200">Doorzo-style lots · Shopee-fast checkout</p>
    <h1 class="text-2xl font-semibold mt-1">Auction surplus. Everyday prices.</h1>
    <p class="text-sm text-violet-100 mt-2 max-w-xl">Discover surplus finds from local shops. Browse freely and sign in when you are ready to place an order.</p>
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('catalog.index') }}" class="rounded-full bg-surface text-accent text-sm font-medium px-4 py-2">Browse products</a>
        <a href="{{ route('shops.index') }}" class="rounded-full border border-white/70 text-sm px-4 py-2">Explore shops</a>
    </div>
</section>

    <div class="flex gap-3 overflow-x-auto pb-3 -mx-1">
        @foreach ($categories as $category)
            <a href="{{ route('catalog.index', ['category' => $category->id]) }}" class="shrink-0 w-16 text-center">
                <div class="h-14 w-14 mx-auto rounded-2xl bg-surface border border-surface-border flex items-center justify-center text-lg">{{ $category->icon }}</div>
                <p class="mt-1 text-[11px] text-text-muted truncate">{{ $category->name }}</p>
            </a>
        @endforeach
    </div>

    @if ($flash->isNotEmpty())
        <h2 class="text-base font-semibold mt-2 mb-2">Flash deals</h2>
        <div class="flex gap-3 overflow-x-auto pb-3">
            @foreach ($flash as $product)
                @include('catalog.partials.card', ['product' => $product, 'compact' => true])
            @endforeach
        </div>
    @endif

    <h2 class="text-base font-semibold mt-2 mb-2">For you</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @forelse ($products as $product)
            @include('catalog.partials.card', ['product' => $product])
        @empty
            <p class="col-span-full rounded-xl border border-surface-border bg-surface p-6 text-sm text-text-muted">No products are available yet. Check back soon for new listings.</p>
        @endforelse
    </div>
@endsection
