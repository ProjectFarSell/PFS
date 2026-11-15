@props(['package' => null])
@if(is_array($package))
    <div {{ $attributes->class('mt-3 rounded-xl border border-surface-border bg-surface-muted p-3 text-xs') }}>
        <p class="font-semibold">Package estimate · {{ $package['total_quantity'] }} {{ $package['total_quantity'] === 1 ? 'item' : 'items' }}</p>
        <dl class="mt-2 grid gap-1 sm:grid-cols-2">
            <div><dt class="inline">Actual weight: </dt><dd class="inline">{{ $package['actual_weight_kg'] === null ? 'Not provided' : $package['actual_weight_kg'].' kg' }}</dd></div>
            <div><dt class="inline">Volumetric weight: </dt><dd class="inline">{{ $package['volumetric_weight_kg'] === null ? 'Not provided' : $package['volumetric_weight_kg'].' kg' }}</dd></div>
            <div><dt class="inline">Chargeable weight: </dt><dd class="inline">{{ $package['chargeable_weight_kg'] === null ? 'Needs shipping details' : $package['chargeable_weight_kg'].' kg' }}</dd></div>
            <div><dt class="inline">Largest item side: </dt><dd class="inline">{{ $package['max_single_item_dimension_cm'] === null ? 'Not provided' : $package['max_single_item_dimension_cm'].' cm' }}</dd></div>
        </dl>
        <p class="mt-2 font-medium">Suggested vehicle: {{ $package['vehicle_tier'] ? \App\Enums\ShippingVehicleTier::from($package['vehicle_tier'])->label() : 'Needs shipping details' }}</p>
        <p class="mt-1 text-text-muted">Item-volume estimate using divisor {{ number_format($package['volumetric_divisor']) }}. Final box packing and vehicle suitability need confirmation. The current checkout delivery fee still applies.</p>
        <x-shipping-details :details="['handling_flags' => $package['handling_flags']]" />
    </div>
@endif
