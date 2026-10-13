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
        $query = Product::query()
            ->with(['shop:id,name,slug', 'category:id,name,slug'])
            ->where('is_active', true)
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

        if ($priceMin = $request->input('price_min')) {
            $query->where('price', '>=', (float) $priceMin);
        }

        if ($priceMax = $request->input('price_max')) {
            $query->where('price', '<=', (float) $priceMax);
        }

        if ($shopId = $request->integer('shop_id')) {
            $query->where('shop_id', $shopId);
        }

        return response()->json($query->paginate(20)->through(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'stock' => $product->stock,
            'image_url' => $product->image_path ? asset('storage/'.$product->image_path) : null,
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
