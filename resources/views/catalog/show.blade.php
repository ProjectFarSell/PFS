@extends('layouts.app')

@section('title', $product->name.' · FarSell')

@section('content')
    <div class="grid md:grid-cols-2 gap-6">
        <div class="aspect-square rounded-2xl bg-surface border border-surface-border flex items-center justify-center text-text-muted">
            {{ $product->category?->name }}
        </div>
        <div>
            <a href="{{ route('shops.show', $product->shop) }}" class="text-xs text-accent hover:text-accent font-medium">{{ $product->shop->name }}</a>
            <h1 class="text-xl font-semibold mt-1">{{ $product->name }}</h1>
            <p class="text-2xl font-semibold text-accent mt-2">{{ $product->formattedPrice() }}</p>
            @if ($product->compare_at_price)
                <p class="text-sm text-text-muted line-through">₱{{ number_format((float) $product->compare_at_price, 2) }}</p>
            @endif
            <p class="text-sm text-text-muted mt-4">{{ $product->description }}</p>
            <p class="text-xs text-text-muted mt-2">{{ $product->stock }} in stock</p>

            <form method="post" action="{{ route('cart.store') }}" class="mt-4 flex gap-2" x-data="{ qty: 1 }">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="number" name="qty" x-model="qty" min="1" max="99" class="w-20 rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent">
                <button class="rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-5 py-2 transition-colors">Add to cart</button>
            </form>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <h2 class="text-base font-semibold mt-8 mb-3">More in {{ $product->category?->name }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($related as $item)
                @include('catalog.partials.card', ['product' => $item])
            @endforeach
        </div>
    @endif
@endsection
