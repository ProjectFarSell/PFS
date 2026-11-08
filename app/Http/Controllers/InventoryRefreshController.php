<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryRefreshController extends Controller
{
    public function product(Product $product)
    {
        abort_unless($product->is_active && $product->shop?->is_active, 404);
        $product->load(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')]);

        return response()->json([
            'available' => true,
            'has_variants' => $product->has_variants,
            'stock' => $product->availableStock(),
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id, 'sku' => $variant->sku, 'stock' => (int) $variant->stock,
                'price' => $variant->effectivePrice(),
                'options' => $variant->options ?: array_filter([$variant->option_name => $variant->option_value]),
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function admin(Request $request)
    {
        return $this->listing($request, Product::query());
    }

    public function seller(Request $request)
    {
        // Resolve ownership from the authenticated user, never a supplied shop ID.
        $shop = $request->user()->shop()->first();
        abort_unless($shop, 404);
        $stats = [
            'products' => $shop->products()->count(),
            'active' => $shop->products()->where('is_active', true)->count(),
            'lowStock' => $shop->products()->lowStock()->count(),
            'orderItems' => OrderItem::whereHas('product', fn ($q) => $q->where('shop_id', $shop->id))->count(),
        ];

        return $this->listing($request, $shop->products(), $stats);
    }

    private function listing(Request $request, $query, ?array $stats = null)
    {
        $data = $request->validate(['ids' => ['sometimes', 'array', 'max:20'], 'ids.*' => ['integer', 'min:1', 'distinct']]);
        $products = $query->whereIn('products.id', $data['ids'] ?? [])->withAvailableStock()
            ->with(['variants' => fn ($q) => $q->select('id', 'product_id', 'stock', 'is_active')->orderBy('id')])->get();

        return response()->json([
            'products' => $products->map(fn ($product) => [
                'id' => $product->id, 'stock' => $product->availableStock(), 'active' => $product->is_active,
                'variants' => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id, 'stock' => $variant->is_active ? (int) $variant->stock : 0, 'active' => $variant->is_active,
                ])->values(),
            ])->values(),
            'stats' => $stats,
        ])->header('Cache-Control', 'private, no-store');
    }
}
