<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Never accept a shop ID from the request: even admins see only their own shop here.
        $shop = $request->user()->shop()->first();

        if (! $shop) {
            return view('seller.dashboard', ['shop' => null]);
        }

        $itemsQuery = OrderItem::query()
            ->whereHas('product', fn ($query) => $query->where('shop_id', $shop->id));

        $stats = [
            'products' => $shop->products()->count(),
            'active' => $shop->products()->where('is_active', true)->count(),
            'lowStock' => $shop->products()->where('is_active', true)->where('stock', '<=', 5)->count(),
            'orderItems' => (clone $itemsQuery)->count(),
        ];

        $products = $shop->products()->latest()->orderByDesc('id')
            ->paginate(10, ['*'], 'products_page')->withQueryString();

        // Deliberately load only order metadata, never buyer/contact/address or whole-order totals.
        $items = $itemsQuery->select(['id', 'order_id', 'product_id', 'name', 'qty', 'unit_price', 'line_total'])
            ->with('order:id,number,status,created_at')
            ->orderByDesc('id')->paginate(10, ['*'], 'items_page')->withQueryString();

        return view('seller.dashboard', compact('shop', 'stats', 'products', 'items'));
    }
}
