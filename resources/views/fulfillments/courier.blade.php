@if($part->usesExternalCourier())
    <div class="mt-3 space-y-1 text-sm">
        <p>Courier: <strong>{{ $part->delivery_method->label() }}</strong></p>
        @if($part->courierBooking?->isSandbox())<p class="font-semibold text-error">Sandbox test shipment. No live courier or real COD settlement.</p>@endif
        @if($part->courierBooking?->reference)<p>Courier reference: {{ $part->courierBooking->reference }}@if($part->courierBooking->status === 'cancelled') (cancellation verified)@endif</p>@endif
        @if($part->courierBooking?->tracking_url)<x-ui.button variant="link" class="inline-block text-accent underline" :href="$part->courierBooking->tracking_url" target="_blank" rel="noopener noreferrer">Track with courier</x-ui.button>@endif
    </div>
@endif
