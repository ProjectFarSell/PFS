<?php

namespace Tests\Feature;

use App\Contracts\DeliveryFeeService;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_owned_saved_address_and_reserves_stock_transactionally(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id, 'city' => 'Quezon City', 'region' => 'NCR']);
        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);

        $response = $this->actingAs($user)
            ->withSession([Cart::SESSION_KEY => [$product->id => 2]])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod']);

        $order = Order::query()->sole();

        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame($address->id, $order->address_id);
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('49.00', $order->shipping_fee);
        $this->assertSame('249.00', $order->total);
        $this->assertNotNull($order->stock_reserved_at);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame([], session(Cart::SESSION_KEY, []));
    }

    public function test_failed_stock_check_rolls_back_order_and_keeps_cart(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['stock' => 1]);

        $response = $this->actingAs($user)
            ->withSession([Cart::SESSION_KEY => [$product->id => 2]])
            ->from('/checkout')
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod']);

        $response->assertRedirect('/checkout')->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertSame([$product->id => 2], session(Cart::SESSION_KEY));
    }

    public function test_user_cannot_checkout_with_another_users_address(): void
    {
        $user = User::factory()->create();
        $otherAddress = Address::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);

        $this->actingAs($user)
            ->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->post('/checkout', ['address_id' => $otherAddress->id, 'payment_method' => 'cod'])
            ->assertSessionHasErrors('address_id');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_checkout_uses_the_delivery_fee_service_contract(): void
    {
        $this->app->instance(DeliveryFeeService::class, new class implements DeliveryFeeService
        {
            public function calculate(Address $address, Collection $lines): float
            {
                return 75.00;
            }
        });

        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['price' => 100, 'stock' => 1]);

        $this->actingAs($user)
            ->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'gateway_stub']);

        $order = Order::query()->sole();
        $this->assertSame('75.00', $order->shipping_fee);
        $this->assertSame('175.00', $order->total);
        $this->assertSame(OrderStatus::Paid, $order->status);
    }
}
