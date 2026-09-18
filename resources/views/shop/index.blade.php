@extends('layouts.app')

@section('title', 'Shops · FarSell')

@section('content')
    <h1 class="text-2xl font-semibold">Shops</h1>
    <p class="mt-1 mb-5 text-sm text-text-muted">Explore our marketplace shops and their available products.</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($shops as $shop)
            <article class="fs-card p-5 flex flex-col">
                <h2 class="text-lg font-semibold break-words">
                    <a href="{{ route('shops.show', $shop) }}" class="text-accent hover:underline">{{ $shop->name }}</a>
                </h2>
                @if($shop->city)
                    <p class="mt-1 text-xs text-text-muted">{{ $shop->city }}</p>
                @endif
                <p class="mt-3 text-sm text-text-muted break-words">{{ $shop->tagline }}</p>
                <p class="mt-3 text-sm">{{ $shop->products_count }} {{ $shop->products_count === 1 ? 'product' : 'products' }}</p>
                <a href="{{ route('shops.show', $shop) }}" class="btn-outline mt-4 self-start" aria-label="Visit {{ $shop->name }}">Visit shop</a>
            </article>
        @empty
            <p class="fs-card p-6 text-text-muted sm:col-span-2 lg:col-span-3">No shops are available yet.</p>
        @endforelse
    </div>

    <div class="mt-5">{{ $shops->links() }}</div>
@endsection
