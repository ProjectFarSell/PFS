<?php

namespace App\Services\Mobile;

use App\Contracts\DeliveryFeeService;
use App\Models\Address;
use App\Models\MobileCart;
use App\Models\Shop;
use App\Services\Orders\CheckoutAvailability;
use Illuminate\Support\Collection;

class MobileCheckout
{
    public function methods(): array
    {
        return app()->environment('staging') && config('mobile.staging_payments') ? ['cod', 'gateway_stub'] : ['cod'];
    }

    public function snapshot(MobileCart $cart, Address $address, Collection $lines, ?float $shipping = null): array
    {
        if ($lines->isEmpty() || $lines->contains(fn ($line) => ! $line->product || (isset($line->valid) && ! $line->valid) || $line->qty > ($line->variant?->stock ?? $line->product->stock))) {
            throw new ApiFailure('stock_conflict', 'Update the unavailable items in your cart.');
        }
        app(CheckoutAvailability::class)->ensureServiceable($address, $lines);
        $shipping ??= app(DeliveryFeeService::class)->calculate($address, $lines);

        return ['cart_version' => $cart->version, 'address' => $address->getAttributes(), 'lines' => $lines->map(fn ($line) => ['product_id' => $line->product->id, 'variant_id' => $line->variant?->id, 'qty' => $line->qty, 'unit_price' => MobileViews::cents($line->unit_price), 'stock' => (int) ($line->variant?->stock ?? $line->product->stock), 'options' => $line->variant_options, 'shop_id' => $line->product->shop_id, 'sku' => $line->variant?->sku ?? $line->product->sku])->values()->all(), 'shipping' => MobileViews::cents($shipping), 'methods' => $this->methods()];
    }

    public function summary(array $snapshot, Address $address, string $id, $expires): array
    {
        $groups = collect($snapshot['lines'])->groupBy('shop_id');
        $shops = Shop::whereIn('id', $groups->keys())->get()->keyBy('id');
        $index = 0;
        $parts = $groups->map(function ($lines, $shopId) use ($groups, &$index, $snapshot, $shops) {
            return ['shop_id' => PublicIds::id($shops[$shopId]), 'subtotal' => $lines->sum(fn ($line) => $line['qty'] * $line['unit_price']), 'shipping_fee' => intdiv($snapshot['shipping'], $groups->count()) + ($index++ < $snapshot['shipping'] % $groups->count() ? 1 : 0)];
        })->values()->all();
        $subtotal = array_sum(array_column($parts, 'subtotal'));

        return ['id' => $id, 'cart_version' => $snapshot['cart_version'], 'address_id' => PublicIds::id($address), 'currency' => 'PHP', 'subtotal' => $subtotal, 'shipping_fee' => $snapshot['shipping'], 'total' => $subtotal + $snapshot['shipping'], 'shipments' => $parts, 'payment_methods' => $snapshot['methods'], 'expires_at' => MobileViews::time($expires)];
    }
}
