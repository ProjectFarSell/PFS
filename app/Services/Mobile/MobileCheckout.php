<?php

namespace App\Services\Mobile;

use App\Contracts\DeliveryFeeService;
use App\Models\Address;
use App\Models\MobileCart;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\CheckoutAvailability;
use App\Services\Orders\MarketplaceSettings;
use App\Services\Orders\SettlementService;
use App\Services\Orders\VoucherService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MobileCheckout
{
    public function methods(): array
    {
        return app()->environment('staging') && config('mobile.staging_payments') ? ['cod', 'gateway_stub'] : ['cod'];
    }

    public function snapshot(MobileCart $cart, Address $address, Collection $lines, ?float $shipping = null, ?string $voucherCode = null): array
    {
        app(MarketplaceSettings::class)->current(true);
        if ($lines->isEmpty() || $lines->contains(fn ($line) => ! $line->product || (isset($line->valid) && ! $line->valid) || $line->qty > ($line->variant?->stock ?? $line->product->stock))) {
            throw new ApiFailure('stock_conflict', 'Update the unavailable items in your cart.');
        }
        app(CheckoutAvailability::class)->ensureServiceable($address, $lines);
        $shipping ??= app(DeliveryFeeService::class)->calculate($address, $lines);
        $voucher = app(VoucherService::class)->quote($voucherCode, User::findOrFail($cart->user_id), $lines, MobileViews::cents($shipping), DB::transactionLevel() > 0);

        return ['cart_version' => $cart->version, 'address' => $address->getAttributes(), 'lines' => $lines->map(fn ($line) => ['product_id' => $line->product->id, 'variant_id' => $line->variant?->id, 'qty' => $line->qty, 'unit_price' => MobileViews::cents($line->unit_price), 'stock' => (int) ($line->variant?->stock ?? $line->product->stock), 'options' => $line->variant_options, 'shop_id' => $line->product->shop_id, 'sku' => $line->variant?->sku ?? $line->product->sku])->values()->all(), 'shipping' => MobileViews::cents($shipping), 'methods' => $this->methods(), 'fee_policy' => app(SettlementService::class)->policy(), 'voucher' => $voucher];
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

        $discount = $snapshot['voucher']['discount_cents'] ?? 0;
        $shippingDiscount = $snapshot['voucher']['shipping_discount_cents'] ?? 0;

        return ['id' => $id, 'cart_version' => $snapshot['cart_version'], 'address_id' => PublicIds::id($address), 'currency' => 'PHP', 'subtotal' => $subtotal, 'shipping_fee' => $snapshot['shipping'], 'discount' => $discount, 'shipping_discount' => $shippingDiscount, 'voucher_code' => $snapshot['voucher']['code'] ?? null, 'total' => $subtotal + $snapshot['shipping'] - $discount - $shippingDiscount, 'shipments' => $parts, 'payment_methods' => $snapshot['methods'], 'expires_at' => MobileViews::time($expires)];
    }
}
