@extends(auth()->user()->role === \App\Enums\UserRole::Admin ? 'layouts.portal' : 'layouts.app')

@section('title', 'Order '.$order->number)

@section('content')
    @if(auth()->user()->role === \App\Enums\UserRole::Buyer)
        <a href="{{ route('orders.index') }}" class="inline-block mb-3 text-sm text-accent hover:text-accent">&larr; My Orders</a>
    @endif
    <h1 class="text-lg font-semibold">Order {{ $order->number }}</h1>
    <p class="text-sm text-text-muted mt-1">{{ $order->status->label() }} · {{ $order->payment_method === \App\Enums\PaymentMethod::Cod ? 'Cash on delivery' : 'Demo prepaid payment' }}</p>
    @if(auth()->user()->role === \App\Enums\UserRole::Buyer)
        <div class="mt-3 flex flex-wrap gap-2 print:hidden">
            <a href="{{ route('orders.print', ['order' => $order, 'document' => 'invoice']) }}" target="_blank" rel="noopener" class="btn-outline">Print invoice</a>
            <a href="{{ route('orders.print', ['order' => $order, 'document' => 'packing-slip']) }}" target="_blank" rel="noopener" class="btn-outline">Print packing slip</a>
        </div>
    @endif
    @if($order->fulfillments->isNotEmpty())
        @include('orders.fulfillments')
    @else
    <ul class="mt-4 rounded-xl bg-surface border border-surface-border divide-y">
        @foreach ($order->items as $item)
            <li class="px-3 py-2 text-sm flex justify-between">
                <span>{{ $item->name }}@if($item->variant_options) ({{ collect($item->variant_options)->map(fn($value, $name) => $name.': '.$value)->join(', ') }})@endif × {{ $item->qty }}<span class="mt-1 block text-xs text-text-muted">SKU: {{ $item->product_sku ?? 'Unavailable' }}</span></span>
                <span>₱{{ number_format((float) $item->line_total, 2) }}</span>
            </li>
        @endforeach
    </ul>
    @endif
    <p class="mt-3 font-semibold">Original total ₱{{ number_format((float) $order->total, 2) }}</p>
    <p class="text-xs text-text-muted mt-2">Ship to {{ $order->ship_to }}</p>
@endsection
