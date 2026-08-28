<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function show(Shop $shop): View
    {
        abort_unless($shop->is_active, 404);

        $products = $shop->products()
            ->where('is_active', true)
            ->latest()
            ->paginate(12);

        return view('shop.show', compact('shop', 'products'));
    }
}
