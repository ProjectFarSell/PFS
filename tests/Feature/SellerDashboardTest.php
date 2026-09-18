<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_buyers_and_riders_cannot_access_seller_data(): void
    {
        $this->get(route('seller.dashboard'))->assertRedirect(route('login'));
        foreach ([UserRole::Buyer, UserRole::Rider] as $role) {
            $user = User::factory()->create(['role' => $role]);
            Shop::factory()->create(['user_id' => $user->id]);
            $this->actingAs($user)->get(route('seller.dashboard'))->assertForbidden();
            $this->get(route('account.profile'))->assertDontSee(route('seller.dashboard'), false);
        }
    }

    public function test_seller_without_shop_gets_setup_message_not_other_shop_data(): void
    {
        $other = Shop::factory()->create(['name' => 'Unrelated shop']);
        $seller = User::factory()->seller()->create();
        $this->actingAs($seller)->get(route('seller.dashboard', ['shop_id' => $other->id]))
            ->assertOk()->assertSee('No shop linked to your account')->assertDontSee($other->name);
    }

    public function test_dashboard_scopes_products_and_mixed_order_items_and_omits_buyer_private_data(): void
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $own = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Owned inventory item', 'stock' => 2]);
        $inactive = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => false, 'stock' => 0]);
        $other = Product::factory()->create(['name' => 'OTHER SELLER INVENTORY']);
        $buyer = User::factory()->create(['name' => 'PRIVATE BUYER NAME', 'email' => 'private@example.test', 'phone' => 'PRIVATE PHONE']);
        $order = $this->order($buyer);
        $this->item($order, $own, 'OWN ORDER SNAPSHOT');
        $this->item($order, $other, 'OTHER SELLER ORDER ITEM');
        $this->item($order, null, 'DELETED PRODUCT SNAPSHOT');

        $response = $this->actingAs($seller)->get(route('seller.dashboard', ['shop_id' => $other->shop_id, 'user_id' => $buyer->id]))
            ->assertOk()->assertSee($shop->name)->assertSee($own->name)->assertSee($inactive->name)
            ->assertSee('OWN ORDER SNAPSHOT')->assertSee($order->number)->assertSee('Awaiting payment')
            ->assertDontSee($other->name)->assertDontSee('OTHER SELLER ORDER ITEM')->assertDontSee('DELETED PRODUCT SNAPSHOT')
            ->assertDontSee($buyer->name)->assertDontSee($buyer->email)->assertDontSee($buyer->phone)
            ->assertDontSee('PRIVATE DELIVERY ADDRESS')->assertDontSee('9,876.54')
            ->assertDontSee(route('orders.show', $order), false)
            ->assertViewHas('stats', ['products' => 2, 'active' => 1, 'lowStock' => 1, 'orderItems' => 1]);

        $loadedOrder = $response->viewData('items')->first()->order;
        $this->assertEqualsCanonicalizing(['id', 'number', 'status', 'created_at'], array_keys($loadedOrder->getAttributes()));
        $this->get(route('orders.show', $order))->assertForbidden();
        $this->assertSame(2, $own->fresh()->stock);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_admin_access_is_also_limited_to_their_own_shop(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $own = Shop::factory()->create(['user_id' => $admin->id, 'name' => 'Admin owned shop']);
        $other = Product::factory()->create(['name' => 'OTHER SHOP PRIVATE INVENTORY']);
        $this->actingAs($admin)->get(route('seller.dashboard', ['shop_id' => $other->shop_id]))
            ->assertOk()->assertSee($own->name)->assertDontSee($other->name);
    }

    public function test_inactive_shop_still_has_private_inventory_and_useful_empty_states(): void
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'is_active' => false]);
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee('Shop inactive')
            ->assertSee('No products in your shop yet.')->assertSee('No order items linked to your products yet.')
            ->assertDontSee(route('shops.show', $shop), false);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $this->get(route('seller.dashboard'))->assertOk()->assertSee($product->name)
            ->assertDontSee(route('products.show', $product), false);
    }

    public function test_inventory_and_items_have_independent_stable_pagination(): void
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $order = $this->order(User::factory()->create());
        $products = collect(range(1, 11))->map(fn ($number) => Product::factory()->create([
            'shop_id' => $shop->id, 'name' => sprintf('Inventory %02d', $number),
        ]));
        foreach ($products as $index => $product) {
            $this->item($order, $product, sprintf('Snapshot %02d', $index + 1));
        }
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()
            ->assertSeeInOrder($products->reverse()->take(10)->pluck('name')->all())
            ->assertDontSee('Inventory 01')->assertDontSee('Snapshot 01')
            ->assertViewHas('products', fn ($rows) => $rows->total() === 11 && $rows->count() === 10)
            ->assertViewHas('items', fn ($rows) => $rows->total() === 11 && $rows->count() === 10);
        $this->get(route('seller.dashboard', ['products_page' => 2]))->assertOk()
            ->assertSee('Inventory 01')->assertDontSee('Inventory 11')->assertSee('Snapshot 11');
        $this->get(route('seller.dashboard', ['items_page' => 2]))->assertOk()
            ->assertSee('Snapshot 01')->assertDontSee('Snapshot 11')->assertSee('Inventory 11');
    }

    public function test_seller_navigation_is_available_but_mutations_are_not(): void
    {
        $seller = User::factory()->seller()->create();
        $this->actingAs($seller)->get(route('account.profile'))->assertOk()
            ->assertSee('Seller Dashboard')->assertSee(route('seller.dashboard'), false);
        $this->post(route('seller.dashboard'), ['stock' => 999])->assertStatus(405);
    }

    private function order(User $buyer): Order
    {
        return $buyer->orders()->create([
            'number' => 'SELLER-'.fake()->unique()->numerify('########'),
            'status' => OrderStatus::PendingPayment, 'payment_method' => PaymentMethod::Cod,
            'ship_to' => 'PRIVATE DELIVERY ADDRESS', 'subtotal' => 9827.54, 'shipping_fee' => 49, 'total' => 9876.54,
        ]);
    }

    private function item(Order $order, ?Product $product, string $name): void
    {
        $order->items()->create(['product_id' => $product?->id, 'name' => $name, 'qty' => 2, 'unit_price' => 50, 'line_total' => 100]);
    }
}
