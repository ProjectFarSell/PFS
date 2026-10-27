<?php

namespace App\Http\Controllers\Seller;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);
        if ($user->role === UserRole::Seller && $user->shop()->exists()) {
            return to_route('seller.dashboard');
        }

        $application = $user->sellerApplication()->with('cityMunicipality.province.region')->first();
        $selectedCategories = old('category_ids', $application?->category_ids ?? []);
        $selectedCategories = is_array($selectedCategories) ? array_map('intval', $selectedCategories) : [];

        return view('seller.application', [
            'application' => $application,
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'regions' => PsgcRegion::query()->orderBy('name')->get(['id', 'name']),
            'selectedLocation' => [
                'region_id' => old('psgc_region_id', $application?->cityMunicipality?->province?->psgc_region_id),
                'province_id' => old('psgc_province_id', $application?->cityMunicipality?->psgc_province_id),
                'city_municipality_id' => old('psgc_city_municipality_id', $application?->psgc_city_municipality_id),
            ],
            'selectedCategories' => $selectedCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, [UserRole::Buyer, UserRole::Seller], true), 403);
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:120'],
            'psgc_region_id' => ['required', 'integer', 'exists:psgc_regions,id'],
            'psgc_province_id' => ['required', 'integer', 'exists:psgc_provinces,id'],
            'psgc_city_municipality_id' => ['required', 'integer', 'exists:psgc_cities_municipalities,id'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:2000'],
            'category_ids' => ['required', 'array', 'min:1', 'max:8'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
        ]);

        $city = PsgcCityMunicipality::query()->with('province')->findOrFail($data['psgc_city_municipality_id']);
        if ((int) $city->psgc_province_id !== (int) $data['psgc_province_id']
            || (int) $city->province->psgc_region_id !== (int) $data['psgc_region_id']) {
            throw ValidationException::withMessages(['psgc_city_municipality_id' => 'Choose a city or municipality that belongs to the selected province and region.']);
        }

        DB::transaction(function () use ($request, $data, $city) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);
            $application = $user->sellerApplication()->lockForUpdate()->first();
            if ($user->shop()->exists() || $application?->status === 'approved') {
                throw ValidationException::withMessages(['application' => 'Your shop is already set up or approved. Contact an administrator for changes.']);
            }
            $user->sellerApplication()->updateOrCreate([], [
                'shop_name' => $data['shop_name'],
                'city' => $city->name,
                'psgc_city_municipality_id' => $city->id,
                'tagline' => $data['tagline'] ?? null,
                'description' => $data['description'],
                'category_ids' => array_map('intval', $data['category_ids']),
                'status' => 'pending', 'revision' => (string) Str::uuid(), 'submitted_at' => now(),
                'reviewed_at' => null, 'reviewed_by' => null, 'review_note' => null,
            ]);
        });

        return to_route('seller.apply')->with('status', 'Seller application submitted. An administrator will review it before your shop is enabled.');
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
}
