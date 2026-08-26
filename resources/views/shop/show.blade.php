@extends('layouts.app')

@section('title', $shop->name.' · FarSell')

@section('content')
    <div class="rounded-2xl bg-white border border-stone-200 p-4 mb-4">
        <h1 class="text-xl font-semibold">{{ $shop->name }}</h1>
        <p class="text-sm text-stone-600">{{ $shop->tagline }}</p>
        <p class="text-xs text-stone-500 mt-1">{{ $shop->city }}</p>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach ($products as $product)
            @include('catalog.partials.card', ['product' => $product])
        @endforeach
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
@endsection
