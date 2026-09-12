@extends('layouts.app')

@section('title', 'Checkout · FarSell')

@section('content')
    <h1 class="text-lg font-semibold mb-3">Checkout</h1>
    <div class="grid md:grid-cols-2 gap-6">
        <form method="post" action="{{ route('checkout.store') }}" class="space-y-4 rounded-2xl bg-white border border-stone-200 p-4">
            @csrf
            <fieldset>
                <legend class="text-sm font-medium mb-2">Delivery address</legend>
                <div class="space-y-2">
                    @foreach ($addresses as $address)
                        <label class="flex gap-3 rounded-xl border border-stone-200 p-3 text-sm cursor-pointer">
                            <input type="radio" name="address_id" value="{{ $address->id }}"
                                   {{ (string) old('address_id', $addresses->first()->id) === (string) $address->id ? 'checked' : '' }} required>
                            <span>
                                <strong>{{ $address->label }}</strong>{{ $address->is_default ? ' · Default' : '' }}<br>
                                <span class="text-stone-500">{{ $address->formatted() }}</span>
                                @if ($address->phone)<br><span class="text-stone-500">{{ $address->phone }}</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
                <a href="{{ route('account.addresses.create') }}" class="inline-block mt-2 text-xs text-orange-600">Add another address</a>
            </fieldset>

            <label class="block text-sm">Payment
                <select name="payment_method" class="mt-1 w-full rounded-lg border-stone-200 text-sm">
                    <option value="cod" @selected(old('payment_method') === 'cod')>Cash on delivery</option>
                    <option value="gateway_stub" @selected(old('payment_method') === 'gateway_stub')>Card / e-wallet (stub)</option>
                </select>
            </label>

            @if ($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-4">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif

            <button class="w-full rounded-full bg-orange-500 text-white text-sm py-2.5 font-medium">Place order</button>
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
