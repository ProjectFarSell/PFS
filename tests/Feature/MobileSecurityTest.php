<?php

namespace Tests\Feature;

use App\Mail\SignInCodeMail;
use App\Models\User;
use App\Services\Auth\FirebasePhoneVerificationService;
use App\Services\Mobile\MobileAuthentication;
use App\Support\PrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\TestCase;

class MobileSecurityTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($user, $user->role->value)['access_token']];
    }

    public function test_phone_sign_in_uses_a_fresh_saved_phone_proof_and_blocks_replay(): void
    {
        config(['security.login_otp_required' => true, 'services.firebase.api_key' => 'test-public-key', 'services.firebase.auth_domain' => 'test.firebaseapp.com', 'services.firebase.project_id' => 'test-project', 'services.firebase.app_id' => 'test-app']);
        $this->mock(FirebasePhoneVerificationService::class)->shouldReceive('verifyForSignIn')->andReturn(['phone' => '+639123456789', 'hash' => hash('sha256', 'one-phone-authentication')]);
        $user = User::factory()->create(['password' => 'Password!12345', 'phone' => '09123456789']);
        $login = ['email' => $user->email, 'password' => 'Password!12345', 'app' => 'buyer'];
        $ticket = $this->postJson('/api/v1/auth/login', $login)->assertStatus(202)->json('data.challenge_token');
        $this->postJson('/api/v1/auth/challenges/send', ['challenge_token' => $ticket, 'channel' => 'phone'])->assertOk()->assertJsonPath('data.phone', '+639123456789');
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'phone', 'id_token' => 'test-firebase-proof'])->assertOk()->assertJsonPath('data.user.phone_verified', true);
        $this->travel(61)->seconds();
        $ticket = $this->postJson('/api/v1/auth/login', $login)->assertStatus(202)->json('data.challenge_token');
        $this->postJson('/api/v1/auth/challenges/send', ['challenge_token' => $ticket, 'channel' => 'phone'])->assertOk();
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'phone', 'id_token' => 'test-firebase-proof'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_otp_is_required_before_a_token_and_cannot_be_replayed(): void
    {
        config(['security.login_otp_required' => true]);
        Mail::fake();
        $user = User::factory()->create(['password' => 'Password!12345']);
        $ticket = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password!12345', 'app' => 'buyer'])->assertStatus(202)->assertJsonMissingPath('data.access_token')->json('data.challenge_token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/challenges/send', ['challenge_token' => $ticket, 'channel' => 'email'])->assertOk();
        $code = Mail::sent(SignInCodeMail::class)->first()->code;
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'email', 'code' => $code])->assertOk()->assertJsonPath('data.abilities.0', 'buyer:*');
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'email', 'code' => $code])->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_failed_otp_attempts_commit_and_contact_changes_invalidate_the_ticket(): void
    {
        config(['security.login_otp_required' => true]);
        Mail::fake();
        $user = User::factory()->create(['password' => 'Password!12345']);
        $ticket = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password!12345', 'app' => 'buyer'])->assertStatus(202)->json('data.challenge_token');
        $this->postJson('/api/v1/auth/challenges/send', ['challenge_token' => $ticket, 'channel' => 'email'])->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'email', 'code' => '000000'])->assertUnprocessable();
        }
        $this->assertDatabaseHas('sign_in_challenges', ['user_id' => $user->id, 'attempts' => 5]);
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'email', 'code' => Mail::sent(SignInCodeMail::class)->first()->code])->assertUnprocessable();
        $user->update(['phone' => '09123456789']);
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'email', 'code' => '000000'])->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_consent_and_role_injection_and_demo_exemption(): void
    {
        config(['security.login_otp_required' => true]);
        $data = ['name' => 'Mobile Buyer', 'email' => 'mobile-test@farsell.test', 'password' => 'Password!12345', 'password_confirmation' => 'Password!12345', 'privacy_consent' => true, 'privacy_notice_version' => PrivacyNotice::VERSION];
        $this->postJson('/api/v1/auth/register', [...$data, 'role' => 'admin'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/register', $data)->assertStatus(202);
        $this->assertDatabaseHas('users', ['email' => $data['email'], 'role' => 'buyer', 'is_demo_account' => false]);
        $demo = User::factory()->create(['email' => 'buyer@farsell.test', 'is_demo_account' => true, 'password' => 'password']);
        $this->postJson('/api/v1/auth/login', ['email' => $demo->email, 'password' => 'password', 'app' => 'buyer'])->assertOk();
        $demo->forceFill(['is_demo_account' => false])->save();
        $this->postJson('/api/v1/auth/login', ['email' => $demo->email, 'password' => 'password', 'app' => 'buyer'])->assertStatus(202);
    }

    public function test_device_rebinding_logout_does_not_delete_the_new_owners_binding(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $a = $this->headers($first);
        $b = $this->headers($second);
        $id = (string) Str::uuid();
        $data = ['installation_id' => $id, 'platform' => 'android', 'app' => 'buyer', 'fcm_token' => 'test-only-fcm-token', 'enabled_events' => ['order.placed']];
        $this->putJson('/api/v1/devices/'.$id, $data, $a)->assertOk()->assertJsonMissingPath('data.fcm_token');
        $this->putJson('/api/v1/devices/'.$id, $data, $b)->assertOk();
        $this->deleteJson('/api/v1/devices/'.$id, [], $a)->assertNotFound();
        $this->postJson('/api/v1/auth/logout', [], $a)->assertNoContent();
        $this->assertDatabaseHas('mobile_devices', ['installation_id' => $id, 'user_id' => $second->id]);
        $this->deleteJson('/api/v1/devices/'.$id, [], $b)->assertNoContent();
        $this->assertDatabaseCount('mobile_devices', 0);
    }

    public function test_recent_identity_deletion_and_optional_contact_verification(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => 'Password!12345', 'email_verified_at' => null]);
        $headers = $this->headers($user);
        $this->postJson('/api/v1/account/contact-verification/send', ['channel' => 'email'], $headers)->assertOk();
        $code = Mail::sent(SignInCodeMail::class)->first()->code;
        $this->postJson('/api/v1/account/contact-verification/verify', ['channel' => 'email', 'code' => $code], $headers)->assertOk()->assertJsonPath('data.email_verified', true);
        $reauth = $this->postJson('/api/v1/auth/reauthenticate', ['password' => 'Password!12345'], $headers)->assertOk()->json('data.reauth_token');
        $this->postJson('/api/v1/account/deletion-requests', ['confirmation' => 'DELETE', 'reauth_token' => $reauth], $headers)->assertStatus(202)->assertJsonPath('data.status', 'pending');
        $this->postJson('/api/v1/account/deletion-requests', ['confirmation' => 'DELETE', 'reauth_token' => $reauth], $headers)->assertUnauthorized();
    }

    public function test_login_limits_have_retry_after_and_no_secret_echo(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'not-my-password', 'app' => 'buyer'])->assertUnauthorized();
        }
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'not-my-password', 'app' => 'buyer'])->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('code', 'rate_limited')->assertDontSee('not-my-password');
    }

    public function test_security_codes_cannot_be_written_to_a_log_mailer(): void
    {
        config(['security.login_otp_required' => true, 'mail.default' => 'log']);
        Mail::fake();
        $user = User::factory()->create(['password' => 'Password!12345']);
        $ticket = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password!12345', 'app' => 'buyer'])->assertStatus(202)->json('data.challenge_token');
        $this->postJson('/api/v1/auth/challenges/send', ['challenge_token' => $ticket, 'channel' => 'email'])->assertStatus(503);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('sign_in_challenges', 0);
    }
}
