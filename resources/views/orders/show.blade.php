@extends('layouts.app')

@section('title', 'Order '.$order->number)

@section('content')
    @php
        $steps = [
            'pending_payment' => 'Placed',
            'paid' => 'Paid / COD confirmed',
            'packed' => 'Packed',
            'assigned' => 'Rider assigned',
            'in_transit' => 'Out for delivery',
            'delivered' => 'Delivered',
        ];
        $keys = array_keys($steps);
        $current = array_search($order->status->value, $keys, true);
        if ($current === false) {
            $current = 0;
        }
    @endphp
    <h1 class="text-lg font-semibold">Order {{ $order->number }}</h1>
    <p class="text-sm text-stone-500 mt-1">{{ $steps[$order->status->value] ?? $order->status->value }} · {{ strtoupper($order->payment_method->value) }}</p>
    <ol class="mt-4 space-y-2 text-sm">
        @foreach ($steps as $key => $label)
            @php $done = array_search($key, $keys, true) <= $current; @endphp
            <li class="flex items-center gap-2 {{ $done ? 'text-stone-900' : 'text-stone-400' }}">
                <span class="h-2 w-2 rounded-full {{ $done ? 'bg-orange-500' : 'bg-stone-300' }}"></span>
                {{ $label }}
            </li>
        @endforeach
    </ol>
    <ul class="mt-4 rounded-xl bg-white border border-stone-200 divide-y">
        @foreach ($order->items as $item)
            <li class="px-3 py-2 text-sm flex justify-between">
                <span>{{ $item->name }} × {{ $item->qty }}</span>
                <span>₱{{ number_format((float) $item->line_total, 2) }}</span>
            </li>
        @endforeach
    </ul>
    <p class="mt-3 font-semibold">Total ₱{{ number_format((float) $order->total, 2) }}</p>
    <p class="text-xs text-stone-500 mt-2">Ship to {{ $order->ship_to }}</p>
@endsection
