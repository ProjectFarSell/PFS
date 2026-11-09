@extends('layouts.portal')
@section('title', 'Logistics - FarSell')
@section('content')
<div class="space-y-5">
    <h1 class="text-2xl font-bold">Logistics</h1>
    <p class="text-sm text-text-muted">One delivery method per shipment. FarSell riders use internal dispatch; Lalamove bookings can be recorded after arranging them with the courier. External shipments do not credit FarSell rider earnings.</p>
    <div class="fs-card space-y-2 p-4 text-sm">
        <p>Lalamove API: <strong>{{ $apiConfigured ? ucfirst($apiEnvironment).' configured' : 'Not configured — manual booking available' }}</strong>. Sandbox bookings do not dispatch live couriers or record real cash settlements. Live COD API booking is held until the provider’s Philippine cash-handling requirements are confirmed.</p>
    </div>
    @if($errors->any())<div role="alert" class="fs-card p-4 text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <details class="rounded-xl border border-surface-border px-3">
        <summary class="cursor-pointer py-3 font-semibold">Filters</summary>
        <form method="get" class="flex flex-wrap items-end gap-3 pb-3">
        <label class="text-sm">Order number<input name="q" value="{{ request('q') }}" maxlength="60" class="mt-1 block rounded-lg"></label>
        <label class="text-sm">Method<select name="method" class="mt-1 block rounded-lg"><option value="">All methods</option>@foreach(\App\Enums\DeliveryMethod::cases() as $method)<option value="{{ $method->value }}" @selected(request('method') === $method->value)>{{ $method->label() }}</option>@endforeach</select></label>
        <label class="text-sm">Status<select name="status" class="mt-1 block rounded-lg"><option value="">All statuses</option>@foreach(\App\Enums\FulfillmentStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
        <x-ui.button variant="primary" size="lg" type="submit">Apply filters</x-ui.button>
        </form>
    </details>
    @php
        $activeFilters = array_filter(request()->only('q', 'method', 'status'), fn ($value) => $value !== null && $value !== '');
        $filterLabels = ['q' => 'Order', 'method' => 'Method', 'status' => 'Status'];
    @endphp
    @if($activeFilters)
        <div class="flex flex-wrap gap-2" aria-label="Active filters">
            @foreach($activeFilters as $key => $value)
                <x-ui.button variant="link" :href="route('admin.logistics.index', \Illuminate\Support\Arr::except($activeFilters, [$key]))" class="bg-surface-muted" aria-label="Remove {{ strtolower($filterLabels[$key]) }} filter">{{ $filterLabels[$key] }}: {{ str_replace('_', ' ', $value) }} <x-ui.icon name="close" size="sm" class="ml-2" /></x-ui.button>
            @endforeach
        </div>
    @endif
    @forelse($shipments as $part)
    @php
        $booking = $part->courierBooking;
        $canSelect = in_array($part->status->value, ['accepted', 'ready']) && !$part->rider_id && (!$booking || in_array($booking->status, ['draft', 'quoted']));
        $stale = $booking?->status === 'submitting' && $booking->updated_at->lte(now()->subMinutes(2));
        $canRecord = $part->usesExternalCourier() && $part->status->value === 'ready' && (!$booking || in_array($booking->status, ['draft', 'quoted', 'unknown']) || $stale);
    @endphp
    <article class="fs-card space-y-4 p-5">
        <div class="flex flex-wrap justify-between gap-3"><h2 class="font-semibold">{{ $part->order->number }} · {{ $part->shop_name }} · Shipment #{{ $part->id }}</h2><x-ui.status-pill :status="$part->status->value">{{ $part->deliveryLabel() }}</x-ui.status-pill></div>
        <p class="text-sm"><strong>{{ $part->delivery_method->label() }}</strong> · Buyer-agreed delivery allocation: PHP {{ number_format((float) $part->shipping_fee, 2) }} · Shipment amount: PHP {{ number_format($part->amount(), 2) }}</p>
        <p class="whitespace-pre-line text-sm">Pickup: {{ $part->pickup_address ?? 'Seller must mark ready and provide pickup details.' }}@if($part->pickup_contact) · {{ $part->pickup_contact }}@endif</p>
        <p class="whitespace-pre-line text-sm">Recipient: {{ $part->order->guest_name }} · {{ $part->order->ship_to }}</p>
        <p class="text-sm">@foreach($part->items as $item){{ $item->name }} ({{ $item->product_sku }}) × {{ $item->qty }}{{ $loop->last ? '' : '; ' }}@endforeach</p>
        @if($booking)
        <div class="rounded-xl border border-surface-border p-3 text-sm space-y-2">
            <p>Booking record: <strong>{{ $booking->status }}</strong> · {{ $booking->mode === 'api' ? strtoupper($booking->api_environment).' API' : 'Manually verified' }}</p>
            @if($booking->isSandbox())<p class="font-semibold text-error">Sandbox simulation: no real courier dispatch, COD collection or financial settlement is implied.</p>@endif
            @if($booking->reference)<p>Courier reference: <strong>{{ $booking->reference }}</strong></p>@endif
            @if($booking->cost_cents !== null)<p>Courier cost: PHP {{ number_format($booking->cost_cents / 100, 2) }}. This does not change the buyer’s agreed charge.</p>@endif
            @if($booking->provider_status)<p>Provider status: {{ $booking->provider_status }}@if($booking->last_synced_at) · Checked {{ $booking->last_synced_at->format('M j, Y g:i A') }}@endif</p>@endif
            @if(in_array($booking->status, ['unknown', 'submitting']))<p class="font-semibold">Check the courier portal before another action. An uncertain booking must not be submitted again. Reconcile its existing reference, or verify cancellation/non-creation.</p>@endif
            @if($booking->status === 'cancelled')<p>Courier cancellation was verified; the purchase remains unchanged. The buyer can cancel before pickup and place a new order if a fresh booking is needed.</p>@endif
        </div>
        @endif
        @if($canSelect)
        <form method="post" action="{{ route('admin.logistics.update', $part) }}" class="flex flex-wrap items-end gap-3">@csrf<input type="hidden" name="action" value="select">
            <label class="text-sm">Delivery method<select name="delivery_method" class="mt-1 block rounded-lg">@foreach(\App\Enums\DeliveryMethod::cases() as $method)<option value="{{ $method->value }}" @selected($part->delivery_method === $method)>{{ $method->label() }}</option>@endforeach</select></label>
            <x-ui.button variant="secondary" type="submit">Set delivery method</x-ui.button>
        </form>
        @endif
        @if(!$part->usesExternalCourier())
            <x-ui.button variant="link" :href="route('fulfillments.index')" class="inline-block text-sm text-accent underline">Open FarSell rider dispatch controls</x-ui.button>
        @elseif($canRecord)
        <details class="rounded-xl border border-surface-border p-4"><summary class="cursor-pointer font-semibold">Record / reconcile an existing courier booking</summary>
            <form method="post" action="{{ route('admin.logistics.update', $part) }}" class="mt-3 space-y-3">@csrf<input type="hidden" name="action" value="manual">
                <p class="text-xs text-text-muted">Book with the provider outside FarSell first, then verify service area, parcel and COD acceptance. Saving this form does not place a courier order.</p>
                <label class="block text-sm">Courier reference<input name="reference" minlength="3" maxlength="100" required class="mt-1 block w-full rounded-lg"></label>
                <label class="block text-sm">Actual / confirmed courier cost (PHP)<input name="courier_cost" type="number" min="0" max="99999999.99" step="0.01" required class="mt-1 block w-full rounded-lg"></label>
                <label class="block text-sm">Official HTTPS tracking link (optional)<input name="tracking_url" type="url" maxlength="2000" class="mt-1 block w-full rounded-lg"></label>
                <label class="block text-sm">Verification note<textarea name="note" minlength="10" maxlength="1000" required class="mt-1 block w-full rounded-lg"></textarea></label>
                <label class="block text-sm"><input type="checkbox" name="verified" value="1" required> I verified this existing booking/reference with the courier</label>
                @if($part->order->payment_method->value === 'cod' && !($booking?->isSandbox() && ($booking->status === 'unknown' || $stale)))<label class="block text-sm"><input type="checkbox" name="cod_supported" value="1" required> The courier confirmed it accepts the exact PHP {{ number_format($part->amount(), 2) }} COD for this shipment</label>@endif
                <x-ui.button variant="primary" size="lg" type="submit">Save verified booking record</x-ui.button>
            </form>
        </details>
        @endif
        @if($booking?->status === 'quoted' && $booking->mode === 'api')
            <p class="text-xs">Quote valid until {{ $booking->quote_expires_at?->format('M j, Y g:i A T') }}.</p>
        @endif
        @if($booking?->tracking_url)
            <x-ui.button variant="secondary" :href="$booking->tracking_url" target="_blank" rel="noopener noreferrer">Open official tracking link</x-ui.button>
        @endif
        @if($booking?->status === 'booked' && in_array($part->status->value, ['assigned', 'in_transit']))
            <form method="post" action="{{ route('fulfillments.update', $part) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl border border-surface-border p-4">@csrf
                <input type="hidden" name="action" value="{{ $part->status->value === 'assigned' ? 'pickup' : 'deliver' }}">
                <label class="block text-sm"><input type="checkbox" name="courier_verified" value="1" required> I verified this pickup/delivery event with the courier</label>
                @if($part->status->value === 'in_transit')
                    <label class="block text-sm">Recipient name<input name="delivery_recipient_name" required maxlength="120" class="mt-1 block w-full rounded-lg"></label>
                    <label class="block text-sm">Handover / provider evidence reference<textarea name="delivery_note" minlength="10" maxlength="1000" required class="mt-1 block w-full rounded-lg"></textarea></label>
                    <label class="block text-sm">Verified proof photo (optional)<input type="file" name="delivery_photo" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-lg"></label>
                    @if($part->order->payment_method->value === 'cod' && !$booking->isSandbox())<label class="block text-sm"><input type="checkbox" name="cod_collected" value="1" required> I verified the courier collected exactly PHP {{ number_format($part->amount(), 2) }} COD. This is not a bank remittance confirmation.</label>@endif
                @endif
                <x-ui.button variant="primary" size="lg" type="submit">{{ $part->status->value === 'assigned' ? 'Record verified courier pickup' : 'Record verified delivery — await buyer confirmation' }}</x-ui.button>
            </form>
        @endif
        <x-action-menu :inline="true" :context="'actions for shipment '.$part->id">
        <p class="px-4 text-xs font-semibold text-text-muted">Shipment and courier</p>
        @if($booking?->status === 'booked' && $booking->mode === 'api')<form method="post" action="{{ route('admin.logistics.update', $part) }}">@csrf<x-ui.button variant="tertiary" type="submit" name="action" value="sync" class="dropdown-item text-left justify-start">Check provider status</x-ui.button><p class="px-4 text-xs text-text-muted">Reading tracking status does not verify COD or complete the buyer’s order.</p></form>@endif
        <a href="{{ route('fulfillments.index', ['status' => $part->status->value]) }}" class="dropdown-item">Shipment / failed-delivery and return controls</a>
        <a href="{{ route('orders.show', $part->order_id) }}" class="dropdown-item">Buyer order and history</a>
        @if($canRecord && $part->delivery_method === \App\Enums\DeliveryMethod::Lalamove && (!$booking || in_array($booking->status, ['draft', 'quoted'])))
        <details class="rounded-xl border border-surface-border p-4"><summary class="cursor-pointer font-semibold">Lalamove API quotation (optional setup)</summary>
            <form method="post" action="{{ route('admin.logistics.update', $part) }}" class="mt-3 space-y-3">@csrf<input type="hidden" name="action" value="quote">
            <fieldset @disabled(!$apiConfigured) class="space-y-3">
                <p class="text-xs">Use verified addresses and map coordinates. Credentials stay in the backend environment. Quotes expire after five minutes. Current checkout charges are not repriced.</p>
                <label class="block text-sm">Provider service code<input name="service_type" value="MOTORCYCLE" required class="mt-1 block w-full rounded-lg"></label>
                @foreach(['pickup' => 'Pickup', 'dropoff' => 'Drop-off'] as $prefix => $label)
                    <label class="block text-sm">{{ $label }} address<textarea name="{{ $prefix }}_address" required minlength="5" maxlength="1000" class="mt-1 block w-full rounded-lg">{{ $prefix === 'pickup' ? $part->pickup_address : $part->order->ship_to }}</textarea></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm">{{ $label }} latitude<input name="{{ $prefix }}_lat" type="number" min="-90" max="90" step="any" required class="mt-1 block w-full rounded-lg"></label><label class="text-sm">{{ $label }} longitude<input name="{{ $prefix }}_lng" type="number" min="-180" max="180" step="any" required class="mt-1 block w-full rounded-lg"></label></div>
                @endforeach
                @foreach(['sender' => 'Pickup contact', 'recipient' => 'Recipient'] as $prefix => $label)
                    <label class="block text-sm">{{ $label }} name<input name="{{ $prefix }}_name" required maxlength="120" value="{{ $prefix === 'recipient' ? $part->order->guest_name : '' }}" class="mt-1 block w-full rounded-lg"></label>
                    <label class="block text-sm">{{ $label }} phone (+63…)<input name="{{ $prefix }}_phone" required placeholder="+639171234567" class="mt-1 block w-full rounded-lg"></label>
                @endforeach
                <x-ui.button variant="secondary" type="submit">Request quotation only</x-ui.button>
            </fieldset></form>
        </details>
        @endif
        @if($booking?->status === 'quoted' && $booking->mode === 'api')
            @if($apiConfigured && $apiEnvironment === 'sandbox' && $booking->api_environment === $apiEnvironment && $booking->quote_expires_at?->isFuture())<form method="post" action="{{ route('admin.logistics.update', $part) }}" class="space-y-2">@csrf<input type="hidden" name="action" value="book"><label class="block text-sm"><input type="checkbox" name="confirmed_booking" value="1" required> Create a sandbox test booking; no live delivery</label><x-ui.button variant="secondary" type="submit">Create sandbox booking</x-ui.button></form>@endif
        @endif
        @if($booking?->status === 'booked' && $part->status->value === 'in_transit')
            <details class="rounded-xl border border-surface-border p-4"><summary class="cursor-pointer font-semibold">Record courier delivery failure</summary><form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-3 space-y-3">@csrf<input type="hidden" name="action" value="fail">
                <label class="block text-sm">Verified contact attempt<select name="contact_method" required class="mt-1 block w-full rounded-lg"><option value="phone">Phone call</option><option value="chat">Chat</option><option value="doorstep">At delivery address</option></select></label>
                <label class="block text-sm">Reason<select name="failure_reason" required class="mt-1 block w-full rounded-lg">@foreach(\App\Services\Orders\DeliveryExceptionService::REASONS as $reason)<option value="{{ $reason }}">{{ str_replace('_', ' ', $reason) }}</option>@endforeach</select></label>
                <label class="block text-sm">Courier/contact attempt details<textarea name="exception_note" minlength="10" maxlength="1000" required class="mt-1 block w-full rounded-lg"></textarea></label>
                <label class="block text-sm"><input type="checkbox" name="courier_verified" value="1" required> I verified this failed attempt with the courier</label><x-ui.button variant="secondary" type="submit">Record unsuccessful attempt</x-ui.button>
            </form></details>
        @endif
        @if($booking && (in_array($booking->status, ['booked', 'unknown'], true) || $stale) && in_array($part->status->value, ['assigned', 'ready']))
            <details class="border-t border-surface-border px-4 pt-2"><summary class="cursor-pointer text-sm font-semibold text-red-700 dark:text-red-300">Record confirmed courier cancellation / non-creation</summary><form method="post" action="{{ route('admin.logistics.update', $part) }}" class="mt-3 space-y-3">@csrf<input type="hidden" name="action" value="cancel_record"><label class="block text-sm">Provider confirmation reference and note<textarea name="note" required minlength="10" maxlength="1000" class="mt-1 block w-full rounded-lg"></textarea></label><label class="block text-sm"><input type="checkbox" name="verified_cancelled" value="1" required> I verified the booking was cancelled or never created with the provider. This form does not cancel a courier order remotely.</label><x-ui.button variant="danger" type="submit" class="text-red-700 dark:text-red-300">Save cancellation verification</x-ui.button></form></details>
        @endif
        </x-action-menu>
    </article>
    @empty<p class="fs-card p-5">No shipments match these filters.</p>@endforelse
    {{ $shipments->links() }}
</div>
@endsection
