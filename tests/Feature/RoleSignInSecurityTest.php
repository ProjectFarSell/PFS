<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\SignInCodeMail;
use App\Models\SignInChallenge;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AdminTwoFactor;
use App\Support\PrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use OTPHP\InternalClock;
use OTPHP\TOTP;
use Tests\TestCase;

class RoleSignInSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.admin_mfa_required' => true, 'security.seller_mfa_required' => true, 'security.login_otp_required' => true]);
        Mail::fake();
        Http::preventStrayRequests();
    }

    private function sendEmail(User $user): string
    {
        $this->actingAs($user)->post(route('security.otp.send'), ['channel' => 'email'])->assertSessionHasNoErrors();

        return Mail::sent(SignInCodeMail::class)->last()->code;
    }

    private function verify(string $code)
    {
        return $this->post(route('security.otp.verify'), ['code' => $code]);
    }

    private function configurePhone(): void
    {
        config(['services.firebase.api_key' => 'test-public-key', 'services.firebase.auth_domain' => 'fixture.firebaseapp.com',
            'services.firebase.project_id' => 'fixture', 'services.firebase.app_id' => 'test-app']);
    }

    private function phoneToken(array $overrides = []): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => __DIR__.'/../Fixtures/openssl.cnf']);
        $this->assertNotFalse($key);
        Cache::put('firebase-auth-certificates', ['fixture-key' => openssl_pkey_get_details($key)['key']], 3600);
        $encode = fn ($value) => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
        $claims = [...['aud' => 'fixture', 'iss' => 'https://securetoken.google.com/fixture', 'sub' => 'fixture-phone-user',
            'phone_number' => '+639171234567', 'iat' => now()->timestamp, 'exp' => now()->addHour()->timestamp,
            'auth_time' => now()->timestamp, 'firebase' => ['sign_in_provider' => 'phone']], ...$overrides];
        $body = $encode(['alg' => 'RS256', 'kid' => 'fixture-key']).'.'.$encode($claims);
        openssl_sign($body, $signature, $key, OPENSSL_ALGO_SHA256);

        return $body.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    public function test_buyer_password_login_requires_a_fresh_code_even_with_verified_contact_and_intended_checkout_is_restored(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->withSession(['url.intended' => route('checkout.create')])->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('security.otp.show'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('account.profile'))->assertRedirect(route('security.otp.show'));
        $this->postJson('/cart', [])->assertStatus(423);
        $code = $this->sendEmail($user);
        $this->assertTrue(Hash::check($code, SignInChallenge::sole()->code_hash));
        $this->assertStringNotContainsString($code, json_encode(SignInChallenge::sole()->toArray()));
        $this->verify($code)->assertRedirect(route('account.profile', [], false))->assertSessionHasNoErrors();
        $this->get(route('account.profile'))->assertOk();
        $this->verify($code)->assertSessionHasErrors('code');
    }

    public function test_login_destination_is_restored_if_no_other_protected_page_was_opened(): void
    {
        $user = User::factory()->create();
        $this->withSession(['url.intended' => route('checkout.create')])->post('/login', ['email' => $user->email, 'password' => 'password']);
        $code = $this->sendEmail($user);
        $this->verify($code)->assertRedirect(route('checkout.create'));
    }

    public function test_rider_uses_otp_and_cannot_enroll_an_authenticator(): void
    {
        $rider = User::factory()->create(['role' => UserRole::Rider]);
        $this->actingAs($rider)->get(route('rider.dashboard'))->assertRedirect(route('security.otp.show'));
        $this->get(route('security.otp.show'))->assertOk()->assertSee('Finish signing in')->assertDontSee('Set up authenticator');
        $code = $this->sendEmail($rider);
        $this->verify($code)->assertRedirect(route('rider.dashboard'))->assertSessionHasNoErrors();
        $this->post(route('security.mfa.setup'))->assertForbidden();
    }

    public function test_seller_must_enroll_and_cannot_use_email_otp_as_an_authenticator_fallback(): void
    {
        $seller = User::factory()->seller()->create();
        $this->post('/login', ['email' => $seller->email, 'password' => 'password'])->assertRedirect(route('security.index'));
        $this->get(route('seller.dashboard'))->assertRedirect(route('security.index'));
        $this->post(route('security.otp.send'), ['channel' => 'email'])->assertForbidden();
        $this->post(route('security.confirm'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->post(route('security.mfa.setup'))->assertSessionHasNoErrors();
        $secret = Crypt::decryptString(session('security.pending_mfa')['secret']);
        $this->get(route('security.index'))->assertOk()->assertSee('Authenticator two-factor authentication')->assertSee('data:image/png;base64,', false);
        $code = TOTP::createFromSecret($secret, new InternalClock)->at(now()->timestamp);
        $this->post(route('security.mfa.enable'), ['code' => $code, 'setup_id' => substr(hash('sha256', $secret), 0, 32)])->assertSessionHasNoErrors();
        $this->assertCount(8, $seller->fresh()->mfa_recovery_codes);
        $this->assertSame(app(AccountSecurity::class)->mfaFingerprint($seller->fresh()), session('security.mfa'));
        $this->post(route('security.confirm'), ['password' => 'password'])->assertSessionHasErrors('identity');
    }

    public function test_enrolled_seller_can_use_a_recovery_code_once_and_cannot_replay_it(): void
    {
        $seller = User::factory()->seller()->create();
        [$plain, $hashes] = app(AdminTwoFactor::class)->recoveryCodes();
        $seller->forceFill(['mfa_secret' => app(AdminTwoFactor::class)->generate(), 'mfa_confirmed_at' => now(), 'mfa_recovery_codes' => $hashes])->save();
        $this->actingAs($seller)->post(route('security.confirm'), ['code' => $plain[0]])->assertSessionHasNoErrors();
        $this->assertFalse(app(AdminTwoFactor::class)->verify($seller, $plain[0]));
        $this->assertCount(7, $seller->fresh()->mfa_recovery_codes);
    }

    public function test_password_or_historical_contact_code_cannot_bypass_the_sign_in_challenge(): void
    {
        $user = User::factory()->create();
        $user->verificationChallenges()->create(['channel' => 'email', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10), 'sent_at' => now()]);
        $this->actingAs($user)->post(route('security.confirm'), ['password' => 'password'])->assertSessionHasErrors('identity');
        $this->post(route('contact-verification.verify', 'email'), ['code' => '123456'])->assertStatus(423);
        $this->verify('123456')->assertSessionHasErrors('code');
        $this->assertNull(session('security.otp'));
    }

    public function test_wrong_codes_lock_after_five_attempts_and_expired_codes_fail(): void
    {
        $user = User::factory()->create();
        $code = $this->sendEmail($user);
        for ($i = 0; $i < 5; $i++) {
            $this->verify('000000')->assertSessionHasErrors('code');
        }
        $this->verify($code)->assertSessionHasErrors('code');
        $this->assertEquals(5, SignInChallenge::sole()->attempts);
        $this->travel(61)->seconds();
        $new = $this->sendEmail($user);
        $this->travel(11)->minutes();
        $this->verify($new)->assertSessionHasErrors('code');
    }

    public function test_resend_is_limited_per_account_and_successful_resend_invalidates_earlier_code(): void
    {
        $user = User::factory()->create();
        $old = $this->sendEmail($user);
        $this->post(route('security.otp.send'), ['channel' => 'email'])->assertSessionHasErrors('code');
        $this->travel(61)->seconds();
        $new = $this->sendEmail($user);
        if ($old !== $new) {
            $this->verify($old)->assertSessionHasErrors('code');
        }
        $this->verify($new)->assertSessionHasNoErrors();
    }

    public function test_codes_are_bound_to_the_current_login_and_account_contact(): void
    {
        $user = User::factory()->create();
        $code = $this->sendEmail($user);
        $original = session('security.otp_context');
        $this->withSession(['security.otp_context' => 'another-login'])->post(route('security.otp.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->withSession(['security.otp_context' => $original]);
        $user->update(['email' => 'changed@example.test']);
        $this->verify($code)->assertSessionHasErrors('code');
        $this->assertNull(session('security.otp'));
    }

    public function test_google_buyer_still_needs_a_code_and_google_seller_still_needs_authenticator(): void
    {
        $profile = [];
        Http::fake(function ($request) use (&$profile) {
            return Http::response(str_contains($request->url(), 'oauth2.googleapis.com') ? ['access_token' => 'fixture'] : $profile);
        });
        foreach (['buyer', 'seller'] as $role) {
            $user = User::factory()->create(['role' => $role, 'google_id' => 'subject-'.$role]);
            $profile = ['sub' => 'subject-'.$role, 'email' => $user->email, 'email_verified' => true, 'name' => 'Fixture User'];
            $this->withSession(['google_oauth_state' => 'fixture-state', 'google_privacy_consent' => PrivacyNotice::VERSION])
                ->get(route('auth.google.callback', ['code' => 'fixture', 'state' => 'fixture-state']))
                ->assertRedirect(route($role === 'buyer' ? 'security.otp.show' : 'security.index'));
            $this->assertNull(session('security.otp'));
            $this->assertNull(session('security.mfa'));
            $this->post(route('logout'));
        }
    }

    public function test_sign_out_and_fresh_password_login_require_another_code(): void
    {
        $user = User::factory()->create();
        $this->verify($this->sendEmail($user))->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('security.otp.show'));
        $this->assertNull(session('security.otp'));
    }

    public function test_verified_session_revocation_keeps_current_otp_but_role_changes_require_the_new_factor(): void
    {
        $user = User::factory()->create();
        $this->verify($this->sendEmail($user))->assertSessionHasNoErrors();
        $this->post(route('security.sessions.revoke'))->assertSessionHasNoErrors();
        $this->assertSame(app(AccountSecurity::class)->otpFingerprint($user->fresh()), session('security.otp'));
        $this->get(route('account.profile'))->assertOk();
        $user->update(['role' => UserRole::Seller]);
        $this->get(route('account.profile'))->assertRedirect(route('security.index'));
    }

    public function test_phone_requires_configuration_and_a_saved_destination_not_an_arbitrary_posted_number(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user)->post(route('security.otp.send'), ['channel' => 'phone', 'phone' => '09179999999'])->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->phone);
        $user->update(['phone' => '09171234567']);
        config(['services.firebase.project_id' => null]);
        $this->post(route('security.otp.send'), ['channel' => 'phone'])->assertSessionHasErrors('code');
    }

    public function test_signed_phone_proof_requires_fresh_sms_authentication_and_matching_number(): void
    {
        $this->configurePhone();
        $user = User::factory()->create(['phone' => '09171234567']);
        $this->actingAs($user)->post(route('security.otp.send'), ['channel' => 'phone'])->assertSessionHasNoErrors();
        $this->get(route('security.otp.show'))->assertOk()->assertSee('+639171234567')->assertSee('Send SMS code');
        $old = $this->phoneToken(['auth_time' => now()->subMinutes(5)->timestamp]);
        $this->postJson(route('security.otp.phone'), ['id_token' => $old])->assertUnprocessable();
        $wrong = $this->phoneToken(['phone_number' => '+639179999999']);
        $this->postJson(route('security.otp.phone'), ['id_token' => $wrong])->assertUnprocessable();
        $fresh = $this->phoneToken();
        $this->postJson(route('security.otp.phone'), ['id_token' => $fresh])->assertOk()->assertJson(['verified' => true]);
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->postJson(route('security.otp.phone'), ['id_token' => $fresh])->assertUnprocessable();
        $this->get(route('account.profile'))->assertOk();
    }

    public function test_phone_proof_from_another_provider_or_wrong_audience_never_passes(): void
    {
        $this->configurePhone();
        $user = User::factory()->create(['phone' => '09171234567']);
        $this->actingAs($user)->post(route('security.otp.send'), ['channel' => 'phone']);
        foreach ([['firebase' => ['sign_in_provider' => 'google.com']], ['aud' => 'other-project'], ['exp' => now()->subMinutes(2)->timestamp]] as $claims) {
            $this->postJson(route('security.otp.phone'), ['id_token' => $this->phoneToken($claims)])->assertUnprocessable();
        }
        $this->assertNull(session('security.otp'));
    }

    public function test_seller_has_guarded_operator_recovery_if_authenticator_and_codes_are_lost(): void
    {
        $user = User::factory()->seller()->create();
        $user->forceFill(['mfa_secret' => app(AdminTwoFactor::class)->generate(), 'mfa_confirmed_at' => now()])->save();
        $this->artisan('ops:reset-mfa', ['email' => $user->email])
            ->expectsQuestion('Type RESET '.$user->email.' after independently verifying this seller', 'RESET '.$user->email)->assertSuccessful();
        $this->assertNull($user->fresh()->mfa_secret);
        $this->assertEquals(1, $user->fresh()->security_version);
    }

    public function test_failed_email_delivery_does_not_create_a_challenge_or_grant_access(): void
    {
        $user = User::factory()->create();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Provider offline'));
        $this->actingAs($user)->post(route('security.otp.send'), ['channel' => 'email'])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('sign_in_challenges', 0);
        $this->assertNull(session('security.otp'));
        $this->get(route('account.profile'))->assertRedirect(route('security.otp.show'));
    }

    public function test_new_account_requires_otp_and_posted_destinations_cannot_replace_its_email(): void
    {
        $this->post('/register', ['name' => 'New Buyer', 'email' => 'new@example.test', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'privacy_consent' => 1])->assertRedirect(route('security.otp.show'));
        $this->post(route('security.otp.send'), ['channel' => 'email', 'email' => 'other@example.test'])->assertSessionHasNoErrors();
        Mail::assertSent(SignInCodeMail::class, fn ($mail) => $mail->hasTo('new@example.test') && ! $mail->hasTo('other@example.test'));
        $this->verify(Mail::sent(SignInCodeMail::class)->last()->code)->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'new@example.test')->sole()->email_verified_at);
    }

    public function test_session_missing_otp_marker_is_gated_even_when_account_was_verified_before(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'phone_verified_at' => now()]);
        $this->actingAs($user)->withSession(['security' => ['version' => 0]])->get(route('account.profile'))->assertRedirect(route('security.otp.show'));
        $this->post(route('security.sessions.revoke'))->assertSessionHasErrors('identity');
    }
}
