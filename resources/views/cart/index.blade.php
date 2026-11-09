@extends('layouts.app')

@section('title', 'Cart · FarSell')
@if ($lines->isNotEmpty())
    @section('buyer-action-bar', 'true')
@endif

@section('content')
@if ($lines->isEmpty())
    {{-- ── Illustrated empty state (no h1 when empty, per design) ──────── --}}
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <span class="mb-6 grid size-24 place-items-center rounded-full bg-surface-muted text-text-muted"><x-ui.icon name="cart" size="lg" /></span>
        <p class="text-xl font-semibold" style="color:rgb(var(--color-text-base));">
            Your cart is empty
        </p>
        <p class="text-sm mt-2" style="color:rgb(var(--color-text-muted));">
            Looks like you haven't added anything yet.
        </p>
        <x-ui.button variant="primary" size="lg" :href="route('catalog.index')" class="mt-6">
            Browse products
        </x-ui.button>
    </div>

@else
    <div class="mb-3 flex items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Cart</h1>
        <x-bottom-actions buyer>
            <p class="mr-auto text-sm font-semibold">Subtotal <span class="text-accent">₱{{ number_format($subtotal, 2) }}</span></p>
            <x-ui.button variant="primary" size="lg" :href="route('checkout.create')">Checkout</x-ui.button>
        </x-bottom-actions>
    </div>

    <ul class="space-y-3">
        @foreach ($lines as $line)
        <li class="fs-card flex flex-wrap gap-3 p-3">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate" style="color:rgb(var(--color-text-base));">
                    {{ $line->product->name }}
                </p>
                <p class="text-xs mt-0.5" style="color:rgb(var(--color-text-muted));">
                    {{ $line->product->shop->name }}
                </p>
                <p class="text-xs mt-0.5 text-text-muted">SKU: {{ $line->variant?->sku ?? $line->product->sku ?? 'Unavailable' }}</p>
                @if($line->stale_variant)<p class="mt-1 text-xs text-error">This product's options changed. Remove it and choose an available option again.</p>@endif
                @if($line->variant_options)<p class="text-xs mt-0.5 text-text-muted">{{ collect($line->variant_options)->map(fn($value, $name) => $name.': '.$value)->join(', ') }}</p>@endif
                <p class="text-sm font-semibold mt-1" style="color:rgb(var(--color-accent));">
                    ₱{{ number_format($line->line_total, 2) }}
                </p>
            </div>
            <form method="post" action="{{ route('cart.update', $line->product) }}"
                  class="ml-auto flex shrink-0 flex-col items-end gap-2" x-data="quantityStepper({{ $line->qty }}, {{ min(99, $line->variant?->stock ?? $line->product->stock) }}, 0)">
                @csrf
                @method('patch')
                @if($line->variant)<input type="hidden" name="variant_id" value="{{ $line->variant->id }}">@endif
                <div class="inline-flex items-center gap-2" role="group" aria-label="Quantity">
                    <x-ui.icon-button variant="secondary" label="Decrease quantity" icon="subtract" type="button" @click="decrease()" x-bind:disabled="qty <= 0" class="disabled:opacity-40"></x-ui.icon-button>
                    <input type="number" name="qty" aria-label="Quantity" x-model.number="qty" min="0" x-bind:max="max"
                           class="fs-input min-h-target w-14 p-0 text-center text-sm md:min-h-target-desktop">
                    <x-ui.icon-button variant="secondary" label="Increase quantity" icon="add" type="button" @click="increase()" x-bind:disabled="qty >= max" class="disabled:opacity-40"></x-ui.icon-button>
                </div>
                <x-ui.button variant="tertiary" type="submit"
                       >
                    Update
                </x-ui.button>
            </form>
        </li>
        @endforeach
    </ul>

@endif
@endsection
