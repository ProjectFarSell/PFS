<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    /**
     * Public, versioned catalogue endpoint for a future Capacitor client.
     * Authenticated mobile endpoints belong in the Sanctum milestone.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'city_id' => ['nullable', 'integer', 'exists:psgc_cities_municipalities,id'],
            'price_min' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', ...($request->filled('price_min') ? ['gte:price_min'] : [])],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Product::query()
            ->with(['shop:id,name,slug', 'category:id,name,slug'])
            ->visible()->withAvailableStock()
            ->latest();

        if ($search = $request->string('q')->trim()->toString()) {
            if (DB::getDriverName() !== 'sqlite') {
                $query->whereFullText(['name', 'description'], $search);
            } else {
                $query->where('name', 'like', '%'.$search.'%');
            }
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->filled('price_min')) {
            $query->where('price', '>=', (float) $request->input('price_min'));
        }

        if ($request->has('price_max') && $request->input('price_max') !== null) {
            $query->where('price', '<=', (float) $request->input('price_max'));
        }

        if ($shopId = $request->integer('shop_id')) {
            $query->where('shop_id', $shopId);
        }

        if ($cityId = $request->integer('city_id')) {
            $query->whereHas('shop', fn ($q) => $q->inCity($cityId));
        }

        $sort = $request->string('sort')->toString();
        if ($sort === 'price_asc') {
            $query->reorder()->orderBy('price');
        } elseif ($sort === 'price_desc') {
            $query->reorder()->orderByDesc('price');
        }

        return response()->json($query->paginate(20)->through(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'stock' => $product->availableStock(),
            'image_url' => $product->imageUrl(),
            'shop' => [
                'name' => $product->shop->name,
                'slug' => $product->shop->slug,
            ],
            'category' => [
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ],
        ]));
    }
}
