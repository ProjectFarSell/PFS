<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalNavigationViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menu_contains_all_destinations_and_no_dashboard_sidebar(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('admin.dashboard'))->assertOk();
        foreach (['admin.dashboard', 'admin.logistics.index', 'admin.products.index', 'admin.categories.index', 'admin.partners.index', 'admin.payouts.index', 'admin.vouchers.index', 'admin.remittances.index', 'admin.marketplace-settings.edit', 'admin.riders.barangays.index', 'admin.rider-records.index', 'admin.reviews.index', 'admin.notifications.index', 'fulfillments.index', 'account.profile'] as $name) {
            $response->assertSee('href="'.route($name).'"', false);
        }
        $response->assertDontSee('aria-label="Dashboard navigation"', false)->assertSee('@keydown.escape.stop.prevent', false);
    }

    public function test_seller_menu_links_are_present_only_for_a_shop_owner(): void
    {
        $seller = User::factory()->seller()->create();
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertDontSee('href="'.route('seller.products.create').'"', false);
        Shop::factory()->create(['user_id' => $seller->id]);
        $response = $this->actingAs($seller->fresh())->get(route('seller.dashboard'))->assertOk();
        foreach (['seller.dashboard', 'seller.products.create', 'seller.payouts.index', 'seller.vouchers.index', 'fulfillments.index', 'chat.index', 'home', 'account.profile'] as $name) {
            $response->assertSee('href="'.route($name).'"', false);
        }
        $response->assertDontSee('aria-label="Dashboard navigation"', false);
    }

    public function test_rider_menu_and_profile_controls_are_present_without_sidebar(): void
    {
        $rider = User::factory()->rider()->create();
        $response = $this->actingAs($rider)->get(route('rider.dashboard'))->assertOk();
        foreach (['rider.dashboard', 'rider.earnings.index', 'rider.cod.index', 'rider.history.index', 'fulfillments.index', 'rider.profile', 'account.profile'] as $name) {
            $response->assertSee('href="'.route($name).'"', false);
        }
        $response->assertSee(route('rider.dashboard').'#delivery-requests', false)->assertDontSee('aria-label="Dashboard navigation"', false);
        $this->get(route('account.profile'))->assertOk()->assertSee('My Profile')->assertDontSee('<aside', false);
    }
}
