<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@test.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
    }

    public function test_storefront_auth_links_open_combined_portal_with_correct_tab(): void
    {
        $this->get('/')->assertViewIs('home');
        $this->get('/login')->assertViewIs('welcome')->assertViewHas('initialTab', 'login')->assertSee("tab: 'login'", false)->assertDontSee('Continue as guest');
        $this->get('/register')->assertViewIs('welcome')->assertViewHas('initialTab', 'register')->assertSee("tab: 'register'", false);
    }

    public function test_combined_portal_returns_to_registration_tab_after_validation_error(): void
    {
        $this->followingRedirects()->from('/login')->post('/register', ['_form' => 'register'])
            ->assertSee("tab: 'register'", false);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'user@test.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors();
    }

    public function test_rider_login_defaults_to_dashboard_and_preserves_intended_destination(): void
    {
        $rider = User::factory()->rider()->create(['password' => 'password123']);
        $credentials = ['email' => $rider->email, 'password' => 'password123'];
        $this->post('/login', $credentials)->assertRedirect(route('rider.dashboard'));
        $this->post('/logout');
        $this->withSession(['url.intended' => route('account.profile')])
            ->post('/login', $credentials)->assertRedirect(route('account.profile'));
    }

    public function test_operational_profiles_omit_shopping_links(): void
    {
        foreach ([UserRole::Admin, UserRole::Rider] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('account.profile'))->assertOk()
                ->assertDontSee('Open a shop')->assertDontSee('My Orders')->assertDontSee('My Addresses')
                ->assertSee($role === UserRole::Rider ? 'Rider Dashboard' : 'Admin Dashboard');
        }
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    }
}
