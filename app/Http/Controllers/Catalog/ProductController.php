<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $query = Product::query()
            ->with(['shop', 'category'])
            ->visible();

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($category = $request->integer('category')) {
            $query->where('category_id', $category);
        }

        // Shop filter — array of shop IDs from the filter sidebar
        $shopIds = array_values(array_filter((array) $request->input('shop', [])));
        if (! empty($shopIds)) {
            $query->whereIn('shop_id', $shopIds);
        }

        // Price range filter
        if ($priceMin = $request->integer('price_min')) {
            $query->where('price', '>=', $priceMin);
        }
        if ($priceMax = $request->integer('price_max')) {
            $query->where('price', '<=', $priceMax);
        }

        // Shops for the sidebar brand list (active shops with active product counts)
        $shops = Shop::query()
            ->where('is_active', true)
            ->withCount(['products as products_count' => fn ($q) => $q->visible()])
            ->orderBy('name')
            ->get();

        return view('catalog.index', [
            'products' => $query->latest()->paginate(16)->withQueryString(),
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'shops' => $shops,
            'q' => $search,
            'activeCategory' => $request->integer('category') ?: null,
            'activeShops' => $shopIds,
            'priceMin' => $request->integer('price_min') ?: '',
            'priceMax' => $request->integer('price_max') ?: '',
            'activeRatings' => $request->input('rating', []),
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->shop?->is_active, 404);

        $product->load(['shop', 'category']);

        $related = Product::query()
            ->with('shop')
            ->visible()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->take(8)
            ->get();

        return view('catalog.show', compact('product', 'related'));
    }
}
