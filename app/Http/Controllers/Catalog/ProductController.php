<?php

namespace App\Http\Controllers\Catalog;

use App\Contracts\DeliveryEtaService;
use App\Enums\UserRole;
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
    public function __construct(private readonly DeliveryEtaService $etaService) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'shop' => ['nullable', 'array', 'max:50'],
            'shop.*' => ['integer', 'distinct', 'exists:shops,id'],
            'shop_search' => ['nullable', 'string', 'max:120'],
            'price_min' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'gte:price_min'],
            'sort' => ['nullable', 'string', 'in:relevance,price_asc,price_desc,newest'],
            'city_id' => ['nullable', 'integer', 'exists:psgc_cities_municipalities,id'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $categoryId = isset($filters['category']) ? (int) $filters['category'] : null;
        $shopIds = collect($filters['shop'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $shopSearch = trim((string) ($filters['shop_search'] ?? ''));
        $priceMin = $filters['price_min'] ?? null;
        $priceMax = $filters['price_max'] ?? null;
        $sort = $filters['sort'] ?? 'newest';
        $sameDay = $request->boolean('same_day');

        // Resolve city_id: explicit param > authenticated user's default address city
        $cityId = isset($filters['city_id']) ? (int) $filters['city_id'] : null;
        if ($cityId === null && auth()->check() && ! $request->has('clear_city')) {
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

        $matchingShops = $this->matchingActiveShops($shopSearch !== '' ? $shopSearch : $search);
        $matchingShopIds = $matchingShops->pluck('id');

        if ($shopSearch !== '') {
            // A shop filter search is an explicit request for products from the matching shop(s).
            $query->whereIn('shop_id', $matchingShopIds);
            $shopIds = $matchingShopIds->map(fn ($id) => (int) $id)->all();
        } elseif ($matchingShopIds->isNotEmpty()) {
            // A shop-name search should show listings from those shops, not unrelated product-name matches.
            $query->whereIn('shop_id', $matchingShopIds);
        } elseif ($search !== '') {
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
        // Note: 'relevance' with a search term intentionally skips an explicit orderBy so that
        // MySQL's fulltext relevance score governs the order. Without a search term it falls
        // back to ->latest() just like 'newest'.
        if ($sort === 'price_asc') {
            $query->orderBy('price');
        } elseif ($sort === 'price_desc') {
            $query->orderByDesc('price');
        } elseif ($sort === 'relevance' && $search !== '') {
            // Skip orderBy to let fulltext relevance scoring apply
        } else {
            // 'newest' or 'relevance' without search term
            $query->latest();
        }

        // Shops for the sidebar brand list (active shops with active product counts)
        $shops = Shop::query()
            ->where('is_active', true)
            ->withCount(['products as products_count' => fn ($q) => $q->visible()])
            ->orderBy('name')
            ->get();
        $priceCeiling = (int) (ceil(((float) Product::query()->visible()->max('price')) / 1000) * 1000);
        $priceCeiling = max($priceCeiling, 1000);

        $savedProductIds = (auth()->check() && auth()->user()->role === UserRole::Buyer)
            ? auth()->user()->savedItems()->pluck('product_id')->all()
            : [];

        return view('catalog.index', [
            'products' => $query->paginate(16)->withQueryString(),
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'shops' => $shops,
            'searchedShop' => $matchingShops->count() === 1 ? $matchingShops->first() : null,
            'q' => $search,
            'activeCategory' => $categoryId,
            'activeShops' => $shopIds,
            'shopSearch' => $shopSearch,
            'priceMin' => $priceMin ?? '',
            'priceMax' => $priceMax ?? '',
            'priceCeiling' => $priceCeiling,
            'sort' => $sort,
            'cityId' => $cityId,
            'activeCityId' => $cityId,
            'cities' => PsgcCityMunicipality::orderBy('name')->get(),
            'sameDay' => $sameDay,
            'savedProductIds' => $savedProductIds,
        ]);
    }

    private function matchingActiveShops(string $term)
    {
        if ($term === '') {
            return collect();
        }

        $activeShops = Shop::query()->where('is_active', true);
        $exactMatches = (clone $activeShops)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($term)])
            ->get(['id', 'name']);

        return $exactMatches->isNotEmpty()
            ? $exactMatches
            : $activeShops->where('name', 'like', '%'.$term.'%')->get(['id', 'name']);
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

        $etaHours = null;
        if (auth()->check()) {
            $defaultAddress = auth()->user()->addresses()->where('is_default', true)->first();
            if ($defaultAddress && $product->shop->psgc_city_municipality_id) {
                $etaHours = $this->etaService->estimate($defaultAddress, (int) $product->shop->psgc_city_municipality_id);
            }
        }

        $reviews = $product->reviews()->with(['buyer:id,name', 'orderItem:id,variant_options,product_sku', 'media'])
            ->latest('id')->paginate(5, ['*'], 'reviews_page')->withQueryString();
        $reviewAverage = $product->reviews()->avg('rating');

        return view('catalog.show', compact('product', 'related', 'etaHours', 'reviews', 'reviewAverage'));
    }
}
