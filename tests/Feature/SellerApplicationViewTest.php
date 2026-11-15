<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerApplicationViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_states_hide_shop_navigation(): void
    {
        $seller = User::factory()->seller()->create();
        $this->actingAs($seller);
        foreach ([
            [null, 'Open your shop', 'Apply to open a shop'],
            [(object) ['status' => 'pending', 'submitted_at' => now(), 'review_note' => null], 'Application under review', 'View submitted details'],
            [(object) ['status' => 'rejected', 'submitted_at' => now(), 'review_note' => 'Missing details'], 'Application not approved', 'Reapply'],
        ] as [$application, $heading, $button]) {
            $view = $this->view('seller.dashboard', ['shop' => null, 'application' => $application]);
            $view->assertSee($heading)->assertSee($button)->assertDontSee('Manage shop orders')->assertDontSee('Add Product')->assertDontSee('View Storefront')->assertDontSee('Chat');
            if ($application?->status === 'pending') {
                $view->assertDontSee('Apply to open a shop');
            }
        }
    }

    public function test_seller_with_shop_keeps_dashboard_navigation(): void
    {
        $seller = User::factory()->seller()->create();
        Shop::factory()->create(['user_id' => $seller->id]);
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee('Add Product')->assertSee('Orders')->assertSee('Chat')->assertSee('View Storefront')->assertSee('Manage shop orders');
    }
}
