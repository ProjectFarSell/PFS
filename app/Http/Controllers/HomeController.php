<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Cart;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::query()->orderBy('sort_order')->get();

        $flash = Product::query()
            ->with(['shop', 'category'])
            ->where('is_active', true)
            ->where('is_flash', true)
            ->latest()
            ->take(8)
            ->get();

        $products = Product::query()
            ->with(['shop', 'category'])
            ->where('is_active', true)
            ->latest()
            ->take(16)
            ->get();

        return view('home', [
            'categories' => $categories,
            'flash' => $flash,
            'products' => $products,
            'cartCount' => Cart::count(),
        ]);
    }
}
