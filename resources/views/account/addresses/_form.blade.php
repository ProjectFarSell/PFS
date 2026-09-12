@php
    $isEditing = isset($address);
@endphp

<form method="post" action="{{ $isEditing ? route('account.addresses.update', $address) : route('account.addresses.store') }}" class="space-y-3"
      data-address-location-form>
    @csrf
    @if ($isEditing) @method('put') @endif

    <input name="label" placeholder="Label (e.g. Home, Work)" value="{{ old('label', $address->label ?? '') }}" class="w-full rounded-lg border-stone-200 text-sm">
    <input name="line1" required placeholder="House no., street, building" value="{{ old('line1', $address->line1 ?? '') }}" class="w-full rounded-lg border-stone-200 text-sm">

    <label class="block text-sm">Region
        <select name="psgc_region_id" required  class="mt-1 w-full rounded-lg border-stone-200 text-sm">
            <option value="">Select region</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((string) $selectedLocation['region_id'] === (string) $region->id)>{{ $region->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">Province
        <select name="psgc_province_id" required disabled data-selected="{{ $selectedLocation['province_id'] }}" data-url="{{ route('account.addresses.locations.provinces', [], false) }}" data-parent-param="region_id" class="mt-1 w-full rounded-lg border-stone-200 text-sm disabled:bg-stone-100">
            <option value="">Select province</option>
        </select>
    </label>
    <label class="block text-sm">City / Municipality
        <select name="psgc_city_municipality_id" required disabled data-selected="{{ $selectedLocation['city_municipality_id'] }}" data-url="{{ route('account.addresses.locations.cities-municipalities', [], false) }}" data-parent-param="province_id" class="mt-1 w-full rounded-lg border-stone-200 text-sm disabled:bg-stone-100">
            <option value="">Select city or municipality</option>
        </select>
    </label>
    <label class="block text-sm">Barangay
        <select name="psgc_barangay_id" required disabled data-selected="{{ $selectedLocation['barangay_id'] }}" data-url="{{ route('account.addresses.locations.barangays', [], false) }}" data-parent-param="city_municipality_id" class="mt-1 w-full rounded-lg border-stone-200 text-sm disabled:bg-stone-100">
            <option value="">Select barangay</option>
        </select>
    </label>

    <p data-location-status role="status" aria-live="polite" class="text-sm text-stone-600"></p>
    <button type="button" data-location-retry hidden class="text-sm text-orange-600 underline">Retry loading locations</button>
    <noscript><p class="text-sm text-red-600">Enable JavaScript to select your delivery location.</p></noscript>

    <input name="postal_code" placeholder="Postal code" value="{{ old('postal_code', $address->postal_code ?? '') }}" class="w-full rounded-lg border-stone-200 text-sm">
    <input name="phone" placeholder="Phone" value="{{ old('phone', $address->phone ?? '') }}" class="w-full rounded-lg border-stone-200 text-sm">
    <label class="flex items-center gap-2 text-sm text-stone-600">
        <input type="checkbox" name="is_default" value="1" {{ old('is_default', $address->is_default ?? false) ? 'checked' : '' }} class="rounded border-stone-300"> Set as default
    </label>
    @if ($errors->any())
        <ul class="text-sm text-red-600 list-disc pl-4">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <button data-address-save disabled class="disabled:opacity-50 w-full rounded-full bg-orange-500 text-white text-sm py-2.5">{{ $isEditing ? 'Update address' : 'Save address' }}</button>
</form>

@push('scripts')
<script src="{{ asset('js/address-location.js') }}" defer></script>
@endpush
