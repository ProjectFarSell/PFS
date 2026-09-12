<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StockReservationService
{
    /**
     * Lock products and confirm the requested quantities are available.
     * Stock is reserved by decrementing it in the same transaction that
     * creates the order.
     *
     * @param  Collection<int, object>  $lines
     * @return Collection<int, object{product: Product, qty: int, unit_price: float, line_total: float}>
     */
    public function lockAndPrepare(Collection $lines): Collection
    {
        return $lines->map(function (object $line): object {
            $product = Product::query()->lockForUpdate()->find($line->product->id);

            if (! $product || ! $product->is_active || $product->stock < $line->qty) {
                throw ValidationException::withMessages([
                    'cart' => $line->product->name.' no longer has enough stock. Please update your cart.',
                ]);
            }

            $unitPrice = (float) $product->price;

            return (object) [
                'product' => $product,
                'qty' => (int) $line->qty,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * (int) $line->qty,
            ];
        });
    }

    /** @param Collection<int, object{product: Product, qty: int}> $lines */
    public function reserve(Collection $lines): void
    {
        foreach ($lines as $line) {
            $line->product->decrement('stock', $line->qty);
        }
    }

    public function release(Order $order): void
    {
        if (! $order->stock_reserved_at || $order->stock_released_at) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->product_id) {
                Product::query()->whereKey($item->product_id)->increment('stock', $item->qty);
            }
        }

        $order->forceFill(['stock_released_at' => now()])->save();
    }
}
