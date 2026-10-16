<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Support\Cart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::query()
            ->withCount(['products' => fn (Builder $query) => $query->visible()])
            ->orderBy('sort_order')
            ->get();

        $popular = Product::query()
            ->with(['shop', 'category'])
            ->visible()
            ->has('savedItems')
            ->withCount('savedItems')
            ->orderByDesc('saved_items_count')
            ->orderByDesc('id')
            ->take(4)
            ->get();

        $flash = Product::query()
            ->with(['shop', 'category', 'variants'])
            ->visible()
            ->where('is_flash', true)
            ->latest()
            ->take(8)
            ->get();

        $products = Product::query()
            ->with(['shop', 'category'])
            ->visible()
            ->latest()
            ->take(16)
            ->get();

        $savedProductIds = (auth()->check() && auth()->user()->role === UserRole::Buyer)
            ? auth()->user()->savedItems()->pluck('product_id')->all()
            : [];

        return view('home', [
            'categories' => $categories,
            'popular' => $popular,
            'flash' => $flash,
            'products' => $products,
            'cartCount' => Cart::count(),
            'savedProductIds' => $savedProductIds,
        ]);
    }
}
