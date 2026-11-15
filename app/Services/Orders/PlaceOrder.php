<?php

namespace App\Services\Orders;

use App\Contracts\DeliveryFeeService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Models\Order;
use App\Models\ReplacementRequest;
use App\Models\User;
use App\Services\Notifications\OrderNotificationService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    public function handle(User $buyer, Address $address, Collection $lines, PaymentMethod $payment, ?ReplacementRequest $replacement = null, ?callable $verify = null): Order
    {
        return DB::transaction(function () use ($buyer, $address, $lines, $payment, $replacement, $verify) {
            // One settings version is locked throughout pricing, quote recheck and snapshots.
            app(MarketplaceSettings::class)->current(true);
            $policy = app(SettlementService::class)->policy();
            if ($replacement && $payment !== PaymentMethod::Cod) {
                throw ValidationException::withMessages(['payment_method' => 'Replacement orders use cash on delivery.']);
            }
            $stock = app(StockReservationService::class);
            $locked = $stock->lockAndPrepare($lines);
            app(CheckoutAvailability::class)->ensureServiceable($address, $locked);
            $shipping = app(DeliveryFeeService::class)->calculate($address, $locked);
            if ($verify) {
                $verify($locked, $shipping);
            }
            $subtotalCents = $locked->sum(fn ($line) => Money::cents((string) $line->unit_price) * (int) $line->qty);
            $shippingCents = Money::cents((string) $shipping);
            $policy['delivery_fee_cents'] = $shippingCents;
            $order = Order::create(['user_id' => $buyer->id, 'address_id' => $address->id, 'number' => 'FS'.now()->format('ymd').Str::upper(Str::random(6)), 'status' => $payment === PaymentMethod::Cod ? OrderStatus::PendingPayment : OrderStatus::Paid, 'payment_method' => $payment, 'guest_email' => $buyer->email, 'guest_name' => $buyer->name, 'ship_to' => $address->formatted().($address->phone ? ' · '.$address->phone : ''), 'subtotal' => Money::decimal($subtotalCents), 'shipping_fee' => Money::decimal($shippingCents), 'total' => Money::decimal($subtotalCents + $shippingCents), 'fee_policy' => $policy, 'stock_reserved_at' => now()]);
            foreach ($locked as $line) {
                $order->items()->create(['product_id' => $line->product->id, 'product_variant_id' => $line->variant?->id, 'name' => $line->product->name, 'product_sku' => $line->variant?->sku ?? $line->product->sku, 'variant_options' => $line->variant_options, 'qty' => $line->qty, 'unit_price' => $line->unit_price, 'line_total' => $line->line_total]);
            }
            $stock->reserve($locked);
            app(FulfillmentService::class)->createForOrder($order);
            app(OrderNotificationService::class)->orderPlaced($order);
            $replacement?->forceFill(['status' => 'ordered', 'new_order_id' => $order->id])->save();

            return $order;
        }, 3);
    }
}
