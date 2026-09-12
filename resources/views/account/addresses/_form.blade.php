@php
    $isEditing = isset($address);
@endphp

<form method="post" action="{{ $isEditing ? route('account.addresses.update', $address) : route('account.addresses.store') }}" class="space-y-3"
      x-data="addressLocationForm({{ Js::from($selectedLocation) }})" x-init="init()">
    @csrf
    @if ($isEditing) @method('put') @endif

    <input name="label" placeholder="Label (e.g. Home, Work)" value="{{ old('label', $address->label ?? '') }}" class="w-full rounded-lg border-stone-200 text-sm">
    <input name="line1" required placeholder="House no., street, building" value="{{ old('line1', $address->line1 ?? '') }}" class="w-full rounded-lg border-stone-200 text-sm">

    <label class="block text-sm">Region
        <select name="psgc_region_id" required x-model="regionId" @change="loadProvinces()" class="mt-1 w-full rounded-lg border-stone-200 text-sm">
            <option value="">Select region</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}">{{ $region->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">Province
        <select name="psgc_province_id" required x-model="provinceId" @change="loadCities()" :disabled="!regionId" class="mt-1 w-full rounded-lg border-stone-200 text-sm disabled:bg-stone-100">
            <option value="">Select province</option>
            <template x-for="province in provinces" :key="province.id"><option :value="province.id" x-text="province.name"></option></template>
        </select>
    </label>
    <label class="block text-sm">City / Municipality
        <select name="psgc_city_municipality_id" required x-model="cityId" @change="loadBarangays()" :disabled="!provinceId" class="mt-1 w-full rounded-lg border-stone-200 text-sm disabled:bg-stone-100">
            <option value="">Select city or municipality</option>
            <template x-for="city in cities" :key="city.id"><option :value="city.id" x-text="city.name"></option></template>
        </select>
    </label>
    <label class="block text-sm">Barangay
        <select name="psgc_barangay_id" required x-model="barangayId" :disabled="!cityId" class="mt-1 w-full rounded-lg border-stone-200 text-sm disabled:bg-stone-100">
            <option value="">Select barangay</option>
            <template x-for="barangay in barangays" :key="barangay.id"><option :value="barangay.id" x-text="barangay.name"></option></template>
        </select>
    </label>

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
    <button class="w-full rounded-full bg-orange-500 text-white text-sm py-2.5">{{ $isEditing ? 'Update address' : 'Save address' }}</button>
</form>

@push('scripts')
<script>
function addressLocationForm(selected) {
    return {
        regionId: selected.region_id || '', provinceId: selected.province_id || '', cityId: selected.city_municipality_id || '', barangayId: selected.barangay_id || '',
        provinces: [], cities: [], barangays: [],
        async init() { if (this.regionId) { await this.loadProvinces(true); } if (this.provinceId) { await this.loadCities(true); } if (this.cityId) { await this.loadBarangays(true); } },
        async fetchOptions(url) { return (await fetch(url, { headers: { Accept: 'application/json' } })).json(); },
        async loadProvinces(keep = false) { if (!keep) { this.provinceId = ''; this.cityId = ''; this.barangayId = ''; this.cities = []; this.barangays = []; } this.provinces = this.regionId ? await this.fetchOptions(`{{ route('account.addresses.locations.provinces') }}?region_id=${this.regionId}`) : []; },
        async loadCities(keep = false) { if (!keep) { this.cityId = ''; this.barangayId = ''; this.barangays = []; } this.cities = this.provinceId ? await this.fetchOptions(`{{ route('account.addresses.locations.cities-municipalities') }}?province_id=${this.provinceId}`) : []; },
        async loadBarangays(keep = false) { if (!keep) this.barangayId = ''; this.barangays = this.cityId ? await this.fetchOptions(`{{ route('account.addresses.locations.barangays') }}?city_municipality_id=${this.cityId}`) : []; },
    };
}
</script>
@endpush
