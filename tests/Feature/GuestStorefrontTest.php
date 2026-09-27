<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Cart;
use App\Support\GuestSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_visit_opens_storefront_with_public_products_and_auth_links_without_guest_setup(): void
    {
        $product = Product::factory()->create(['name' => 'Public storefront listing']);
        $draft = Product::factory()->create(['name' => 'Unpublished listing', 'is_active' => false]);
        $inactiveShop = Shop::factory()->create(['is_active' => false]);
        $hidden = Product::factory()->create(['shop_id' => $inactiveShop->id, 'name' => 'Hidden shop listing']);

        $this->get('/')->assertOk()->assertViewIs('home')->assertSee($product->name)
            ->assertDontSee($draft->name)->assertDontSee($hidden->name)
            ->assertSee('Browse products')->assertSee('Explore shops')
            ->assertSee(route('login'), false)->assertSee(route('register'), false)
            ->assertDontSee('Continue as guest')->assertDontSee('Guest mode')
            ->assertDontSee('name="password"', false)->assertDontSee('action="'.route('guest.start').'"', false)
            ->assertSessionMissing(GuestSession::KEY);
        $this->assertGuest();
        $this->get('/home')->assertOk()->assertViewIs('home')->assertSee($product->name);
    }

    public function test_guest_can_browse_and_add_to_cart_then_sign_in_at_checkout_without_losing_cart(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $buyer = User::factory()->create(['password' => 'password']);
        Address::factory()->create(['user_id' => $buyer->id]);

        $this->get('/')->assertOk();
        $this->get(route('catalog.index'))->assertOk()->assertSee($product->name);
        $this->get(route('shops.index'))->assertOk()->assertSee($product->shop->name);
        $this->get(route('products.show', $product))->assertOk();
        $this->post(route('cart.store'), ['product_id' => $product->id, 'qty' => 2])->assertRedirect();
        $this->get(route('cart.index'))->assertOk()->assertSee($product->name);
        $this->assertSame([$product->id => 2], session(Cart::SESSION_KEY));
        $this->assertNull(session(GuestSession::KEY));
        $this->get(route('checkout.create'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Log in to checkout')->assertDontSee('Continue as guest');
        $this->post(route('login'), ['email' => $buyer->email, 'password' => 'password'])->assertRedirect(route('checkout.create'));
        $this->get(route('checkout.create'))->assertOk()->assertSee('Place order');
        $this->assertSame([$product->id => 2], session(Cart::SESSION_KEY));
    }

    public function test_default_guest_browsing_never_grants_account_order_or_application_access(): void
    {
        $this->get('/')->assertOk();
        foreach (['checkout.create', 'account.profile', 'orders.index', 'seller.apply', 'rider.register', 'admin.dashboard'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->post(route('checkout.store'), [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertGuest();
    }

    public function test_buyers_and_sellers_can_browse_while_private_roles_return_to_their_portals(): void
    {
        foreach ([UserRole::Buyer, UserRole::Seller] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/')->assertOk()->assertViewIs('home')->assertSee($user->name)
                ->assertSee(route('account.profile'), false)->assertDontSee('Continue as guest');
        }

        $rider = User::factory()->rider()->create();
        $this->actingAs($rider)->get('/')->assertRedirect(route('rider.dashboard'));

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
    }

    public function test_empty_storefront_and_legacy_guest_sessions_need_no_guest_banner(): void
    {
        $this->withSession([GuestSession::KEY => 'legacy-guest-id'])->get('/')->assertOk()
            ->assertSee('No products are available yet.')->assertDontSee('Guest mode')->assertDontSee('Continue as guest');
        $this->get(route('login'))->assertOk()->assertSee('Back to browsing')->assertDontSee('Continue as guest');
    }
}
