<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function product(): array
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'has_variants' => true, 'stock' => 999]);
        $variant = $product->variants()->create(['sku' => 'LIVE-BKS1', 'options' => ['Size' => 'S', 'Color' => 'Black'], 'stock' => 3, 'is_active' => true]);
        $product->variants()->create(['sku' => 'HIDE-BLM2', 'options' => ['Size' => 'M', 'Color' => 'Blue'], 'stock' => 50, 'is_active' => false]);

        return [$seller, $shop, $product, $variant];
    }

    public function test_public_availability_is_uncached_current_and_excludes_inactive_variants(): void
    {
        [, $shop, $product, $variant] = $this->product();
        $this->getJson(route('products.availability', $product))->assertOk()->assertJsonPath('stock', 3)
            ->assertJsonCount(1, 'variants')->assertJsonPath('variants.0.sku', 'LIVE-BKS1')->assertDontSee('HIDE-BLM2')
            ->assertHeader('Cache-Control', 'no-store, private');
        $variant->update(['stock' => 0]);
        $this->getJson(route('products.availability', $product))->assertOk()->assertJsonPath('stock', 0)->assertJsonPath('variants.0.stock', 0);
        $shop->update(['is_active' => false]);
        $this->getJson(route('products.availability', $product))->assertNotFound()->assertDontSee('LIVE-BKS1');
        $shop->update(['is_active' => true]);
        $product->update(['is_active' => false]);
        $this->getJson(route('products.availability', $product))->assertNotFound();
    }

    public function test_seller_inventory_scopes_ids_and_stats_and_uses_active_variant_stock(): void
    {
        [$seller, , $product] = $this->product();
        $other = Product::factory()->create(['stock' => 321]);
        $this->actingAs($seller)->getJson(route('seller.inventory.stock', ['ids' => [$product->id, $other->id], 'shop_id' => $other->shop_id]))
            ->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $product->id)
            ->assertJsonPath('products.0.stock', 3)->assertJsonPath('stats.lowStock', 1)->assertJsonPath('stats.products', 1)
            ->assertDontSee($other->name)->assertDontSee('321')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/seller')->assertOk()->assertViewHas('products', fn ($rows) => $rows->first()->availableStock() === 3);
    }

    public function test_private_inventory_routes_enforce_roles_and_bound_request_size(): void
    {
        [, , $product] = $this->product();
        foreach (['seller.inventory.stock', 'admin.products.stock'] as $route) {
            $this->getJson(route($route))->assertUnauthorized();
        }
        foreach ([UserRole::Buyer, UserRole::Rider] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson(route('seller.inventory.stock'))->assertForbidden();
            $this->getJson(route('admin.products.stock'))->assertForbidden();
        }
        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.products.stock', ['ids' => [$product->id]]))
            ->assertOk()->assertJsonPath('products.0.stock', 3)->assertJsonPath('products.0.variants.1.active', false);
        $this->getJson(route('admin.products.stock', ['ids' => range(1, 21)]))->assertUnprocessable();
        $this->getJson(route('admin.products.stock', ['ids' => ['bad']]))->assertUnprocessable();
        $this->getJson(route('admin.products.stock', ['ids' => [$product->id, $product->id]]))->assertUnprocessable();
        $this->getJson(route('seller.inventory.stock'))->assertForbidden();
    }

    public function test_stock_refresh_reflects_checkout_reservation_and_cancellation_without_reserving_itself(): void
    {
        [, , $product, $variant] = $this->product();
        $product->update(['stock' => 3]);
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $url = route('products.availability', $product);
        $this->actingAs($buyer)->getJson($url)->assertOk()->assertJsonPath('stock', 3);
        $this->getJson($url)->assertOk()->assertJsonPath('stock', 3);
        $this->withSession([Cart::SESSION_KEY => ['v'.$variant->id => 2]])->post('/checkout', ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $this->getJson($url)->assertOk()->assertJsonPath('stock', 1);
        $this->post(route('orders.cancel', Order::sole()), ['reason' => 'Changed my mind before dispatch.'])->assertSessionHasNoErrors();
        $this->getJson($url)->assertOk()->assertJsonPath('stock', 3);
        $this->assertSame(3, $variant->fresh()->stock);
    }
}
