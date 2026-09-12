<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\PsgcBarangay;
use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Address::class);

        $addresses = $request->user()->addresses()->latest()->get();

        return view('account.addresses.index', compact('addresses'));
    }

    public function create(): View
    {
        $this->authorize('create', Address::class);

        return view('account.addresses.create', $this->locationViewData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Address::class);

        $data = $this->validatedAddress($request);

        $address = $request->user()->addresses()->create([
            ...$data,
            'label' => $data['label'] ?? 'Home',
            'is_default' => $request->boolean('is_default'),
        ]);

        if ($address->is_default) {
            $request->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return redirect()->route('account.addresses.index')->with('status', 'Address saved.');
    }

    public function edit(Address $address): View
    {
        $this->authorize('update', $address);

        return view('account.addresses.edit', [...$this->locationViewData($address), 'address' => $address]);
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('update', $address);

        $data = $this->validatedAddress($request);

        $address->update([
            ...$data,
            'label' => $data['label'] ?? 'Home',
            'is_default' => $request->boolean('is_default'),
        ]);

        if ($address->is_default) {
            $address->user->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return redirect()->route('account.addresses.index')->with('status', 'Address updated.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorize('delete', $address);

        $address->delete();

        return redirect()->route('account.addresses.index')->with('status', 'Address removed.');
    }

    public function provinces(Request $request): JsonResponse
    {
        $data = $request->validate(['region_id' => ['required', 'integer', 'exists:psgc_regions,id']]);

        return response()->json(PsgcProvince::query()
            ->where('psgc_region_id', $data['region_id'])
            ->orderBy('name')->get(['id', 'name']));
    }

    public function citiesMunicipalities(Request $request): JsonResponse
    {
        $data = $request->validate(['province_id' => ['required', 'integer', 'exists:psgc_provinces,id']]);

        return response()->json(PsgcCityMunicipality::query()
            ->where('psgc_province_id', $data['province_id'])
            ->orderBy('name')->get(['id', 'name']));
    }

    public function barangays(Request $request): JsonResponse
    {
        $data = $request->validate(['city_municipality_id' => ['required', 'integer', 'exists:psgc_cities_municipalities,id']]);

        return response()->json(PsgcBarangay::query()
            ->where('psgc_city_municipality_id', $data['city_municipality_id'])
            ->orderBy('name')->get(['id', 'name']));
    }

    /** @return array<string, mixed> */
    private function locationViewData(?Address $address = null): array
    {
        return [
            'regions' => PsgcRegion::query()->orderBy('name')->get(['id', 'name']),
            'selectedLocation' => [
                'region_id' => old('psgc_region_id', $address?->psgc_region_id),
                'province_id' => old('psgc_province_id', $address?->psgc_province_id),
                'city_municipality_id' => old('psgc_city_municipality_id', $address?->psgc_city_municipality_id),
                'barangay_id' => old('psgc_barangay_id', $address?->psgc_barangay_id),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function validatedAddress(Request $request): array
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'line1' => ['required', 'string', 'max:255'],
            'psgc_region_id' => ['required', 'integer', 'exists:psgc_regions,id'],
            'psgc_province_id' => ['required', 'integer', 'exists:psgc_provinces,id'],
            'psgc_city_municipality_id' => ['required', 'integer', 'exists:psgc_cities_municipalities,id'],
            'psgc_barangay_id' => ['required', 'integer', 'exists:psgc_barangays,id'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $barangay = PsgcBarangay::query()->with('cityMunicipality.province.region')->findOrFail($data['psgc_barangay_id']);
        $city = $barangay->cityMunicipality;
        $province = $city->province;

        if (! (
            $city->id === (int) $data['psgc_city_municipality_id']
            && $province->id === (int) $data['psgc_province_id']
            && $province->psgc_region_id === (int) $data['psgc_region_id']
        )) {
            throw ValidationException::withMessages([
                'psgc_barangay_id' => 'Select a barangay that belongs to the selected location.',
            ]);
        }

        return [...$data, 'city' => $city->name, 'region' => $province->region->name];
    }
}
