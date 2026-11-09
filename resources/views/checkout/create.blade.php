@extends('layouts.app')

@section('title', 'Checkout · FarSell')
@section('buyer-action-bar', 'true')

@section('content')
    <div class="mb-3 flex items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Checkout</h1>
        <x-bottom-actions buyer>
            <x-ui.button variant="primary" size="lg" type="submit" form="checkout-form" class="w-full md:w-auto">Place order</x-ui.button>
        </x-bottom-actions>
    </div>
    @if(session()->has(\App\Services\Orders\ReplacementService::SESSION_KEY))<p class="mb-4 rounded-xl border border-accent p-3 text-sm">Replacement order: review the current variant, item price and delivery charge below. Placing this order creates a new COD purchase linked to your returned order. No previous payment is being credited.</p>@endif
    <div class="grid md:grid-cols-2 gap-6">
        <form id="checkout-form" method="post" action="{{ route('checkout.store') }}" class="space-y-4 rounded-2xl bg-surface border border-surface-border p-4">
            @csrf
            <fieldset>
                <legend class="text-sm font-medium mb-2">Delivery address</legend>
                <div class="space-y-2">
                    @foreach ($addresses as $address)
                        <label class="flex gap-3 rounded-xl border border-surface-border p-3 text-sm cursor-pointer hover:border-accent transition-colors">
                            <input type="radio" name="address_id" value="{{ $address->id }}"
                                   class="text-accent focus:ring-accent"
                                   {{ (string) old('address_id', $addresses->first()->id) === (string) $address->id ? 'checked' : '' }} required>
                            <span>
                                <strong>{{ $address->label }}</strong>{{ $address->is_default ? ' · Default' : '' }}<br>
                                <span class="text-text-muted">{{ $address->formatted() }}</span>
                                @if ($address->phone)<br><span class="text-text-muted">{{ $address->phone }}</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-ui.button variant="link" :href="route('account.addresses.create')" class="mt-2">Add another address</x-ui.button>
            </fieldset>

            <label class="block text-sm">Payment
                <select name="payment_method" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent">
                    <option value="cod" @selected(old('payment_method') === 'cod')>Cash on delivery</option>
                    @if(config('release.allow_demo_payments'))
                        <option value="gateway_stub" @selected(old('payment_method') === 'gateway_stub')>Card / e-wallet (stub)</option>
                    @endif
                </select>
            </label>

            <p class="text-xs text-text-muted">Each shop confirms and ships its items separately. The delivery fee is split across shipments. Rejected items and their delivery allocation are removed from the amount due.</p>
            @if ($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-4">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif

        </form>

        <div>
            @foreach ($lines as $line)
                <div class="flex justify-between text-sm py-1">
                    <span>{{ $line->product->name }}@if($line->variant_options) ({{ collect($line->variant_options)->map(fn($value, $name) => $name.': '.$value)->join(', ') }})@endif × {{ $line->qty }}<span class="mt-0.5 block text-xs text-text-muted">SKU: {{ $line->variant?->sku ?? $line->product->sku ?? 'Unavailable' }}</span></span>
                    <span>₱{{ number_format($line->line_total, 2) }}</span>
                </div>
            @endforeach
            <div class="flex justify-between text-sm mt-2">
                <span>Shipping</span>
                <span>₱{{ number_format($shipping, 2) }}</span>
            </div>
            <div class="flex justify-between font-semibold mt-2">
                <span>Total</span>
                <span>₱{{ number_format($subtotal + $shipping, 2) }}</span>
            </div>
        </div>
    </div>
@endsection
