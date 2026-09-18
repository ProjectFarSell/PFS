<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_profile_shows_own_storefront_and_dashboard_without_buyer_shortcuts(): void
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $other = Shop::factory()->create();

        $this->actingAs($seller)->get(route('account.profile', ['shop_id' => $other->id]))
            ->assertOk()->assertSee('Seller Profile')->assertSee($seller->name)->assertSee($seller->email)
            ->assertSee('Seller Dashboard')->assertSee(route('seller.dashboard'), false)
            ->assertSee('My Storefront')->assertSee(route('shops.show', $shop), false)
            ->assertDontSee($other->name)->assertDontSee(route('shops.show', $other), false)
            ->assertDontSee('My Orders')->assertDontSee('My Addresses')
            ->assertDontSee(route('orders.index'), false)->assertDontSee(route('account.addresses.index'), false);
    }

    public function test_seller_without_a_shop_has_helpful_setup_state(): void
    {
        $this->actingAs(User::factory()->seller()->create())->get(route('account.profile'))
            ->assertOk()->assertSee('Seller Profile')->assertSee('No shop linked yet')
            ->assertSee(route('seller.dashboard'), false)->assertDontSee('My Storefront')
            ->assertDontSee('My Orders')->assertDontSee('My Addresses');
    }

    public function test_inactive_storefront_has_no_broken_public_link(): void
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'is_active' => false]);
        $this->actingAs($seller)->get(route('account.profile'))->assertOk()->assertSee('Shop inactive')
            ->assertSee(route('seller.dashboard'), false)->assertDontSee(route('shops.show', $shop), false);
    }

    public function test_buyer_profile_keeps_purchase_and_address_shortcuts(): void
    {
        $this->actingAs(User::factory()->create())->get(route('account.profile'))->assertOk()
            ->assertSee('My Orders')->assertSee('My Addresses')->assertDontSee('Seller Dashboard')
            ->assertDontSee('My Storefront')->assertDontSee('Seller Profile');
    }
}
