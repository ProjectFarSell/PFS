<?php

namespace App\Services\Orders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class PurchaseLine
{
    public function resolve(int $productId, ?int $variantId): array
    {
        $product = Product::query()->visible()->findOrFail($productId);
        $variant = $variantId ? ProductVariant::where('product_id', $product->id)->where('is_active', true)->findOrFail($variantId) : null;
        if ($variant && ! $product->has_variants) {
            throw ValidationException::withMessages(['variant_id' => 'This product no longer uses options. Reload the product page.']);
        }
        if ($product->has_variants && ! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'Choose an available option before continuing.']);
        }

        return [$product, $variant, (int) ($variant?->stock ?? $product->stock)];
    }
}
