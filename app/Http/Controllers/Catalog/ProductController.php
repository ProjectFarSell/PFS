<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\PsgcCityMunicipality;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'sort' => ['nullable', 'string', 'in:relevance,price_asc,price_desc,newest'],
            'city_id' => ['nullable', 'integer', 'exists:psgc_cities_municipalities,id'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $categoryId = isset($filters['category']) ? (int) $filters['category'] : null;
        $shopIds = collect($filters['shop'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $priceMin = $filters['price_min'] ?? null;
        $priceMax = $filters['price_max'] ?? null;
        $sort = $filters['sort'] ?? 'newest';
        $sameDay = $request->boolean('same_day');

        // Resolve city_id: explicit param > authenticated user's default address city
        $cityId = isset($filters['city_id']) ? (int) $filters['city_id'] : null;
        if ($cityId === null && auth()->check()) {
            $defaultCityId = auth()->user()->addresses()
                ->where('is_default', true)
                ->value('psgc_city_municipality_id');
            if ($defaultCityId !== null) {
                $cityId = (int) $defaultCityId;
            }
        }

        $query = Product::query()
            ->with(['shop', 'category'])
            ->visible();

        if ($search !== '') {
            if (DB::getDriverName() !== 'sqlite') {
                $query->whereFullText(['name', 'description'], $search);
            } else {
                $query->where('name', 'like', '%'.$search.'%');
            }
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

        // City filter
        if ($cityId !== null) {
            $query->whereHas('shop', fn ($q) => $q->inCity($cityId));
        }

        // Apply sort ordering
        match ($sort) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'relevance'  => $search !== '' ? $query->latest() : $query->latest(),
            default      => $query->latest(), // newest
        };

        // Shops for the sidebar brand list (active shops with active product counts)
        $shops = Shop::query()
            ->where('is_active', true)
            ->withCount(['products as products_count' => fn ($q) => $q->visible()])
            ->orderBy('name')
            ->get();

        return view('catalog.index', [
            'products' => $query->paginate(16)->withQueryString(),
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'shops' => $shops,
            'q' => $search,
            'activeCategory' => $categoryId,
            'activeShops' => $shopIds,
            'priceMin' => $priceMin ?? '',
            'priceMax' => $priceMax ?? '',
            'sort' => $sort,
            'cityId' => $cityId,
            'activeCityId' => $cityId,
            'cities' => PsgcCityMunicipality::orderBy('name')->get(),
            'sameDay' => $sameDay,
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
