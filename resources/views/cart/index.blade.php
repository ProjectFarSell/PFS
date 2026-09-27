@extends('layouts.app')

@section('title', 'Cart · FarSell')

@section('content')
@if ($lines->isEmpty())
    {{-- ── Illustrated empty state (no h1 when empty, per design) ──────── --}}
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <svg xmlns="http://www.w3.org/2000/svg"
             class="mb-6"
             style="width:96px; height:96px; color:rgb(var(--color-text-muted));"
             fill="none" viewBox="0 0 96 96"
             stroke="currentColor" stroke-width="1.5"
             aria-hidden="true">
            <circle cx="34" cy="80" r="6"/>
            <circle cx="72" cy="80" r="6"/>
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M6 8h12l10 48h44l8-32H26"/>
        </svg>
        <p class="text-xl font-semibold" style="color:rgb(var(--color-text-base));">
            Your cart is empty
        </p>
        <p class="text-sm mt-2" style="color:rgb(var(--color-text-muted));">
            Looks like you haven't added anything yet.
        </p>
        <a href="{{ route('catalog.index') }}" class="btn-accent mt-6">
            Browse products
        </a>
    </div>

@else
    <h1 class="text-lg font-semibold mb-3" style="color:rgb(var(--color-text-base));">Cart</h1>

    <ul class="space-y-3">
        @foreach ($lines as $line)
        <li class="fs-card p-3 flex gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate" style="color:rgb(var(--color-text-base));">
                    {{ $line->product->name }}
                </p>
                <p class="text-xs mt-0.5" style="color:rgb(var(--color-text-muted));">
                    {{ $line->product->shop->name }}
                </p>
                <p class="text-sm font-semibold mt-1" style="color:rgb(var(--color-accent));">
                    ₱{{ number_format($line->line_total, 2) }}
                </p>
            </div>
            <form method="post" action="{{ route('cart.update', $line->product) }}"
                  class="flex flex-col items-end gap-1 shrink-0">
                @csrf
                @method('patch')
                <input type="number" name="qty" value="{{ $line->qty }}" min="0"
                       class="fs-input w-16 py-1.5 text-center">
                <button type="submit"
                        class="text-xs font-medium transition-colors hover:underline"
                        style="color:rgb(var(--color-text-muted));">
                    Update
                </button>
            </form>
        </li>
        @endforeach
    </ul>

    <div class="mt-5 fs-card p-4 flex items-center justify-between gap-4">
        <p class="font-semibold" style="color:rgb(var(--color-text-base));">
            Subtotal
            <span style="color:rgb(var(--color-accent));">₱{{ number_format($subtotal, 2) }}</span>
        </p>
        <a href="{{ route('checkout.create') }}" class="btn-accent">
            Checkout
        </a>
    </div>
@endif
@endsection
