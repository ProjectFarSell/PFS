@extends('layouts.app')

@section('title', 'Search · FarSell')

@section('content')
@php $productCount = $products->total(); @endphp

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
<div x-data="{ filtersOpen: false }" class="flex gap-5 items-start">

    {{-- Filter sidebar (desktop persistent / mobile bottom-sheet) --}}
    @include('catalog.partials.filter-sidebar')

    {{-- Product area --}}
    <div class="flex-1 min-w-0">

        {{-- Mobile filter toggle + result count --}}
        <div class="flex items-center justify-between mb-3 lg:hidden">
            <p class="text-sm" style="color: rgb(var(--color-text-muted));">
                {{ $productCount }} {{ Str::plural('product', $productCount) }}
            </p>
            <button @click="filtersOpen = true"
                    class="btn-outline text-sm px-4 py-2">
                <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/>
                </svg>
                Filters
            </button>
        </div>

        {{-- Product grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @forelse ($products as $product)
                @include('catalog.partials.card', [
                    'product'     => $product,
                    'heroVariant' => $productCount >= 6 && $loop->first,
                ])
            @empty
                <div class="col-span-full rounded-xl border p-6 text-sm"
                     style="border-color:rgb(var(--color-surface-border)); background-color:rgb(var(--color-surface)); color:rgb(var(--color-text-muted));">
                    No lots match that search.
                </div>
            @endforelse
        </div>

        @if ($products->hasPages())
            <div class="mt-5">{{ $products->links() }}</div>
        @endif
    </div>

</div>
@endsection
