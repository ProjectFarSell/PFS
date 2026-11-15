@php($vehicleTier = $part->required_vehicle_tier ?? $part->shipping_package['vehicle_tier'] ?? null)
<div class="mt-3 text-sm">
    <p>Dispatch vehicle: <strong>{{ $vehicleTier ? \App\Enums\ShippingVehicleTier::from($vehicleTier)->label() : 'Unmeasured / legacy — confirm suitability' }}</strong></p>
    @if($part->vehicle_upgrade)
        <p class="mt-1">Upgrade {{ $part->vehicle_upgrade['status'] }}: {{ \App\Enums\ShippingVehicleTier::from($part->vehicle_upgrade['requested_tier'])->label() }} — {{ $part->vehicle_upgrade['reason'] }}</p>
        @if(isset($part->vehicle_upgrade['review_note']))<p>Admin review: {{ $part->vehicle_upgrade['review_note'] }}</p>@endif
    @endif
    @if($part->vehicle_review_pending)<p class="mt-1 font-semibold">Dispatch paused for Admin vehicle review.</p>@endif
</div>
@if($isSeller && $part->status === \App\Enums\FulfillmentStatus::Ready && !$part->rider_id && !$part->usesExternalCourier() && !$part->vehicle_review_pending)
    <details class="mt-3"><summary class="cursor-pointer text-sm text-accent">Request a larger vehicle</summary>
        <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-2 space-y-2">@csrf<input type="hidden" name="action" value="request_vehicle_upgrade">@include('fulfillments.vehicle-upgrade-fields')<x-ui.button type="submit">Request upgrade</x-ui.button></form>
    </details>
@endif
@if($isAdmin && $part->vehicle_review_pending && $part->status === \App\Enums\FulfillmentStatus::Ready)
    <form method="post" action="{{ route('fulfillments.update', $part) }}" class="mt-3 space-y-2 rounded-lg border border-surface-border p-3">@csrf<input type="hidden" name="action" value="review_vehicle_upgrade">
        <label class="block text-sm">Review decision<select name="vehicle_upgrade_decision" required class="mt-1 w-full rounded-lg"><option value="approved">Approve upgrade</option><option value="rejected">Reject upgrade</option></select></label>
        <label class="block text-sm">Review reason<textarea name="vehicle_upgrade_reason" required minlength="10" maxlength="1000" class="mt-1 w-full rounded-lg"></textarea></label>
        <x-ui.button type="submit">Record vehicle review</x-ui.button>
    </form>
@endif
