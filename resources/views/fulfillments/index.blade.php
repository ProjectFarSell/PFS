@extends('layouts.app')
@section('title', 'Order fulfillment · FarSell')
@section('content')
    @php
        $isSeller = $role === \App\Enums\UserRole::Seller;
        $isAdmin = $role === \App\Enums\UserRole::Admin;
    @endphp
    <a class="text-sm text-accent underline" href="{{ route($isSeller ? 'seller.dashboard' : ($isAdmin ? 'admin.dashboard' : 'rider.dashboard')) }}">Back to dashboard</a>
    <section class="fs-card-raised p-5 mt-4">
        <h1 class="text-2xl font-semibold">{{ $isSeller ? 'Manage shop orders' : ($isAdmin ? 'Dispatch shipments' : 'Your deliveries') }}</h1>
        <p class="mt-2 text-sm text-text-muted">{{ $isSeller ? 'Confirm incoming orders, prepare items, and mark them ready for pickup.' : ($isAdmin ? 'Assign an approved rider to each shipment that is ready for pickup.' : 'Record pickup, then confirm delivery and any cash collected.') }}</p>
        <form method="get" class="mt-4 flex flex-wrap gap-3 items-center">
            <label for="status">Shipment status</label>
            <select id="status" name="status" class="rounded-lg">
                <option value="">All</option>
                @foreach(\App\Enums\FulfillmentStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <button class="btn-accent">Filter</button>
        </form>
    </section>
    @if($errors->any())
        <div role="alert" class="fs-card p-4 mt-4 text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    <div class="mt-5 space-y-4">
        @forelse($fulfillments as $part)
            <article class="fs-card p-5">
                <div class="flex flex-wrap gap-3 justify-between items-center">
                    <h2 class="font-semibold">{{ $part->order->number }} · Shipment #{{ $part->id }}</h2>
                    <span class="badge badge-neutral">{{ $part->status->label() }}</span>
                </div>
                <p class="mt-2 text-sm">{{ $part->shop_name }}</p>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach($part->items as $item)<li>{{ $item->name }} × {{ $item->qty }} @if($isSeller || $isAdmin) · ₱{{ number_format((float) $item->line_total, 2) }} @endif</li>@endforeach
                </ul>
                @if(!$isSeller)
                    <p class="mt-3 text-sm font-semibold">Recipient: {{ $part->order->guest_name }}</p>
                    <p class="text-sm whitespace-pre-line">{{ $part->order->ship_to }}</p>
                    <p class="mt-2 text-sm">Pickup shop: {{ $part->shop_name }}{{ $part->shop ? ' · '.$part->shop->city : '' }}</p>
                    <p class="text-sm whitespace-pre-line">{{ $part->pickup_address ?? 'Legacy shipment: ask the administrator to confirm the pickup point.' }}</p>
                    @if($part->pickup_contact)<p class="text-sm">Pickup contact: {{ $part->pickup_contact }}</p>@endif
                    <p class="mt-2 font-semibold">{{ $part->order->payment_method === \App\Enums\PaymentMethod::Cod ? 'COD for this shipment' : 'Demo prepaid shipment' }}: ₱{{ number_format($part->amount(), 2) }}</p>
                    <p class="text-xs text-text-muted">Includes ₱{{ number_format((float) $part->shipping_fee, 2) }} allocated delivery fee.</p>
                @else
                    <p class="mt-2 text-sm">Item subtotal: ₱{{ number_format((float) $part->subtotal, 2) }}</p>
                @endif
                @if($part->rider)<p class="mt-2 text-sm">Rider: {{ $part->rider->user?->name ?? 'Unavailable account' }}</p>@endif
                @if($part->rejection_reason)<p class="mt-2 text-sm text-error">Rejection reason: {{ $part->rejection_reason }}</p>@endif
                @if($isSeller && $part->status === \App\Enums\FulfillmentStatus::Pending)
                    <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4">@csrf <button name="action" value="accept" class="btn-accent">Accept order</button></form>
                    <details class="mt-3 text-sm"><summary class="cursor-pointer text-error">Reject these items</summary>
                        <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-3 space-y-2">
                            @csrf <input type="hidden" name="action" value="reject">
                            <label for="reason-{{ $part->id }}" class="block">Reason shown to buyer</label>
                            <textarea id="reason-{{ $part->id }}" name="reason" required maxlength="1000" class="rounded-lg w-full"></textarea>
                            <p class="text-text-muted">Rejects this shop's items and releases their reserved stock. This cannot be undone.</p>
                            <button class="btn-outline">Confirm rejection</button>
                        </form>
                    </details>
                @elseif($isSeller && ($part->status === \App\Enums\FulfillmentStatus::Accepted || ($part->status === \App\Enums\FulfillmentStatus::Ready && $part->pickup_city === null)))
                    <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4 space-y-3">
                        @csrf <input type="hidden" name="action" value="{{ $part->status === \App\Enums\FulfillmentStatus::Ready ? 'request_rider' : 'ready' }}">
                        <label class="block" for="pickup-address-{{ $part->id }}">Exact pickup address for the rider</label>
                        <textarea id="pickup-address-{{ $part->id }}" name="pickup_address" required maxlength="1000" class="w-full rounded-lg"></textarea>
                        <label class="block" for="pickup-contact-{{ $part->id }}">Pickup contact name and phone</label>
                        <input id="pickup-contact-{{ $part->id }}" name="pickup_contact" required maxlength="255" class="w-full rounded-lg">
                        <button class="btn-accent">{{ $part->status === \App\Enums\FulfillmentStatus::Ready ? 'Request a rider' : 'Mark ready for pickup & request rider' }}</button>
                        <p class="text-xs text-text-muted">Available approved riders in {{ $part->shop?->city }} will receive an in-app delivery request when you mark this ready.</p>
                    </form>
                @elseif($isSeller && $part->status === \App\Enums\FulfillmentStatus::Ready)
                    <p class="mt-4 text-sm font-semibold">Rider requested — waiting for acceptance</p>
                    <p class="mt-2 text-sm text-text-muted">Available approved riders in {{ ucwords($part->pickup_city) }} can accept this shipment. An administrator can also assign a rider.</p>
                @elseif($isAdmin && $part->status === \App\Enums\FulfillmentStatus::Ready)
                    @if($riders->isEmpty())
                        <p class="mt-4 text-sm">No approved riders available. <a class="text-accent underline" href="{{ route('admin.riders.index') }}">Review rider applications</a></p>
                    @else
                        <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4 flex flex-wrap gap-3 items-center">
                            @csrf <input type="hidden" name="action" value="assign">
                            <label for="rider-{{ $part->id }}">Assign rider</label>
                            <select id="rider-{{ $part->id }}" name="rider_id" required class="rounded-lg">
                                <option value="">Choose rider</option>
                                @foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->user->name }} · {{ $rider->city }} · {{ $rider->vehicle_type }}</option>@endforeach
                            </select><button class="btn-accent">Assign shipment</button>
                        </form>
                    @endif
                @elseif($role === \App\Enums\UserRole::Rider && $part->status === \App\Enums\FulfillmentStatus::Assigned)
                    <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4">@csrf <button name="action" value="pickup" class="btn-accent">Confirm pickup / Out for delivery</button></form>
                @elseif($role === \App\Enums\UserRole::Rider && $part->status === \App\Enums\FulfillmentStatus::InTransit)
                    <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-4 space-y-3">
                        @csrf <input type="hidden" name="action" value="deliver">
                        <label class="block" for="delivery-note-{{ $part->id }}">Delivery confirmation (recipient name and handover details)</label>
                        <textarea id="delivery-note-{{ $part->id }}" name="delivery_note" required maxlength="1000" class="w-full rounded-lg"></textarea>
                        @if($part->order->payment_method === \App\Enums\PaymentMethod::Cod)
                            <label class="block"><input type="checkbox" name="cod_collected" value="1" required> I collected ₱{{ number_format($part->amount(), 2) }} in cash.</label>
                        @endif
                        <button class="btn-accent">Confirm delivered</button>
                    </form>
                @endif
                @if($isAdmin)<a class="inline-block mt-4 text-sm text-accent underline" href="{{ route('orders.show', $part->order_id) }}">Order details and history</a>@endif
            </article>
        @empty
            <p class="fs-card p-6 text-text-muted">No shipments match this view.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $fulfillments->links() }}</div>
@endsection
