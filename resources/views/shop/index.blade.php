@extends('layouts.app')

@section('title', 'Shops · FarSell')

@section('content')
@php
    // Banner gradient cycling — 6 distinct gradients keyed by ($shop->id % 6)
    $bannerGradients = [
        0 => 'from-violet-500 to-indigo-600',
        1 => 'from-emerald-500 to-teal-600',
        2 => 'from-orange-400 to-pink-600',
        3 => 'from-sky-500 to-blue-700',
        4 => 'from-rose-500 to-red-600',
        5 => 'from-amber-400 to-orange-500',
    ];
@endphp

<div class="mb-5">
    <h1 class="text-2xl font-semibold" style="color:rgb(var(--color-text-base));">Shops</h1>
    <p class="mt-1 text-sm" style="color:rgb(var(--color-text-muted));">
        Explore our marketplace shops and their available products.
    </p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    @forelse ($shops as $shop)
    @php $gradient = $bannerGradients[$shop->id % 6]; @endphp

    <article class="fs-card overflow-hidden flex flex-col hover:border-accent transition-colors">

        {{-- 80px gradient banner --}}
        <div class="h-20 bg-gradient-to-r {{ $gradient }} relative">
            {{-- 48px avatar overlapping the banner by ~24px --}}
            <div class="absolute -bottom-6 left-4 h-12 w-12 rounded-full flex items-center justify-center
                        text-lg font-black border-4 shadow-card"
                 style="background-color:rgb(var(--color-accent));
                        color:rgb(var(--color-accent-text));
                        border-color:rgb(var(--color-surface));">
                {{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}
            </div>
        </div>

        {{-- Card body — pt-8 clears the avatar overlap --}}
        <div class="pt-9 px-4 pb-4 flex flex-col flex-1">
            <h2 class="text-base font-semibold" style="color:rgb(var(--color-text-base));">
                {{ $shop->name }}
            </h2>
            @if ($shop->city)
            <p class="text-xs mt-0.5" style="color:rgb(var(--color-text-muted));">
                📍 {{ $shop->city }}
            </p>
            @endif
            @if ($shop->tagline)
            <p class="text-sm mt-2 line-clamp-2 flex-1" style="color:rgb(var(--color-text-muted));">
                {{ $shop->tagline }}
            </p>
            @endif
            <p class="text-xs mt-2" style="color:rgb(var(--color-text-muted));">
                {{ $shop->products_count }} {{ Str::plural('product', $shop->products_count) }}
            </p>
            <a href="{{ route('shops.show', $shop) }}"
               class="btn-accent mt-4 self-start"
               aria-label="Visit {{ $shop->name }}">
                Visit Store
            </a>
        </div>
    </article>

    @empty
    <div role="status"
         class="col-span-full fs-card p-8 text-center"
         style="color:rgb(var(--color-text-muted));">
        No shops are available yet. Check back soon.
    </div>
    @endforelse
</div>

@if ($shops->hasPages())
<div class="mt-6">{{ $shops->links() }}</div>
@endif
@endsection
