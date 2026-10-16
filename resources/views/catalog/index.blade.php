@extends('layouts.app')

@section('title', 'Search · FarSell')

@section('content')
@php $productCount = $products->total(); @endphp

<div class="mb-5"><p class="section-eyebrow">Local shops. Everyday finds.</p><h1 class="section-title">Explore the marketplace</h1></div>

{{-- ── Category pill strip ─────────────────────────────────────────────────── --}}
<div class="flex gap-2 overflow-x-auto pb-2 mb-4 -mx-1 px-1"
     style="scrollbar-width:none; -ms-overflow-style:none;">
    <a href="{{ route('catalog.index', request()->except(['category', 'page'])) }}"
       class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors
              {{ !$activeCategory
                  ? 'text-white'
                  : 'border text-text-muted hover:text-accent' }}"
       style="{{ !$activeCategory
                  ? 'background-color:rgb(var(--color-accent));'
                  : 'border-color:rgb(var(--color-surface-border));' }}">
        All
    </a>
    @foreach ($categories as $cat)
    <a href="{{ route('catalog.index', array_merge(request()->except(['category', 'page']), ['category' => $cat->id])) }}"
       class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors
              {{ $activeCategory === $cat->id
                  ? 'text-white'
                  : 'border text-text-muted hover:text-accent' }}"
       style="{{ $activeCategory === $cat->id
                  ? 'background-color:rgb(var(--color-accent));'
                  : 'border-color:rgb(var(--color-surface-border));' }}">
        {{ $cat->name }}
    </a>
    @endforeach
</div>

{{-- ── Main layout: filter sidebar + product grid ──────────────────────────── --}}
<div x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false" class="flex gap-5 items-start">

    {{-- Filter sidebar (desktop persistent / mobile bottom-sheet) --}}
    @include('catalog.partials.filter-sidebar')

    {{-- Product area --}}
    <div class="flex-1 min-w-0">

        {{-- Mobile filter toggle + result count --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <p class="text-sm" style="color: rgb(var(--color-text-muted));">
                {{ $productCount }} {{ Str::plural('product', $productCount) }}
            </p>
            <button @click="filtersOpen = true; $nextTick(() => $refs.closeFilters.focus())" :aria-expanded="filtersOpen" aria-controls="mobile-filters"
                    class="btn-outline text-sm px-4 py-2 lg:hidden">
                <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>
                </svg>
                Filters
            </button>
            <form action="{{ route('catalog.index') }}" method="get" class="ml-auto flex items-center gap-2">
                @foreach (request()->only(['q', 'category', 'shop', 'price_min', 'price_max', 'city_id', 'same_day', 'clear_city']) as $key => $value)
                    @foreach (is_array($value) ? $value : [$value] as $item)
                        <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $item }}">
                    @endforeach
                @endforeach
                <label for="catalog-sort" class="sr-only">Sort products</label>
                <select id="catalog-sort" name="sort" class="fs-input min-h-11 text-sm" onchange="this.form.requestSubmit()">
                    @foreach (['newest' => 'Newest first', 'relevance' => 'Relevance', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn-outline" type="submit">Sort</button></noscript>
            </form>
        </div>

        {{-- Product grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @forelse ($products as $product)
                @include('catalog.partials.card', [
                    'product'     => $product,
                ])
            @empty
                <div class="col-span-full rounded-xl border p-6 text-sm"
                     style="border-color:rgb(var(--color-surface-border)); background-color:rgb(var(--color-surface)); color:rgb(var(--color-text-muted));">
                    <h2 class="font-semibold text-text-base">No lots match that search.</h2>
                    <p class="mt-2">Try a broader term or remove a filter to see more finds.</p>
                    <a href="{{ route('catalog.index', ['clear_city' => 1]) }}" class="btn-outline mt-4">Reset search &amp; filters</a>
                </div>
            @endforelse
        </div>

        @if ($products->hasPages())
            <div class="mt-5">{{ $products->links() }}</div>
        @endif
    </div>

</div>
@endsection
