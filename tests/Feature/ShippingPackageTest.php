<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\MobileViews;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\TestCase;

class ShippingPackageTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase;

    private function product(array $extra = []): Product
    {
        return Product::factory()->create(['price' => '100', 'stock' => 10, 'length_cm' => '20', 'width_cm' => '30', 'height_cm' => '40', 'weight_kg' => '2.125', 'handling_flags' => ['fragile'], ...$extra]);
    }

    public function test_web_checkout_groups_packages_per_seller_and_preserves_flat_fee_and_old_snapshots(): void
    {
        config(['shipping.volumetric_divisor' => 5000]);
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $first = $this->product();
        $second = $this->product(['shop_id' => $first->shop_id, 'weight_kg' => 5, 'length_cm' => 10, 'width_cm' => 1, 'height_cm' => 1, 'handling_flags' => ['keep_dry']]);
        $third = $this->product(['weight_kg' => 120, 'length_cm' => 1, 'width_cm' => 1, 'height_cm' => 1]);
        $cart = [$first->id => 2, $second->id => 1, $third->id => 1];
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => $cart])->get('/checkout')->assertOk()->assertSee('Package estimate')->assertSee('9.602')->assertSee('Van / light truck');
        $this->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame('49.00', $order->shipping_fee);
        $this->assertSame('449.00', $order->total);
        $this->assertCount(2, $order->fulfillments);
        $part = $order->fulfillments->firstWhere('shop_id', $first->shop_id);
        $package = $part->shipping_package;
        $this->assertSame('9.250', $package['actual_weight_kg']);
        $this->assertSame('9.602', $package['volumetric_weight_kg']);
        $this->assertSame('9.602', $package['chargeable_weight_kg']);
        $this->assertSame('motorcycle', $package['vehicle_tier']);
        $this->assertSame(['fragile', 'keep_dry'], $package['handling_flags']);
        $this->assertSame('van_light_truck', $order->fulfillments->firstWhere('shop_id', $third->shop_id)->shipping_package['vehicle_tier']);
        $this->assertEquals(49, $order->fulfillments->sum('shipping_fee'));
        $first->update(['weight_kg' => 1000, 'height_cm' => 1000]);
        config(['shipping.volumetric_divisor' => 3500]);
        $this->assertEquals($package, $part->fresh()->shipping_package);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('9.602')->assertSee('5,000');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.logistics.index'))->assertOk()->assertSee('9.602');
    }

    public function test_missing_optional_dimensions_allow_checkout_without_a_fake_vehicle_choice(): void
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['price' => 100, 'stock' => 2]);
        $this->actingAs($buyer)->withSession([Cart::SESSION_KEY => [$product->id => 1]])->get('/checkout')->assertOk()->assertSee('Needs shipping details');
        $this->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $part = Order::sole()->fulfillments()->sole();
        $this->assertSame('needs_shipping_details', $part->shipping_package['status']);
        $this->assertNull($part->shipping_package['chargeable_weight_kg']);
        $this->assertNull($part->shipping_package['vehicle_tier']);
        $this->assertSame('149.00', $part->order->total);
        $part->update(['shipping_package' => null]);
        $this->get(route('orders.show', $part->order))->assertOk();
        $this->assertNull(MobileViews::shipment($part->fresh(), false)['shipping_package']);
        $this->assertNull(MobileViews::shipment($part->fresh(), true)['shipping_package']);
    }

    private function mobileQuote(): array
    {
        config(['shipping.volumetric_divisor' => 5000]);
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $product = $this->product();
        $headers = ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($buyer, 'buyer')['access_token']];
        $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 2, 'cart_version' => 0], $headers)->assertOk();
        $quote = $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 1], $headers)
            ->assertCreated()->assertJsonPath('data.shipping_fee', 4900)->assertJsonPath('data.total', 24900)
            ->assertJsonPath('data.shipments.0.shipping_package.volumetric_weight_kg', 9.6)->json('data.id');

        return compact('headers', 'quote', 'product');
    }

    public function test_mobile_quote_and_order_expose_same_package_and_unchanged_fees(): void
    {
        $s = $this->mobileQuote();
        $this->postJson('/api/v1/buyer/orders', ['quote_id' => $s['quote'], 'cart_version' => 1, 'payment_method' => 'cod'], [...$s['headers'], 'Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()->assertJsonPath('data.total', 24900)->assertJsonPath('data.shipments.0.shipping_package.chargeable_weight_kg', 9.6)
            ->assertJsonPath('data.shipments.0.shipping_package.vehicle_tier', 'motorcycle');
        $this->assertSame('9.600', Order::sole()->fulfillments()->sole()->shipping_package['chargeable_weight_kg']);
    }

    public function test_product_measurement_changes_stale_mobile_quotes_without_reserving_stock(): void
    {
        $s = $this->mobileQuote();
        $s['product']->update(['weight_kg' => 25]);
        $this->postJson('/api/v1/buyer/orders', ['quote_id' => $s['quote'], 'cart_version' => 1, 'payment_method' => 'cod'], [...$s['headers'], 'Idempotency-Key' => (string) Str::uuid()])
            ->assertConflict()->assertJsonPath('code', 'quote_stale');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $s['product']->fresh()->stock);
    }

    public function test_divisor_changes_stale_mobile_quotes_even_when_fee_is_identical(): void
    {
        $s = $this->mobileQuote();
        config(['shipping.volumetric_divisor' => 3500]);
        $this->postJson('/api/v1/buyer/orders', ['quote_id' => $s['quote'], 'cart_version' => 1, 'payment_method' => 'cod'], [...$s['headers'], 'Idempotency-Key' => (string) Str::uuid()])
            ->assertConflict()->assertJsonPath('code', 'quote_stale');
        $this->assertDatabaseCount('orders', 0);
    }
}
