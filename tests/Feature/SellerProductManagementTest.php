<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seller(bool $active = true): User
    {
        $user = User::factory()->seller()->create();
        Shop::factory()->create(['user_id' => $user->id, 'is_active' => $active]);

        return $user;
    }

    private function data(array $extra = []): array
    {
        $category = Category::first() ?? Category::create(['name' => 'Collectibles']);

        return ['name' => 'Seller Listed Item', 'description' => 'An actual seller listing.', 'category_id' => $category->id, 'price' => '125.50', 'stock' => 5, 'is_active' => '1', ...$extra];
    }

    public function test_approval_to_listing_purchase_and_seller_order_visibility_end_to_end(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create();
        $this->actingAs($seller)->post(route('seller.apply.store'), ['shop_name' => 'Professor Demo Shop', 'city' => 'Manila', 'description' => 'Collectibles for a class demo'])->assertRedirect();
        $application = $seller->sellerApplication;
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->post(route('admin.sellers.review', $application), ['decision' => 'approve', 'confirmed' => '1', 'revision' => $application->revision])->assertSessionHasNoErrors();
        $seller->refresh();
        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()->assertSee('Add product');
        $this->post(route('seller.products.store'), $this->data(['image' => UploadedFile::fake()->image('product.jpg')]))->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::sole();
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertSame($seller->shop->id, $product->shop_id);
        $this->get(route('home'))->assertOk()->assertSee($product->name)->assertSee('storage/'.$product->image_path, false);
        $this->get(route('shops.show', $seller->shop))->assertOk()->assertSee($product->name);
        $this->get(route('products.show', $product))->assertOk()->assertSee('125.50');
        $buyer = User::factory()->create(['name' => 'Private Buyer', 'email' => 'private-buyer@example.test']);
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $this->actingAs($buyer)->post(route('cart.store'), ['product_id' => $product->id, 'qty' => 2])->assertSessionHasNoErrors();
        $this->get(route('checkout.create'))->assertOk()->assertSee('Place order');
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::sole();
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('251.00', $order->subtotal);
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee($order->number)->assertSee($product->name)
            ->assertDontSee($buyer->email)->assertDontSee($buyer->name)->assertDontSee($address->line1);
        $this->put(route('seller.products.update', $product), $this->data(['name' => 'Updated title', 'price' => '200', 'stock' => 3, 'version' => $product->fresh()->editVersion()]))->assertSessionHasNoErrors();
        $this->assertSame('Seller Listed Item', $order->items()->sole()->name);
        $this->assertSame('125.50', $order->items()->sole()->unit_price);
        $this->put(route('seller.products.update', $product), $this->data(['is_active' => 0, 'version' => $product->fresh()->editVersion(), 'stock' => 3]))->assertSessionHasNoErrors();
        $this->get(route('products.show', $product))->assertNotFound();
        $this->get(route('seller.dashboard'))->assertOk()->assertSee($order->number);
    }

    public function test_only_active_shop_owner_can_create_or_edit_even_with_tampered_ids(): void
    {
        $seller = $this->seller();
        $other = Product::factory()->create();
        $own = Product::factory()->create(['shop_id' => $seller->shop->id]);
        $this->get(route('seller.products.create'))->assertRedirect(route('login'));
        foreach ([UserRole::Buyer, UserRole::Rider, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            Shop::factory()->create(['user_id' => $user->id]);
            $this->actingAs($user)->get(route('seller.products.create'))->assertForbidden();
            $this->post(route('seller.products.store'), $this->data())->assertForbidden();
            $this->put(route('seller.products.update', $own), $this->data(['version' => $own->editVersion()]))->assertForbidden();
        }
        $this->actingAs($seller)->get(route('seller.products.edit', $other))->assertNotFound();
        $this->put(route('seller.products.update', $other), $this->data(['version' => $other->editVersion()]))->assertNotFound();
        $this->post(route('seller.products.store'), $this->data(['shop_id' => $other->shop_id, 'is_flash' => true, 'slug' => 'injected', 'image_path' => '../secret']))->assertSessionHasNoErrors();
        $new = Product::where('name', 'Seller Listed Item')->sole();
        $this->assertSame($seller->shop->id, $new->shop_id);
        $this->assertFalse($new->is_flash);
        $this->assertNotSame('injected', $new->slug);
        $this->assertNull($new->image_path);
        $this->actingAs($this->seller(false))->get(route('seller.products.create'))->assertForbidden();
        $this->post(route('seller.products.store'), $this->data())->assertForbidden();
    }

    public function test_drafts_publish_and_unpublish_without_deleting_product_or_order_history(): void
    {
        $seller = $this->seller();
        $this->actingAs($seller)->post(route('seller.products.store'), $this->data(['is_active' => 0]))->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->get(route('home'))->assertDontSee($product->name);
        $this->get(route('products.show', $product))->assertNotFound();
        $this->post(route('cart.store'), ['product_id' => $product->id])->assertNotFound();
        $this->put(route('seller.products.update', $product), $this->data(['version' => $product->editVersion()]))->assertSessionHasNoErrors();
        $this->get(route('catalog.index'))->assertOk()->assertSee($product->name);
        $this->put(route('seller.products.update', $product), $this->data(['is_active' => 0, 'version' => $product->fresh()->editVersion()]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
        $this->delete(route('seller.products.update', $product))->assertStatus(405);
        $this->get(route('seller.dashboard'))->assertOk()->assertSee($product->name);
    }

    public function test_stale_editor_cannot_restore_sold_stock_and_conflict_reload_shows_current_values(): void
    {
        $seller = $this->seller();
        $product = Product::factory()->create(['shop_id' => $seller->shop->id, 'stock' => 5]);
        $stale = $product->editVersion();
        $product->decrement('stock', 2);
        $this->actingAs($seller)->from(route('seller.products.edit', $product))->put(route('seller.products.update', $product), $this->data(['stock' => 5, 'version' => $stale]))->assertRedirect(route('seller.products.edit', $product));
        $this->assertSame(3, $product->fresh()->stock);
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('Current listing values have been reloaded')
            ->assertSee('value="3"', false)->assertDontSee('value="'.$stale.'"', false);
        $this->put(route('seller.products.update', $product), $this->data(['stock' => 4, 'version' => $product->fresh()->editVersion()]))->assertSessionHasNoErrors();
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_validation_error_retains_original_version_to_avoid_stale_stock_overwrite(): void
    {
        $seller = $this->seller();
        $product = Product::factory()->create(['shop_id' => $seller->shop->id, 'stock' => 5]);
        $version = $product->editVersion();
        $product->decrement('stock', 1);
        $this->actingAs($seller)->from(route('seller.products.edit', $product))->put(route('seller.products.update', $product), $this->data(['price' => -1, 'version' => $version]))->assertRedirect();
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('value="'.$version.'"', false);
        $this->putJson(route('seller.products.update', $product), $this->data(['version' => $version]))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_listing_validation_rejects_bad_prices_stock_category_and_non_image_uploads(): void
    {
        Storage::fake('public');
        $this->actingAs($this->seller());
        foreach ([['price' => 0], ['price' => '1.001'], ['stock' => -1], ['stock' => 1.5], ['category_id' => 9999], ['name' => ''], ['is_active' => 'approved'], ['image' => UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml')], ['image' => UploadedFile::fake()->image('large.jpg')->size(4097)]] as $invalid) {
            $this->postJson(route('seller.products.store'), $this->data($invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_hidden_shop_products_are_blocked_in_public_catalog_cart_and_stale_checkout(): void
    {
        $seller = $this->seller(false);
        $product = Product::factory()->create(['shop_id' => $seller->shop->id, 'name' => 'Hidden shop item']);
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        $this->actingAs($buyer)->get(route('home'))->assertDontSee($product->name);
        $this->get(route('catalog.index'))->assertDontSee($product->name);
        $this->get(route('products.show', $product))->assertNotFound();
        $this->post(route('cart.store'), ['product_id' => $product->id])->assertNotFound();
        $this->withSession([Cart::SESSION_KEY => [$product->id => 1]])->post(route('checkout.store'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame($product->stock, $product->fresh()->stock);
    }

    public function test_failed_create_rolls_back_database_and_removes_new_upload(): void
    {
        Storage::fake('public');
        $seller = $this->seller();
        Product::creating(function () {
            throw new \RuntimeException('Simulated failure');
        });
        try {
            $this->actingAs($seller)->post(route('seller.products.store'), $this->data(['image' => UploadedFile::fake()->image('item.jpg')]))->assertStatus(500);
            $this->assertDatabaseCount('products', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        } finally {
            Product::flushEventListeners();
        }
    }
}
