<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\MobileFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\TestCase;

class MobileAddressCheckoutTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($user, 'buyer')['access_token'], 'Idempotency-Key' => (string) Str::uuid()];
    }

    public function test_multi_shop_quote_matches_existing_shipping_remainder_rules(): void
    {
        config(['mobile.events_enabled' => false, 'notifications.enabled' => false]);
        $user = User::factory()->create();
        $headers = $this->headers($user);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $products = Product::factory()->count(3)->create(['stock' => 10, 'price' => 100]);
        foreach ($products->reverse()->values() as $version => $product) {
            $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 1, 'cart_version' => $version], $headers)->assertOk();
        }
        $quote = $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 3], $headers)->assertCreated()->assertJsonPath('data.shipping_fee', 4900)->json('data');
        $this->postJson('/api/v1/buyer/orders', ['quote_id' => $quote['id'], 'cart_version' => 3, 'payment_method' => 'cod'], $headers)->assertCreated();
        $actual = Order::sole()->fulfillments()->with('shop')->get()->mapWithKeys(fn ($part) => [$part->shop->public_id => (int) round($part->shipping_fee * 100)])->all();
        $expected = collect($quote['shipments'])->pluck('shipping_fee', 'shop_id')->all();
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_product_list_query_count_stays_bounded_as_rows_grow(): void
    {
        Product::factory()->count(25)->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/products?limit=25')->assertOk()->assertJsonCount(25, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(8, $queries, 'Catalogue serialization must not query each product separately.');
    }

    public function test_location_chain_addresses_and_owner_scope(): void
    {
        app(MobileFixtures::class)->seed();
        $user = User::where('email', 'mobile-buyer@farsell.test')->firstOrFail();
        $headers = $this->headers($user);
        $region = $this->getJson('/api/v1/locations/regions')->assertOk()->json('data.0.id');
        $province = $this->getJson('/api/v1/locations/provinces?region_id='.$region)->assertOk()->json('data.0.id');
        $city = $this->getJson('/api/v1/locations/cities?province_id='.$province)->assertOk()->json('data.0.id');
        $barangay = $this->getJson('/api/v1/locations/barangays?city_id='.$city)->assertOk()->json('data.0.id');
        $data = ['line1' => 'Test address only', 'region_id' => $region, 'province_id' => $province, 'city_id' => $city, 'barangay_id' => $barangay, 'is_default' => true];
        $id = $this->postJson('/api/v1/buyer/addresses', $data, $headers)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/buyer/addresses', $headers)->assertOk()->assertJsonCount(2, 'data');
        $this->putJson('/api/v1/buyer/addresses/'.$id, [...$data, 'line1' => 'Updated test address'], $headers)->assertOk()->assertJsonPath('data.line1', 'Updated test address');
        $other = $this->headers(User::factory()->create());
        $this->putJson('/api/v1/buyer/addresses/'.$id, $data, $other)->assertNotFound();
        $this->deleteJson('/api/v1/buyer/addresses/'.$id, [], $other)->assertNotFound();
        $this->deleteJson('/api/v1/buyer/addresses/'.$id, [], $headers)->assertNoContent();
    }

    public function test_quote_changes_reject_without_stock_or_order_mutation(): void
    {
        config(['mobile.events_enabled' => false]);
        $user = User::factory()->create();
        $headers = $this->headers($user);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 500, 'sku' => 'TEST-BKS1']);
        $cart = $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 1, 'cart_version' => 0], $headers)->assertOk()->json('data');
        $quote = $this->postJson('/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 1], $headers)->assertCreated()->json('data.id');
        $product->update(['price' => 550]);
        $this->postJson('/api/v1/buyer/orders', ['quote_id' => $quote, 'cart_version' => 1, 'payment_method' => 'cod'], $headers)->assertConflict()->assertJsonPath('code', 'quote_stale');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
        $this->patchJson('/api/v1/buyer/cart/lines/'.$cart['lines'][0]['id'], ['cart_version' => 1, 'qty' => 0], $headers)->assertOk()->assertJsonCount(0, 'data.lines');
    }

    public function test_production_ignores_staging_payment_flag_and_lists_are_paginated(): void
    {
        config(['mobile.events_enabled' => false, 'mobile.staging_payments' => true, 'app.url' => 'https://localhost']);
        $user = User::factory()->create();
        $headers = $this->headers($user);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['stock' => 2]);
        $this->postJson('/api/v1/buyer/cart/lines', ['product_id' => $product->public_id, 'qty' => 1, 'cart_version' => 0], $headers)->assertOk();
        $this->app->instance('env', 'production');
        $quote = $this->postJson('https://localhost/api/v1/buyer/checkout/quotes', ['address_id' => $address->public_id, 'cart_version' => 1], $headers)->assertCreated()->assertJsonPath('data.payment_methods', ['cod'])->json('data.id');
        $this->postJson('https://localhost/api/v1/buyer/orders', ['quote_id' => $quote, 'cart_version' => 1, 'payment_method' => 'gateway_stub'], $headers)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->app->instance('env', 'testing');
        Product::factory()->count(3)->create();
        $first = $this->getJson('/api/v1/products?limit=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('has_more', true)->json('next_cursor');
        $this->getJson('/api/v1/products?limit=2&cursor='.urlencode($first))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/products?limit=999')->assertUnprocessable();
    }
}
