@extends('layouts.app')

@section('title', 'Cart · FarSell')

@section('content')
    <h1 class="text-lg font-semibold mb-3">Cart</h1>
    @if ($lines->isEmpty())
        <p class="text-sm text-stone-500">Your cart is empty. <a class="text-orange-600" href="{{ route('home') }}">Browse lots</a></p>
    @else
        <ul class="space-y-3">
            @foreach ($lines as $line)
                <li class="rounded-xl bg-white border border-stone-200 p-3 flex gap-3">
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ $line->product->name }}</p>
                        <p class="text-xs text-stone-500">{{ $line->product->shop->name }}</p>
                        <p class="text-sm text-orange-600 mt-1">₱{{ number_format($line->line_total, 2) }}</p>
                    </div>
                    <form method="post" action="{{ route('cart.update', $line->product) }}">
                        @csrf
                        @method('patch')
                        <input type="number" name="qty" value="{{ $line->qty }}" min="0" class="w-16 rounded-lg border-stone-200 text-sm">
                        <button class="text-xs text-stone-500 mt-1 block">Update</button>
                    </form>
                </li>
            @endforeach
        </ul>
        <div class="mt-4 flex items-center justify-between">
            <p class="font-semibold">Subtotal ₱{{ number_format($subtotal, 2) }}</p>
            <a href="{{ route('checkout.create') }}" class="rounded-full bg-orange-500 text-white text-sm px-5 py-2">Checkout</a>
        </div>
    @endif
@endsection
