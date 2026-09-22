@extends('layouts.app')

@section('title', 'Checkout · FarSell')

@section('content')
    <h1 class="text-lg font-semibold mb-3">Checkout</h1>
    <div class="grid md:grid-cols-2 gap-6">
        <form method="post" action="{{ route('checkout.store') }}" class="space-y-4 rounded-2xl bg-surface border border-surface-border p-4">
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
                <a href="{{ route('account.addresses.create') }}" class="inline-block mt-2 text-xs text-accent hover:text-accent font-medium">Add another address</a>
            </fieldset>

            <label class="block text-sm">Payment
                <select name="payment_method" class="mt-1 w-full rounded-lg border-surface-border text-sm focus:border-accent focus:ring-accent">
                    <option value="cod" @selected(old('payment_method') === 'cod')>Cash on delivery</option>
                    <option value="gateway_stub" @selected(old('payment_method') === 'gateway_stub')>Card / e-wallet (stub)</option>
                </select>
            </label>

            <p class="text-xs text-text-muted">Each shop confirms and ships its items separately. The delivery fee is split across shipments. Rejected items and their delivery allocation are removed from the amount due.</p>
            @if ($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-4">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif

            <button class="w-full rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm py-2.5 font-medium transition-colors">Place order</button>
        </form>

        <div>
            @foreach ($lines as $line)
                <div class="flex justify-between text-sm py-1">
                    <span>{{ $line->product->name }} × {{ $line->qty }}</span>
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
