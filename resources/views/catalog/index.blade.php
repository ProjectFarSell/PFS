@extends('layouts.app')

@section('title', 'Search · FarSell')

@section('content')
@php $productCount = $products->total(); @endphp

<div class="mb-5">
    @if ($searchedShop)
        <p class="section-eyebrow">Shop listings</p>
        <h1 class="section-title">Products from {{ $searchedShop->name }}</h1>
    @else
        <p class="section-eyebrow">Local shops. Everyday finds.</p>
        <h1 class="section-title">Explore the marketplace</h1>
    @endif
</div>

{{-- ── Category pill strip ─────────────────────────────────────────────────── --}}
<div class="flex gap-2 overflow-x-auto pb-2 mb-4 -mx-1 px-1"
     style="scrollbar-width:none; -ms-overflow-style:none;">
    <a href="{{ route('catalog.index', request()->except(['category', 'page'])) }}"
       class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors
              {{ !$activeCategory
                  ? 'text-accent-text'
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
                  ? 'text-accent-text'
                  : 'border text-text-muted hover:text-accent' }}"
       style="{{ $activeCategory === $cat->id
                  ? 'background-color:rgb(var(--color-accent));'
                  : 'border-color:rgb(var(--color-surface-border));' }}">
        {{ $cat->name }}
    </a>
    @endforeach
</div>

{{-- ── Main layout: filter sidebar + product grid ──────────────────────────── --}}
<div x-data="{
        filtersOpen: false,
        closeFilters() {
            if (!this.filtersOpen) return;
            this.filtersOpen = false;
            this.$nextTick(() => this.$refs.filterToggle.focus());
        },
        trapFilterFocus(event) {
            if (!this.filtersOpen) return;
            const controls = [...event.currentTarget.querySelectorAll('a[href], button, input, select')]
                .filter(control => !control.disabled && control.getClientRects().length);
            const first = controls[0];
            const last = controls.at(-1);
            if (event.shiftKey && event.target === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && event.target === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    }" @keydown.escape.window="closeFilters()"
    @resize.window="if (!$refs.filterToggle.getClientRects().length) filtersOpen = false"
    class="flex gap-5 items-start">

    {{-- Filter sidebar (desktop persistent / mobile bottom-sheet) --}}
    @include('catalog.partials.filter-sidebar')

    {{-- Product area --}}
    <div class="flex-1 min-w-0">

        {{-- Mobile filter toggle + result count --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <p class="text-sm" style="color: rgb(var(--color-text-muted));">
                {{ $productCount }} {{ Str::plural('product', $productCount) }}
            </p>
            <x-ui.button variant="secondary" type="button" x-ref="filterToggle" @click="filtersOpen = true; $nextTick(() => $refs.closeFilters.focus())" ::aria-expanded="filtersOpen" aria-controls="mobile-filters"
                    class="lg:hidden">
                <x-ui.icon name="filter" size="sm" />
                Filters
            </x-ui.button>
            <form action="{{ route('catalog.index') }}" method="get" class="ml-auto flex items-center gap-2">
                @foreach (request()->only(['q', 'category', 'shop', 'shop_search', 'price_min', 'price_max', 'city_id', 'same_day', 'clear_city']) as $key => $value)
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
                <noscript><x-ui.button variant="secondary" type="submit">Sort</x-ui.button></noscript>
            </form>
        </div>

        {{-- Product grid --}}
        <h2 class="sr-only">Product results</h2>
        @php($firstProductWithImage = $products->first(fn ($product) => $product->image_path))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @forelse ($products as $product)
                @include('catalog.partials.card', [
                    'product'     => $product,
                    'priorityImage' => $product === $firstProductWithImage,
                ])
            @empty
                <div class="col-span-full rounded-xl border p-6 text-sm"
                     style="border-color:rgb(var(--color-surface-border)); background-color:rgb(var(--color-surface)); color:rgb(var(--color-text-muted));">
                    <h2 class="font-semibold text-text-base">No lots match that search.</h2>
                    <p class="mt-2">Try a broader term or remove a filter to see more finds.</p>
                    <x-ui.button variant="secondary" :href="route('catalog.index', ['clear_city' => 1])" class="mt-4">Reset search &amp; filters</x-ui.button>
                </div>
            @endforelse
        </div>

        @if ($products->hasPages())
            <div class="mt-5">{{ $products->links() }}</div>
        @endif
    </div>

</div>
@endsection
