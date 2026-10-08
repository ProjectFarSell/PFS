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
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'shop' => ['nullable', 'array', 'max:50'],
            'shop.*' => ['integer', 'distinct', 'exists:shops,id'],
            'price_min' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'gte:price_min'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $categoryId = isset($filters['category']) ? (int) $filters['category'] : null;
        $shopIds = collect($filters['shop'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $priceMin = $filters['price_min'] ?? null;
        $priceMax = $filters['price_max'] ?? null;

        $query = Product::query()
            ->with(['shop', 'category'])
            ->visible();

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        // Shop filter — array of shop IDs from the filter sidebar
        if (! empty($shopIds)) {
            $query->whereIn('shop_id', $shopIds);
        }

        // Price range filter
        if ($priceMin !== null) {
            $query->where('price', '>=', $priceMin);
        }
        if ($priceMax !== null) {
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
            'activeCategory' => $categoryId,
            'activeShops' => $shopIds,
            'priceMin' => $priceMin ?? '',
            'priceMax' => $priceMax ?? '',
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->shop?->is_active, 404);

        $product->load(['shop', 'category', 'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')]);

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
