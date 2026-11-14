<?php

namespace App\Services\Mobile;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;

class MobileViews
{
    public static function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    public static function time($value): ?string
    {
        return $value?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    public static function product(Product $product): array
    {
        return ['id' => PublicIds::id($product), 'name' => $product->name, 'slug' => $product->slug, 'description' => $product->description ?? '', 'sku' => $product->sku, 'unit_price' => self::cents($product->price), 'stock' => $product->availableStock(), 'has_variants' => $product->has_variants, 'image_url' => $product->imageUrl(), 'shop' => ['id' => PublicIds::id($product->shop), 'name' => $product->shop->name, 'slug' => $product->shop->slug], 'category' => ['id' => PublicIds::id($product->category), 'name' => $product->category->name, 'slug' => $product->category->slug], 'variants' => $product->variants->where('is_active', true)->map(fn ($v) => ['id' => PublicIds::id($v), 'sku' => $v->sku, 'options' => (object) ($v->options ?: array_filter([$v->option_name => $v->option_value])), 'unit_price' => self::cents($v->price_override ?? $product->price), 'stock' => (int) $v->stock])->values()->all()];
    }

    public static function address(Address $address): array
    {
        return ['id' => PublicIds::id($address), 'label' => $address->label, 'line1' => $address->line1, 'region_id' => PublicIds::id($address->psgcRegion), 'province_id' => PublicIds::id($address->psgcProvince), 'city_id' => PublicIds::id($address->psgcCityMunicipality), 'barangay_id' => PublicIds::id($address->psgcBarangay), 'postal_code' => $address->postal_code, 'phone' => $address->phone, 'is_default' => $address->is_default, 'formatted' => $address->formatted()];
    }

    public static function order(Order $order, bool $detail = true): array
    {
        $data = ['id' => PublicIds::id($order), 'number' => $order->number, 'status' => $order->status->value, 'payment_method' => $order->payment_method->value, 'currency' => 'PHP', 'subtotal' => self::cents($order->subtotal), 'shipping_fee' => self::cents($order->shipping_fee), 'total' => self::cents($order->total), 'created_at' => self::time($order->created_at)];
        if ($detail) {
            $order->loadMissing(['fulfillments.items', 'fulfillments.events', 'fulfillments.courierBooking']);
            $data['ship_to'] = $order->ship_to;
            $data['can_cancel'] = $order->canBuyerCancel();
            $data['shipments'] = $order->fulfillments->map(function ($part) use ($order) {
                $part->setRelation('order', $order);

                return self::shipment($part, false);
            })->all();
        }

        return $data;
    }

    public static function shipment(Fulfillment $part, bool $rider): array
    {
        $part->loadMissing(['order', 'items', 'events', 'courierBooking']);
        $actions = [];
        if ($rider) {
            $actions = match ($part->status) {
                FulfillmentStatus::Assigned => ['pickup'],
                FulfillmentStatus::InTransit => $part->retry_at?->isFuture() ? [] : ($part->cod_collected_at ? ['deliver'] : ['deliver', 'report_failure']),
                default => [],
            };
        } elseif ($part->status === FulfillmentStatus::Delivered && (! $part->usesExternalCourier() || $part->order->payment_method !== PaymentMethod::Cod || $part->cod_collected_at || $part->courierBooking?->isSandbox())) {
            $actions = ['confirm_receipt'];
        }
        $items = $part->items->map(fn ($item) => ['id' => PublicIds::id($item), 'name' => $item->name, 'sku' => $item->product_sku, 'options' => (object) ($item->variant_options ?? []), 'qty' => (int) $item->qty, 'unit_price' => self::cents($item->unit_price), 'line_total' => self::cents($item->line_total)])->all();
        $events = $part->events->map(fn ($event) => ['id' => PublicIds::id($event), 'status' => $event->status, 'occurred_at' => self::time($event->created_at), 'message' => FulfillmentStatus::from($event->status)->label()])->all();
        if ($rider) {
            $operational = in_array($part->status->value, ['assigned', 'in_transit', 'delivery_failed', 'returning'], true);

            return ['id' => PublicIds::id($part), 'order_number' => $part->order->number, 'status' => $part->status->value, 'shop_name' => $part->shop_name, 'pickup_address' => $operational ? (string) $part->pickup_address : '', 'pickup_contact' => $operational ? (string) $part->pickup_contact : '', 'dropoff_address' => $operational ? (string) $part->order->ship_to : '', 'recipient_name' => $operational ? (string) $part->order->guest_name : '', 'recipient_phone' => null, 'payment_method' => $part->order->payment_method->value, 'cod_amount_due' => $part->order->payment_method === PaymentMethod::Cod && ! $part->cod_collected_at ? self::cents($part->amount()) : 0, 'items' => $items, 'events' => $events, 'retry_at' => self::time($part->retry_at), 'allowed_actions' => $actions, 'return_instructions' => $part->status === FulfillmentStatus::Returning ? 'Return the shipment to the seller as instructed by dispatch. Do not collect COD.' : ($part->status === FulfillmentStatus::DeliveryFailed ? 'Wait for dispatch to review the failed attempt.' : null)];
        }
        $data = ['id' => PublicIds::id($part), 'status' => $part->status->value, 'shop_name' => $part->shop_name, 'delivery_method' => $part->delivery_method?->value ?? 'internal', 'subtotal' => self::cents($part->subtotal), 'shipping_fee' => self::cents($part->shipping_fee), 'cod_amount_due' => $part->order->payment_method === PaymentMethod::Cod && ! $part->cod_collected_at ? self::cents($part->amount()) : 0, 'items' => $items, 'events' => $events, 'tracking_url' => $part->courierBooking?->tracking_url, 'proof' => $part->delivery_recipient_name ? ['recipient_name' => $part->delivery_recipient_name, 'delivery_note' => (string) $part->delivery_note, 'delivered_at' => self::time($part->events->firstWhere('status', 'delivered')?->created_at ?? $part->updated_at), 'has_photo' => filled($part->delivery_photo_path), 'cod_collected_at' => self::time($part->cod_collected_at)] : null, 'allowed_actions' => $actions];
        if ($part->status === FulfillmentStatus::InTransit) {
            $data['delivery_pin'] = $part->delivery_pin;
        }

        return $data;
    }
}
