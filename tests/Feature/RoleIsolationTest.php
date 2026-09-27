<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_access_matches_each_role_boundary(): void
    {
        $this->get(route('home'))->assertOk();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $this->actingAs($buyer)->get(route('home'))->assertOk();

        $seller = User::factory()->seller()->create();
        $this->actingAs($seller)->get(route('home'))->assertOk();

        $rider = User::factory()->rider()->create();
        $this->actingAs($rider)->get(route('home'))->assertRedirect(route('rider.dashboard'));

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('home'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_only_guests_and_buyers_can_use_the_cart(): void
    {
        $this->get(route('cart.index'))->assertOk();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $this->actingAs($buyer)->get(route('cart.index'))->assertOk();

        foreach ([
            [UserRole::Seller, 'seller.dashboard'],
            [UserRole::Rider, 'rider.dashboard'],
            [UserRole::Admin, 'admin.dashboard'],
        ] as [$role, $destination]) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('cart.index'))->assertRedirect(route($destination));
        }
    }

    public function test_seller_can_browse_products_without_buyer_purchase_controls(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Seller accounts can browse products')
            ->assertDontSee('Add to cart')
            ->assertDontSee(route('cart.index'), false);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'qty' => 1])
            ->assertRedirect(route('seller.dashboard'));
    }

    public function test_role_dashboards_are_exactly_isolated(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->seller()->create();
        $rider = User::factory()->rider()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($buyer)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('rider.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();
        $this->actingAs($seller)->get(route('rider.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs($rider)->get(route('rider.dashboard'))->assertOk();
        $this->actingAs($rider)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($rider)->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('rider.dashboard'))->assertForbidden();
    }

    public function test_private_portals_do_not_render_public_store_navigation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Seller Approvals')->assertDontSee('href="'.route('home').'"', false);

        $rider = User::factory()->rider()->create();
        $this->actingAs($rider)->get(route('rider.dashboard'))
            ->assertOk()->assertSee('Delivery Requests')->assertDontSee('href="'.route('home').'"', false);

        $seller = User::factory()->seller()->create();
        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()->assertSee('View Storefront')->assertSee('href="'.route('home').'"', false)
            ->assertDontSee('href="'.route('cart.index').'"', false);
    }
}
