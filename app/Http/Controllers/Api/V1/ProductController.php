<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\PsgcCityMunicipality;
use App\Models\Shop;
use App\Services\Mobile\MobileViews;
use App\Services\Mobile\PublicIds;
use App\Services\Mobile\Reply;

class ProductController extends Controller
{
    public function index(MobileRequest $request)
    {
        $data = $request->validated();
        $query = Product::visible()->withAvailableStock()->with(['shop', 'category', 'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')]);
        if ($search = trim($data['q'] ?? '')) {
            $query->where('name', 'like', '%'.$search.'%');
        }
        foreach (['category_id' => Category::class, 'shop_id' => Shop::class] as $field => $model) {
            if (! empty($data[$field])) {
                $query->where($field, PublicIds::find($model, $data[$field])->id);
            }
        }
        if (! empty($data['city_id'])) {
            $city = PublicIds::find(PsgcCityMunicipality::class, $data['city_id']);
            $query->whereHas('shop', fn ($q) => $q->where('psgc_city_municipality_id', $city->id));
        }
        if (isset($data['price_min'])) {
            $query->where('price', '>=', $data['price_min'] / 100);
        }
        if (isset($data['price_max'])) {
            $query->where('price', '<=', $data['price_max'] / 100);
        }
        match ($data['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price'), 'price_desc' => $query->orderByDesc('price'), default => $query->orderByDesc('created_at')
        };

        return Reply::page($query->orderByDesc('id')->cursorPaginate($data['limit'] ?? 20), fn ($product) => MobileViews::product($product));
    }

    public function show(string $product_id)
    {
        $product = Product::visible()->where('public_id', $product_id)->with(['shop', 'category', 'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')])->firstOrFail();

        return Reply::data(MobileViews::product($product));
    }
}
