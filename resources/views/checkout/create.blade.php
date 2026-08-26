@extends('layouts.app')

@section('title', 'Checkout · FarSell')

@section('content')
    <h1 class="text-lg font-semibold mb-3">Checkout {{ $guest ? '(guest)' : '' }}</h1>
    <div class="grid md:grid-cols-2 gap-6">
        <form method="post" action="{{ route('checkout.store') }}" class="space-y-3 rounded-2xl bg-white border border-stone-200 p-4">
            @csrf
            @if ($guest)
                <label class="block text-sm">Name
                    <input name="guest_name" required class="mt-1 w-full rounded-lg border-stone-200 text-sm" value="{{ old('guest_name') }}">
                </label>
                <label class="block text-sm">Email
                    <input type="email" name="guest_email" required class="mt-1 w-full rounded-lg border-stone-200 text-sm" value="{{ old('guest_email') }}">
                </label>
            @endif
            <label class="block text-sm">Phone
                <input name="phone" required class="mt-1 w-full rounded-lg border-stone-200 text-sm" value="{{ old('phone') }}">
            </label>
            <label class="block text-sm">Ship to
                <textarea name="ship_to" required rows="3" class="mt-1 w-full rounded-lg border-stone-200 text-sm">{{ old('ship_to') }}</textarea>
            </label>
            <label class="block text-sm">Payment
                <select name="payment_method" class="mt-1 w-full rounded-lg border-stone-200 text-sm">
                    <option value="cod">Cash on delivery</option>
                    <option value="gateway_stub">Card / e-wallet (stub)</option>
                </select>
            </label>
            @if ($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
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
