<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\PsgcBarangay;
use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use App\Models\User;
use App\Services\Mobile\MobileViews;
use App\Services\Mobile\PublicIds;
use App\Services\Mobile\Reply;
use App\Services\Orders\AddressData;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(MobileRequest $request)
    {
        return Reply::page($request->user()->addresses()->with(['psgcRegion', 'psgcProvince', 'psgcCityMunicipality', 'psgcBarangay'])->orderByDesc('id')->cursorPaginate($request->integer('limit', 20)), fn ($address) => MobileViews::address($address));
    }

    public function store(MobileRequest $request)
    {
        return $this->save($request);
    }

    public function update(MobileRequest $request, string $address_id)
    {
        return $this->save($request, $address_id);
    }

    private function save(MobileRequest $request, ?string $id = null)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $address = $id ? $request->user()->addresses()->where('public_id', $id)->lockForUpdate()->firstOrFail() : null;
            $mapped = $data;
            foreach (['region_id' => ['psgc_region_id', PsgcRegion::class], 'province_id' => ['psgc_province_id', PsgcProvince::class], 'city_id' => ['psgc_city_municipality_id', PsgcCityMunicipality::class], 'barangay_id' => ['psgc_barangay_id', PsgcBarangay::class]] as $field => [$column, $model]) {
                $mapped[$column] = PublicIds::find($model, $data[$field])->id;
                unset($mapped[$field]);
            }
            $validated = app(AddressData::class)->validate($mapped);
            $validated['label'] ??= 'Home';
            if ($address) {
                $address->update($validated);
            } else {
                $address = $request->user()->addresses()->create($validated);
            }
            if ($address->is_default) {
                $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
            $address->load(['psgcRegion', 'psgcProvince', 'psgcCityMunicipality', 'psgcBarangay']);

            return Reply::data(MobileViews::address($address), $id ? 200 : 201);
        }, 3);
    }

    public function destroy(MobileRequest $request, string $address_id)
    {
        DB::transaction(function () use ($request, $address_id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $request->user()->addresses()->where('public_id', $address_id)->firstOrFail()->delete();
        });

        return response()->noContent();
    }

    public function locations(MobileRequest $request, string $level)
    {
        [$model, $parent, $column, $parentModel] = match ($level) {
            'regions' => [PsgcRegion::class, null, null, null],
            'provinces' => [PsgcProvince::class, 'region_id', 'psgc_region_id', PsgcRegion::class],
            'cities' => [PsgcCityMunicipality::class, 'province_id', 'psgc_province_id', PsgcProvince::class],
            'barangays' => [PsgcBarangay::class, 'city_id', 'psgc_city_municipality_id', PsgcCityMunicipality::class],
            default => abort(404),
        };
        $query = $model::query();
        if ($parent) {
            $request->validate([$parent => ['required', 'uuid']]);
            $query->where($column, PublicIds::find($parentModel, $request->input($parent))->id);
        }

        return Reply::page($query->orderBy('id')->cursorPaginate($request->integer('limit', 20)), fn ($location) => ['id' => PublicIds::id($location), 'name' => $location->name]);
    }
}
