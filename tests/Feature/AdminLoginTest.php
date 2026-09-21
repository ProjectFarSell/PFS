<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_alias_logs_in_and_opens_dashboard(): void
    {
        $admin = $this->admin();
        $this->post(route('login'), ['email' => 'admin', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_email_login_still_works(): void
    {
        $admin = $this->admin();
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_alias_is_case_insensitive_and_honors_intended_destination(): void
    {
        $admin = $this->admin();
        $this->withSession(['url.intended' => route('account.profile')])
            ->post(route('login'), ['email' => ' ADMIN ', 'password' => 'password'])
            ->assertRedirect(route('account.profile'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_alias_does_not_bypass_password_checks(): void
    {
        $this->admin();
        $this->from(route('login'))->post(route('login'), ['email' => 'admin', 'password' => 'incorrect'])
            ->assertRedirect(route('login'))->assertSessionHasErrors(['email'], null, 'login');
        $this->assertGuest();
    }

    public function test_alias_cannot_sign_in_a_non_admin_or_create_an_admin(): void
    {
        $user = User::factory()->create(['email' => 'admin@farsell.test', 'role' => UserRole::Buyer, 'password' => 'password']);
        $this->post(route('login'), ['email' => 'admin', 'password' => 'password'])
            ->assertSessionHasErrors(['email'], null, 'login');
        $this->assertGuest();
        $this->assertSame(UserRole::Buyer, $user->fresh()->role);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_accepts_admin_alias_but_storefront_does_not_require_auth_and_registration_requires_email(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Email or admin username')
            ->assertSee('id="login-identifier" type="text"', false)
            ->assertSee('autocomplete="username"', false);
        $this->get(route('welcome'))->assertOk()->assertViewIs('home')
            ->assertDontSee('Email or admin username')->assertSee(route('login'), false);
        $this->post(route('register'), [
            'name' => 'Admin attempt', 'email' => 'admin', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['email'], null, 'register');
        $this->assertDatabaseCount('users', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@farsell.test', 'role' => UserRole::Admin, 'password' => 'password']);
    }
}
