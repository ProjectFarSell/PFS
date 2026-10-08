<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class Cart
{
    public const SESSION_KEY = 'farsell.cart';
    public const BUY_NOW_KEY = 'farsell.buy_now';

    /**
     * @return array<int|string, int> product or variant line key => qty
     */
    public static function lines(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public static function lineKey(int $productId, ?int $variantId = null): int|string
    {
        return $variantId ? 'v'.$variantId : $productId;
    }

    public static function add(int $productId, int $qty = 1, ?int $variantId = null): void
    {
        self::clearBuyNow();
        $cart = self::lines();
        $key = self::lineKey($productId, $variantId);
        $cart[$key] = ($cart[$key] ?? 0) + max(1, $qty);
        Session::put(self::SESSION_KEY, $cart);
    }

    public static function update(int $productId, int $qty, ?int $variantId = null): void
    {
        self::clearBuyNow();
        $cart = self::lines();
        $key = self::lineKey($productId, $variantId);

        if ($qty < 1) {
            unset($cart[$key]);
        } else {
            $cart[$key] = $qty;
        }

        Session::put(self::SESSION_KEY, $cart);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function startBuyNow(int $productId, int $qty = 1, ?int $variantId = null): void
    {
        Session::put(self::BUY_NOW_KEY, [self::lineKey($productId, $variantId) => max(1, $qty)]);
    }

    public static function clearBuyNow(): void
    {
        Session::forget(self::BUY_NOW_KEY);
    }

    public static function usesBuyNow(): bool
    {
        return Session::has(self::BUY_NOW_KEY);
    }

    public static function checkoutCount(): int
    {
        return array_sum(Session::get(self::BUY_NOW_KEY, self::lines()));
    }

    public static function forCheckout(): Collection
    {
        return self::hydrateLines(Session::get(self::BUY_NOW_KEY, self::lines()));
    }

    public static function clearCheckout(): void
    {
        if (self::usesBuyNow()) {
            self::clearBuyNow();
        } else {
            self::clear();
        }
    }

    public static function count(): int
    {
        return array_sum(self::lines());
    }

    /**
     * @return Collection<int, object>
     */
    public static function hydrated(): Collection
    {
        return self::hydrateLines(self::lines());
    }

    /** @param array<int|string, int> $lines */
    private static function hydrateLines(array $lines): Collection
    {
        $productIds = collect(array_keys($lines))->filter(fn ($key) => is_numeric($key))->map(fn ($id) => (int) $id);
        $variantIds = collect(array_keys($lines))->filter(fn ($key) => is_string($key) && str_starts_with($key, 'v'))->map(fn ($key) => (int) substr($key, 1));
        $products = Product::query()->with('shop')->whereIn('id', $productIds)->get()->keyBy('id');
        $variants = ProductVariant::query()->with('product.shop')->whereIn('id', $variantIds)->get()->keyBy(fn ($variant) => 'v'.$variant->id);

        return collect($lines)->map(function (int $qty, int|string $key) use ($products, $variants) {
            $variant = is_string($key) ? $variants->get($key) : null;
            $product = $variant?->product ?? $products->get((int) $key);

            if (! $product) {
                return null;
            }

            $unitPrice = $variant?->effectivePrice() ?? (float) $product->price;
            $options = $variant?->options ?: ($variant ? array_filter([$variant->option_name => $variant->option_value]) : []);

            return (object) [
                'product' => $product,
                'variant' => $variant,
                'stale_variant' => ($product->has_variants && ! $variant) || ($variant && ! $product->has_variants),
                'variant_options' => $options,
                'line_key' => $key,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $qty,
            ];
        })->filter()->values();
    }

    public static function subtotal(): float
    {
        return (float) self::hydrated()->sum('line_total');
    }
}
