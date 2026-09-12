<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_a_profile(): void
    {
        $this->get(route('account.profile'))->assertRedirect(route('login'));
        $this->post(route('guest.start'))->assertRedirect();
        $this->get(route('account.profile'))->assertRedirect(route('login'));
    }

    public function test_profile_shows_only_the_signed_in_users_details_and_account_links(): void
    {
        $buyer = User::factory()->create(['name' => 'Buyer One', 'phone' => '09171234567']);
        $other = User::factory()->create(['name' => 'Other Buyer']);

        $this->actingAs($buyer)->get(route('account.profile', ['user_id' => $other->id]))
            ->assertOk()->assertSee('My Profile')->assertSee($buyer->name)
            ->assertSee($buyer->email)->assertSee($buyer->phone)
            ->assertDontSee($other->name)->assertDontSee($other->email)
            ->assertSee('My Orders')->assertSee(route('orders.index'), false)
            ->assertSee('My Addresses')->assertSee(route('account.addresses.index'), false)
            ->assertDontSee($buyer->password, false);
    }

    public function test_profile_handles_a_missing_phone_number(): void
    {
        $this->actingAs(User::factory()->create(['phone' => null]))
            ->get(route('account.profile'))->assertOk()->assertSee('Not provided');
    }

    public function test_orders_and_addresses_link_back_to_profile_and_navigation_no_longer_has_orders_shortcut(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['orders.index', 'account.addresses.index'] as $route) {
            $response = $this->get(route($route))->assertOk()
                ->assertSee('← My Profile')->assertSee(route('account.profile'), false);

            preg_match('/<nav\b[^>]*>(.*?)<\/nav>/s', $response->getContent(), $matches);
            $this->assertNotEmpty($matches);
            $this->assertStringContainsString(route('account.profile'), $matches[1]);
            $this->assertStringContainsString('aria-current="true"', $matches[1]);
            $this->assertStringNotContainsString(route('orders.index'), $matches[1]);
            $this->assertStringNotContainsString('My Orders', $matches[1]);
        }
    }
}
