<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\SignInChallenge;
use App\Models\User;
use App\Services\Security\AdminTwoFactor;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'New-Password123!', 'password_confirmation' => 'New-Password123!'];
    }

    public function test_login_links_to_recovery_and_email_contains_an_expiring_reset_url_on_the_configured_origin(): void
    {
        Notification::fake();
        config(['app.url' => 'https://farsell.example']);
        $user = User::factory()->create();
        $this->get('/login')->assertOk()->assertSee('Forgot password?')->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
        $this->post(route('password.email'), ['email' => strtoupper($user->email)])->assertSessionHas('status', PasswordResetLinkController::MESSAGE);
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith('https://farsell.example/reset-password/', $url);
            $this->assertStringContainsString($notification->token, $url);
            $this->assertStringNotContainsString($notification->token, DB::table('password_reset_tokens')->value('token'));

            return true;
        });
    }

    public function test_known_unknown_and_throttled_emails_receive_the_same_response(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        foreach ([$user->email, 'unknown@example.test', $user->email] as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertRedirect(route('password.request'))
                ->assertSessionHas('status', PasswordResetLinkController::MESSAGE)->assertSessionHasNoErrors();
        }
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_reset_is_single_use_revokes_sessions_and_challenges_and_never_logs_in_the_user(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        DB::table('sessions')->insert(['id' => 'old-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        SignInChallenge::create(['user_id' => $user->id, 'context_hash' => str_repeat('a', 64), 'identity_hash' => str_repeat('b', 64), 'channel' => 'email',
            'code_hash' => Hash::make('123456'), 'sent_at' => now(), 'expires_at' => now()->addMinutes(10)]);
        $token = Password::createToken($user);
        config(['session.driver' => 'database']);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $data = $this->payload($user, $token);
        $this->post(route('password.store'), $data)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('New-Password123!', $user->password));
        $this->assertEquals(1, $user->security_version);
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-device']);
        $this->assertDatabaseCount('sign_in_challenges', 0);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
        $this->post(route('password.store'), $data)->assertSessionHasErrors('email');
        $this->assertEquals(1, $user->fresh()->security_version);
        $this->get('/login')->assertSee('Forgot password?');
    }

    public function test_invalid_expired_and_wrong_account_tokens_cannot_change_passwords(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = Password::createToken($user);
        $this->post(route('password.store'), $this->payload($user, 'invalid-token'))->assertSessionHasErrors('email');
        $this->post(route('password.store'), $this->payload($other, $token))->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->post(route('password.store'), $this->payload($user, $token))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertTrue(Hash::check('password', $other->fresh()->password));
    }

    public function test_weak_or_unconfirmed_password_does_not_consume_the_reset_link(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post(route('password.store'), [...$this->payload($user, $token), 'password' => '123', 'password_confirmation' => '123'])->assertSessionHasErrors('password');
        $this->post(route('password.store'), [...$this->payload($user, $token), 'password_confirmation' => 'wrong-confirmation'])->assertSessionHasErrors('password');
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_authenticator_setup_and_account_role_survive_password_reset(): void
    {
        config(['security.admin_mfa_required' => true, 'security.seller_mfa_required' => true]);
        foreach (['admin', 'seller'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $secret = app(AdminTwoFactor::class)->generate();
            [, $codes] = app(AdminTwoFactor::class)->recoveryCodes();
            $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now(), 'mfa_recovery_codes' => $codes])->save();
            $this->post(route('password.store'), $this->payload($user, Password::createToken($user)))->assertSessionHasNoErrors();
            $this->assertSame($secret, $user->fresh()->mfa_secret);
            $this->assertSame($codes, $user->fresh()->mfa_recovery_codes);
            $this->assertEquals($role, $user->fresh()->role->value);
            $this->post('/login', ['email' => $user->email, 'password' => 'New-Password123!'])->assertRedirect(route('security.index'));
            $this->assertNull(session('security.mfa'));
            $this->post(route('logout'));
        }
    }

    public function test_buyer_still_requires_otp_after_reset_and_suspended_accounts_stay_suspended(): void
    {
        config(['security.login_otp_required' => true]);
        $user = User::factory()->create();
        $this->post(route('password.store'), $this->payload($user, Password::createToken($user)))->assertSessionHasNoErrors();
        $this->post('/login', ['email' => $user->email, 'password' => 'New-Password123!'])->assertRedirect(route('security.otp.show'));
        $this->assertNull(session('security.otp'));
        $this->post(route('logout'));
        $user->forceFill(['suspended_at' => now()])->save();
        $this->post(route('password.store'), $this->payload($user, Password::createToken($user)))->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->suspended_at);
        $this->post('/login', ['email' => $user->email, 'password' => 'New-Password123!'])->assertSessionHasErrors('email', null, 'login');
        $this->assertGuest();
    }

    public function test_email_delivery_failure_uses_the_same_generic_response(): void
    {
        $user = User::factory()->create();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail offline'));
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', PasswordResetLinkController::MESSAGE);
        $this->assertGuest();
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'unknown@example.test'])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'unknown@example.test'])->assertTooManyRequests();
    }
}
