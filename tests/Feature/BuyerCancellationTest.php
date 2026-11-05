<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BuyerCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function placed(): array
    {
        config(['notifications.enabled' => false]);
        $buyer = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => User::factory()->seller()->create()->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'stock' => 5]);
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 2]])->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = Order::query()->sole();

        return [$buyer, $product, $order, $order->fulfillments()->sole()];
    }

    public function test_buyer_cancels_and_stock_and_event_are_recorded_once(): void
    {
        [$buyer, $product, $order, $part] = $this->placed();
        $this->get(route('orders.show', $order))->assertSee('Confirm cancellation');
        $this->post(route('orders.cancel', $order), ['reason' => 'Ordered by mistake'])->assertSessionHasNoErrors();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('cancelled', $part->fresh()->status->value);
        $this->assertSame('Ordered by mistake', $order->fresh()->cancellation_reason);
        $this->assertEquals(0, $part->fresh()->amount());
        $this->post(route('orders.cancel', $order), ['reason' => 'Duplicate request'])->assertSessionHasNoErrors();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(1, $part->events()->where('status', 'cancelled')->count());
        config(['services.invoice_generator.api_key' => 'fake-key']);
        Http::preventStrayRequests();
        $this->get(route('orders.print', ['order' => $order, 'document' => 'invoice']))->assertOk()->assertSee('no payment due')->assertSee('PHP 0.00');
        $this->assertCount(0, $order->fresh()->billableItems());
        $this->actingAs($part->shop->owner)->post(route('fulfillments.update', $part), ['action' => 'accept'])->assertSessionHasErrors('fulfillment');
    }

    public function test_late_cancellation_wrong_owner_and_wrong_role_are_blocked(): void
    {
        [$buyer, $product, $order, $part] = $this->placed();
        $this->actingAs(User::factory()->create())->post(route('orders.cancel', $order), ['reason' => 'Not my order'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->post(route('orders.cancel', $order), ['reason' => 'Not a buyer'])->assertForbidden();
        $part->update(['status' => 'assigned']);
        $this->actingAs($buyer)->post(route('orders.cancel', $order), ['reason' => 'Too late now'])->assertSessionHasErrors('cancellation');
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_already_rejected_shipment_is_not_restocked_twice(): void
    {
        [$buyer, $product, $order, $part] = $this->placed();
        $other = $order->fulfillments()->create(['shop_id' => Shop::factory()->create()->id, 'shop_name' => 'Other shipment', 'status' => 'pending', 'subtotal' => 0, 'shipping_fee' => 0]);
        $this->actingAs($part->shop->owner)->post(route('fulfillments.update', $part), ['action' => 'reject', 'reason' => 'Unavailable'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('orders.cancel', $order), ['reason' => 'Cancel remaining items'])->assertSessionHasNoErrors();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('rejected', $part->fresh()->status->value);
        $this->assertSame('cancelled', $other->fresh()->status->value);
    }

    public function test_variant_stock_is_restored_once_even_after_seller_acceptance(): void
    {
        [$buyer, $product, $order, $part] = $this->placed();
        // Convert the existing reservation into a variant reservation for this check.
        $product->update(['has_variants' => true]);
        $variant = ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'TEST-BKS', 'option_name' => 'Size', 'option_value' => 'S', 'options' => ['Size' => 'S'], 'stock' => 3, 'is_active' => true]);
        $order->items()->update(['product_variant_id' => $variant->id]);
        $this->actingAs($part->shop->owner)->post(route('fulfillments.update', $part), ['action' => 'accept'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('orders.cancel', $order), ['reason' => 'Wrong size selected'])->assertSessionHasNoErrors();
        $this->post(route('orders.cancel', $order), ['reason' => 'Repeated request'])->assertSessionHasNoErrors();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(5, $variant->fresh()->stock);
    }
}
