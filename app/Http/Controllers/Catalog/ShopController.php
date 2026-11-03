<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(): View
    {
        $shops = Shop::query()->where('is_active', true)
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')->orderBy('id')->paginate(12);

        return view('shop.index', compact('shops'));
    }

    public function show(Shop $shop): View
    {
        abort_unless($shop->is_active, 404);

        $products = $shop->products()
            ->with(['shop', 'category'])
            ->where('is_active', true)
            ->latest()
            ->paginate(12);

        $savedProductIds = auth()->user()?->role === UserRole::Buyer
            ? auth()->user()->savedItems()->pluck('product_id')->all() : [];

        return view('shop.show', compact('shop', 'products', 'savedProductIds'));
    }
}
