@extends('layouts.app')

@section('title', $shop->name.' · FarSell')

@section('content')
    <a href="{{ route('shops.index') }}" class="inline-block mb-3 text-sm text-accent hover:underline">← All shops</a>
    <div class="rounded-2xl bg-surface border border-surface-border p-4 mb-4">
        <h1 class="text-xl font-semibold">{{ $shop->name }}</h1>
        <p class="text-sm text-text-muted">{{ $shop->tagline }}</p>
        <p class="text-xs text-text-muted mt-1">{{ $shop->city }}</p>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @forelse ($products as $product)
            @include('catalog.partials.card', ['product' => $product])
        @empty
            <p class="text-sm text-text-muted col-span-2 sm:col-span-4">This shop has no products available yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
@endsection
