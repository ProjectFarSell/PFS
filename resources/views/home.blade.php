@extends('layouts.app')

@section('title', 'FarSell — Japan surplus marketplace')

@section('content')
    <section class="rounded-2xl bg-gradient-to-r from-orange-500 to-amber-500 text-white p-5 mb-4">
        <p class="text-xs uppercase tracking-wide text-orange-100">Doorzo-style lots · Shopee-fast checkout</p>
        <h1 class="text-2xl font-semibold mt-1">Auction surplus. Everyday prices.</h1>
        <p class="text-sm text-orange-50 mt-2 max-w-xl">Browse as a guest, check out in minutes, or apply as a rider and deliver FarSell orders in your city.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @guest
                <form method="post" action="{{ route('guest.start') }}">
                    @csrf
                    <button class="rounded-full bg-white text-orange-600 text-sm font-medium px-4 py-2">Continue as guest</button>
                </form>
                <a href="{{ route('register') }}" class="rounded-full border border-white/70 text-sm px-4 py-2">Create account</a>
            @endguest
            @auth
                <a href="{{ route('rider.register') }}" class="rounded-full bg-white text-orange-600 text-sm font-medium px-4 py-2">Become a rider</a>
            @endauth
        </div>
    </section>

    <div class="flex gap-3 overflow-x-auto pb-3 -mx-1">
        @foreach ($categories as $category)
            <a href="{{ route('catalog.index', ['category' => $category->id]) }}" class="shrink-0 w-16 text-center">
                <div class="h-14 w-14 mx-auto rounded-2xl bg-white border border-stone-200 flex items-center justify-center text-lg">{{ $category->icon }}</div>
                <p class="mt-1 text-[11px] text-stone-600 truncate">{{ $category->name }}</p>
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
        @foreach ($products as $product)
            @include('catalog.partials.card', ['product' => $product])
        @endforeach
    </div>
@endsection
