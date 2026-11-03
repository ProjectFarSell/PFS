@extends(auth()->user()->role === \App\Enums\UserRole::Admin ? 'layouts.portal' : 'layouts.app')

@section('title', 'Order '.$order->number)

@section('content')
    @if(auth()->user()->role === \App\Enums\UserRole::Buyer)
        <a href="{{ route('orders.index') }}" class="inline-block mb-3 text-sm text-accent hover:text-accent">&larr; My Orders</a>
    @endif
    <h1 class="text-lg font-semibold">Order {{ $order->number }}</h1>
    @if($errors->any())<div role="alert" class="my-4 rounded-xl border border-error p-4">{{ $errors->first() }}</div>@endif
    @if($order->cancelled_at)
        <p class="my-3 text-sm">Cancelled {{ $order->cancelled_at->format('M j, Y g:i A') }}: {{ $order->cancellation_reason }}</p>
    @elseif(auth()->user()->role === \App\Enums\UserRole::Buyer && $order->canBuyerCancel())
        <details class="my-4 rounded-xl border border-surface-border p-4 print:hidden">
            <summary class="cursor-pointer font-semibold">Cancel order</summary>
            <p class="my-2 text-sm">Cancel all remaining shipments before a rider is assigned. Reserved stock will be restored.</p>
            <form method="post" action="{{ route('orders.cancel', $order) }}" class="space-y-3">@csrf
                <label class="block text-sm">Reason<textarea name="reason" required minlength="5" maxlength="1000" class="mt-1 block w-full rounded-lg border border-surface-border bg-surface p-3">{{ old('reason') }}</textarea></label>
                <button class="btn-outline" type="submit">Confirm cancellation</button>
            </form>
        </details>
    @endif
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
    @include('orders.return-actions')
@endsection
