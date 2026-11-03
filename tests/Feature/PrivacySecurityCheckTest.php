<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Support\PrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrivacySecurityCheckTest extends TestCase
{
    use RefreshDatabase;

    private function signup(array $overrides = []): array
    {
        return array_merge(['name' => 'Topic 9 Dummy Buyer', 'email' => 'topic9@example.test', 'password' => 'Demo-Only-12345', 'password_confirmation' => 'Demo-Only-12345', 'privacy_consent' => '1'], $overrides);
    }

    public function test_privacy_notice_is_public_and_registration_links_to_it(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('What we collect and why')->assertSee('Who receives information')->assertSee('no automatic deletion schedule');
        $this->get(route('register'))->assertOk()->assertSee('name="privacy_consent"', false)->assertSee(route('privacy'), false);
    }

    public function test_signup_requires_explicit_consent_even_when_browser_validation_is_bypassed(): void
    {
        foreach ([null, false, '0', 'no'] as $value) {
            $this->post(route('register'), $this->signup(['privacy_consent' => $value]))->assertSessionHasErrors('privacy_consent', null, 'register');
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_weak_password_123_is_rejected_and_no_account_is_created(): void
    {
        $this->post(route('register'), $this->signup(['password' => '123', 'password_confirmation' => '123']))->assertSessionHasErrors('password', null, 'register');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signup_hashes_password_and_records_server_controlled_consent(): void
    {
        $this->post(route('register'), $this->signup(['privacy_accepted_at' => '2000-01-01', 'privacy_notice_version' => 'fake', 'role' => 'admin']))->assertRedirect('/account/verify');
        $user = User::sole();
        $this->assertTrue(Hash::check('Demo-Only-12345', $user->password));
        $this->assertNotSame('Demo-Only-12345', $user->password);
        $this->assertSame(PrivacyNotice::VERSION, $user->privacy_notice_version);
        $this->assertTrue($user->privacy_accepted_at->isToday());
        $this->assertSame(UserRole::Buyer, $user->role);
        $this->assertNull($user->phone);
        $this->assertStringStartsWith('data:image/svg+xml,', $user->avatar_url);
    }

    public function test_logout_protects_orders_profile_and_admin_urls(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->get(route('orders.index'))->assertRedirect(route('login'));
        $this->get(route('account.profile'))->assertRedirect(route('login'));
        $this->get(route('account.profile.edit'))->assertRedirect(route('login'));
    }

    public function test_all_roles_can_request_review_only_for_themselves_and_duplicates_are_idempotent(): void
    {
        $other = User::factory()->create();
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post(route('account.privacy.deletion-request'), ['confirmation' => 'DELETE', 'user_id' => $other->id, 'status' => 'reviewed'])->assertRedirect(route('account.profile.edit'));
            $this->post(route('account.privacy.deletion-request'), ['confirmation' => 'DELETE'])->assertRedirect(route('account.profile.edit'));
            $this->assertSame(1, AccountDeletionRequest::where('user_id', $user->id)->count());
            $this->assertSame('pending', AccountDeletionRequest::where('user_id', $user->id)->sole()->status);
            $this->assertNotNull($user->fresh());
        }
        $this->assertDatabaseMissing('account_deletion_requests', ['user_id' => $other->id]);
    }

    public function test_deletion_request_needs_authentication_and_confirmation(): void
    {
        $this->post(route('account.privacy.deletion-request'), ['confirmation' => 'DELETE'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('account.privacy.deletion-request'), [])->assertSessionHasErrors('confirmation', null, 'privacy');
        $this->assertDatabaseCount('account_deletion_requests', 0);
    }

    public function test_only_admin_can_review_requests_and_response_is_visible_only_to_owner(): void
    {
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $deletion = AccountDeletionRequest::create(['user_id' => $buyer->id]);
        $url = route('admin.privacy-requests.review', $deletion);
        $this->actingAs($buyer)->get(route('admin.privacy-requests.index'))->assertForbidden();
        $this->post($url, ['response' => 'Spoofed'])->assertForbidden();
        $response = 'Please confirm which records you want reviewed with the project administrator.';
        $this->actingAs($admin)->post($url, ['response' => $response])->assertRedirect(route('admin.privacy-requests.index'));
        $this->assertSame('reviewed', $deletion->fresh()->status);
        $this->assertSame($admin->id, $deletion->fresh()->reviewed_by);
        $this->assertNotNull($buyer->fresh());
        $this->actingAs($buyer)->get(route('account.profile.edit'))->assertOk()->assertSee($response);
        $this->actingAs($other)->get(route('account.profile.edit'))->assertOk()->assertDontSee($response);
        $this->actingAs($buyer)->post(route('account.privacy.deletion-request'), ['confirmation' => 'DELETE'])->assertRedirect(route('account.profile.edit'));
        $this->assertSame('pending', $deletion->fresh()->status);
        $this->assertNull($deletion->fresh()->reviewed_by);
    }

    public function test_existing_accounts_can_record_consent_but_profile_payload_cannot_forge_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.privacy.consent'), [])->assertSessionHasErrors('privacy_consent');
        $this->post(route('account.privacy.consent'), ['privacy_consent' => '1', 'privacy_notice_version' => 'fake'])->assertRedirect(route('account.profile.edit'));
        $this->assertSame(PrivacyNotice::VERSION, $user->fresh()->privacy_notice_version);
    }

    public function test_google_requires_consent_before_contacting_google_and_callback_records_it(): void
    {
        config(['services.google.client_id' => 'dummy-client', 'services.google.client_secret' => 'dummy-secret', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
        Http::preventStrayRequests();
        $this->get(route('auth.google.redirect'))->assertOk()->assertSee('privacy_consent');
        $this->post(route('auth.google.start'), [])->assertSessionHasErrors('privacy_consent');
        Http::assertNothingSent();
        $this->post(route('auth.google.start'), ['privacy_consent' => '1'])->assertRedirectContains('https://accounts.google.com/');
        $state = session('google_oauth_state');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'dummy-token']),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'dummy-google-id', 'email' => 'google@example.test', 'name' => 'Dummy Google Buyer', 'email_verified' => true]),
        ]);
        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'dummy-code']))->assertRedirect(route('home'));
        $user = User::where('email', 'google@example.test')->sole();
        $this->assertSame(PrivacyNotice::VERSION, $user->privacy_notice_version);
        $this->assertAuthenticatedAs($user);
    }
}
