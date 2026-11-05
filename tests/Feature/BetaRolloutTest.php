<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Orders\CheckoutAvailability;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BetaRolloutTest extends TestCase
{
    use RefreshDatabase;

    public function test_paused_checkout_keeps_the_cart_and_does_not_reserve_stock(): void
    {
        config(['release.checkout_enabled' => false]);
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['stock' => 5]);
        $this->actingAs($user)->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->post(route('checkout.store'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertServiceUnavailable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame([$product->id => 1], session(Cart::SESSION_KEY));
    }

    public function test_beta_checks_both_delivery_and_pickup_cities_and_fails_closed_when_unconfigured(): void
    {
        config(['release.beta_enabled' => true, 'release.beta_city_ids' => [101]]);
        $address = new Address(['psgc_city_municipality_id' => 101]);
        $product = new Product;
        $shop = new Shop(['psgc_city_municipality_id' => 101]);
        $product->setRelation('shop', $shop);
        $lines = collect([(object) ['product' => $product]]);
        $policy = app(CheckoutAvailability::class);
        $policy->ensureServiceable($address, $lines);
        foreach (['delivery', 'pickup', 'empty'] as $case) {
            $address->psgc_city_municipality_id = $case === 'delivery' ? 202 : 101;
            $shop->psgc_city_municipality_id = $case === 'pickup' ? 202 : 101;
            config(['release.beta_city_ids' => $case === 'empty' ? [] : [101]]);
            try {
                $policy->ensureServiceable($address, $lines);
                $this->fail('Unsupported beta route was allowed.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('address_id', $exception->errors());
            }
        }
    }

    public function test_demo_gateway_is_hidden_and_cannot_be_posted_when_disabled(): void
    {
        config(['release.allow_demo_payments' => false]);
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['stock' => 5]);
        $this->actingAs($user)->withSession([Cart::SESSION_KEY => [$product->id => 1]])->get(route('checkout.create'))->assertOk()->assertDontSee('value="gateway_stub"', false);
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'payment_method' => 'gateway_stub'])->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }
}
