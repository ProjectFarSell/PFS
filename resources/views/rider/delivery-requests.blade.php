<p class="font-semibold" role="status">{{ $count }} available delivery {{ $count === 1 ? 'request' : 'requests' }}</p>
<div class="mt-3 space-y-3">
    @forelse($requests as $deliveryRequest)
        <article class="rounded-xl border border-surface-border p-4">
            <h3 class="font-semibold">{{ $deliveryRequest->shop_name }} · Shipment #{{ $deliveryRequest->id }}</h3>
            <p class="text-sm mt-1">Pickup area: {{ ucwords($deliveryRequest->pickup_city) }}</p>
            <p class="text-sm mt-1">Offer expires at {{ \Illuminate\Support\Carbon::parse($deliveryRequest->offer_expires_at)->format('g:i:s A T') }}.</p>
            <p class="text-xs text-text-muted mt-1">Offered to you automatically. You have two minutes from the offer to accept; otherwise another rider will be tried. Accept to see pickup contact, recipient details, and any COD amount.</p>
            <form method="post" action="{{ route('fulfillments.update', $deliveryRequest) }}" class="mt-3">
                @csrf <button name="action" value="claim" class="btn-accent">Accept delivery request</button>
                <button name="action" value="decline" class="btn-outline">Decline offer</button>
            </form>
        </article>
    @empty
        <p class="text-sm text-text-muted">No matching requests right now. Check that you are available; new requests will appear here.</p>
    @endforelse
</div>
@if($count > 30)<p class="mt-3 text-sm">Showing the 30 oldest requests first.</p>@endif
