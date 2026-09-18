@extends('layouts.app')

@section('title', 'Search · FarSell')

@section('content')
    <form method="get" class="flex gap-2 mb-4">
        <input type="search" name="q" value="{{ $q }}" class="flex-1 rounded-full border-surface-border text-sm focus:border-accent focus:ring-accent" placeholder="Search products">
        <select name="category" class="rounded-full border-surface-border text-sm focus:border-accent focus:ring-accent">
            <option value="">All</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($activeCategory === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <button class="rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm px-4 transition-colors">Go</button>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @forelse ($products as $product)
            @include('catalog.partials.card', ['product' => $product])
        @empty
            <p class="col-span-2 text-sm text-text-muted">No lots match that search.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
