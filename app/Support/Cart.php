<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class Cart
{
    public const SESSION_KEY = 'farsell.cart';

    /**
     * @return array<int, int> product_id => qty
     */
    public static function lines(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public static function add(int $productId, int $qty = 1): void
    {
        $cart = self::lines();
        $cart[$productId] = ($cart[$productId] ?? 0) + max(1, $qty);
        Session::put(self::SESSION_KEY, $cart);
    }

    public static function update(int $productId, int $qty): void
    {
        $cart = self::lines();

        if ($qty < 1) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $qty;
        }

        Session::put(self::SESSION_KEY, $cart);
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
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
        $lines = self::lines();
        $products = Product::query()
            ->with('shop')
            ->whereIn('id', array_keys($lines))
            ->get()
            ->keyBy('id');

        return collect($lines)->map(function (int $qty, int $productId) use ($products) {
            $product = $products->get($productId);

            if (! $product) {
                return null;
            }

            return (object) [
                'product' => $product,
                'qty' => $qty,
                'line_total' => (float) $product->price * $qty,
            ];
        })->filter()->values();
    }

    public static function subtotal(): float
    {
        return (float) self::hydrated()->sum('line_total');
    }
}
