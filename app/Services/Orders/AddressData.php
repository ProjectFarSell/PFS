<?php

namespace App\Services\Orders;

use App\Models\PsgcBarangay;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddressData
{
    public function validate(array $input): array
    {
        $data = Validator::make($input, ['label' => ['nullable', 'string', 'max:60'], 'line1' => ['required', 'string', 'max:255'], 'psgc_region_id' => ['required', 'integer', 'exists:psgc_regions,id'], 'psgc_province_id' => ['required', 'integer', 'exists:psgc_provinces,id'], 'psgc_city_municipality_id' => ['required', 'integer', 'exists:psgc_cities_municipalities,id'], 'psgc_barangay_id' => ['required', 'integer', 'exists:psgc_barangays,id'], 'postal_code' => ['nullable', 'string', 'max:20'], 'phone' => ['nullable', 'string', 'max:40'], 'is_default' => ['nullable', 'boolean']])->validate();
        $barangay = PsgcBarangay::with('cityMunicipality.province.region')->findOrFail($data['psgc_barangay_id']);
        $city = $barangay->cityMunicipality;
        $province = $city->province;
        if ($city->id !== (int) $data['psgc_city_municipality_id'] || $province->id !== (int) $data['psgc_province_id'] || $province->psgc_region_id !== (int) $data['psgc_region_id']) {
            throw ValidationException::withMessages(['psgc_barangay_id' => 'Select a barangay that belongs to the selected location.']);
        }

        return [...$data, 'city' => $city->name, 'region' => $province->region->name];
    }
}
