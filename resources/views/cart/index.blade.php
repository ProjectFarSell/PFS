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
                @if($line->stale_variant)<p class="mt-1 text-xs text-error">This product's options changed. Remove it and choose an available option again.</p>@endif
                @if($line->variant_options)<p class="text-xs mt-0.5 text-text-muted">{{ collect($line->variant_options)->map(fn($value, $name) => $name.': '.$value)->join(', ') }}</p>@endif
                <p class="text-sm font-semibold mt-1" style="color:rgb(var(--color-accent));">
                    ₱{{ number_format($line->line_total, 2) }}
                </p>
            </div>
            <form method="post" action="{{ route('cart.update', $line->product) }}"
                  class="flex flex-col items-end gap-1 shrink-0" x-data="quantityStepper({{ $line->qty }}, {{ min(99, $line->variant?->stock ?? $line->product->stock) }}, 0)">
                @csrf
                @method('patch')
                @if($line->variant)<input type="hidden" name="variant_id" value="{{ $line->variant->id }}">@endif
                <div class="inline-flex h-9 overflow-hidden rounded-md border border-surface-border" role="group" aria-label="Quantity">
                    <button type="button" @click="decrease()" x-bind:disabled="qty <= 0" class="w-9 border-r border-surface-border text-text-muted hover:bg-surface-muted disabled:opacity-40" aria-label="Decrease quantity">−</button>
                    <input type="number" name="qty" x-model.number="qty" min="0" x-bind:max="max"
                           class="h-full w-14 border-0 bg-transparent p-0 text-center text-sm focus:ring-0">
                    <button type="button" @click="increase()" x-bind:disabled="qty >= max" class="w-9 border-l border-surface-border text-text-muted hover:bg-surface-muted disabled:opacity-40" aria-label="Increase quantity">+</button>
                </div>
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
