<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_offers_login_and_registration_without_guest_action(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $this->withSession([Cart::SESSION_KEY => [$product->id => 2]])
            ->get(route('checkout.create'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Log in to checkout')
            ->assertSee('Create an account')->assertSee(route('register'), false)
            ->assertDontSee('Continue as guest')->assertDontSee(route('guest.start'), false);
        $this->get(route('register'))->assertOk()->assertSee('Create account and continue')
            ->assertSee(route('login'), false)->assertDontSee('Continue as guest');
        $this->assertSame([$product->id => 2], session(Cart::SESSION_KEY));
    }

    public function test_successful_login_returns_to_checkout_with_cart_intact(): void
    {
        $buyer = User::factory()->create(['password' => 'password']);
        Address::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['stock' => 4]);
        $this->withSession([Cart::SESSION_KEY => [$product->id => 2]])->get(route('checkout.create'));
        $this->post(route('login'), ['email' => $buyer->email, 'password' => 'password'])
            ->assertRedirect(route('checkout.create'));
        $this->get(route('checkout.create'))->assertOk()->assertSee('Place order');
        $this->assertSame([$product->id => 2], session(Cart::SESSION_KEY));
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_failed_login_keeps_the_checkout_prompt_and_cart(): void
    {
        $buyer = User::factory()->create(['password' => 'password']);
        $product = Product::factory()->create();
        $this->withSession([Cart::SESSION_KEY => [$product->id => 1]])->get(route('checkout.create'));
        $this->from(route('login'))->post(route('login'), ['email' => $buyer->email, 'password' => 'incorrect'])
            ->assertRedirect(route('login'))->assertSessionHasErrors(['email'], null, 'login');
        $this->get(route('login'))->assertOk()->assertSee('Log in to checkout')->assertDontSee('Continue as guest');
        $this->assertGuest();
        $this->assertSame([$product->id => 1], session(Cart::SESSION_KEY));
    }

    public function test_registration_returns_to_checkout_and_then_requests_an_address_without_losing_cart(): void
    {
        $product = Product::factory()->create();
        $this->withSession([Cart::SESSION_KEY => [$product->id => 1]])->get(route('checkout.create'));
        $this->post(route('register'), [
            'name' => 'New checkout buyer', 'email' => 'checkout@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123', 'intent' => 'buyer',
        ])->assertRedirect(route('checkout.create'));
        $this->assertAuthenticated();
        $this->get(route('checkout.create'))->assertRedirect(route('account.addresses.create'))
            ->assertSessionHas('checkout.needs_address', true);
        $this->assertSame([$product->id => 1], session(Cart::SESSION_KEY));
    }

    public function test_failed_registration_preserves_checkout_context(): void
    {
        $this->get(route('checkout.create'));
        $this->from(route('register'))->post(route('register'), [
            'name' => 'New buyer', 'email' => 'invalid',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('register'))->assertSessionHasErrors(['email'], null, 'register');
        $this->get(route('register'))->assertOk()->assertSee('Create account and continue');
        $this->get(route('login'))->assertSee('Log in to checkout')->assertDontSee('Continue as guest');
        $this->assertGuest();
    }

    public function test_regular_guest_browsing_remains_available_and_cannot_loop_into_checkout(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Back to browsing')->assertDontSee('Continue as guest');
        $this->get(route('welcome'))->assertOk()->assertSee('Browse products')->assertDontSee('Continue as guest');
        $this->get(route('checkout.create'));
        $this->post(route('guest.start'))->assertRedirect(route('home'))
            ->assertSessionMissing('url.intended');
        $this->assertGuest();
        $this->post(route('checkout.store'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('orders', 0);
    }
}
