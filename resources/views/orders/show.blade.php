@extends('layouts.app')

@section('title', 'Order '.$order->number)

@section('content')
    <a href="{{ route('orders.index') }}" class="inline-block mb-3 text-sm text-accent hover:text-accent">← My Orders</a>
    <h1 class="text-lg font-semibold">Order {{ $order->number }}</h1>
    <p class="text-sm text-text-muted mt-1">{{ $order->status->label() }} · {{ $order->payment_method === \App\Enums\PaymentMethod::Cod ? 'Cash on delivery' : 'Demo prepaid payment' }}</p>
    @if($order->fulfillments->isNotEmpty())
        @include('orders.fulfillments')
    @else
    <ul class="mt-4 rounded-xl bg-surface border border-surface-border divide-y">
        @foreach ($order->items as $item)
            <li class="px-3 py-2 text-sm flex justify-between">
                <span>{{ $item->name }} × {{ $item->qty }}</span>
                <span>₱{{ number_format((float) $item->line_total, 2) }}</span>
            </li>
        @endforeach
    </ul>
    @endif
    <p class="mt-3 font-semibold">Original total ₱{{ number_format((float) $order->total, 2) }}</p>
    <p class="text-xs text-text-muted mt-2">Ship to {{ $order->ship_to }}</p>
@endsection
