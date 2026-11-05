<?php

namespace Tests\Feature;

use App\Mail\OneTimePasscodeMail;
use App\Models\User;
use App\Models\VerificationChallenge;
use App\Services\Auth\ContactVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.firebase.api_key', null);
        Config::set('services.firebase.auth_domain', null);
        Config::set('services.firebase.project_id', null);
        Config::set('services.firebase.app_id', null);
    }

    public function test_user_can_verify_their_email_with_an_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email_verified_at' => null, 'phone' => '09123456789', 'verification_channel' => 'email']);
        $code = null;

        $this->actingAs($user)->post(route('contact-verification.send', 'email'))
            ->assertRedirect(route('contact-verification.show'));

        Mail::assertSent(OneTimePasscodeMail::class, function (OneTimePasscodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $this->assertDatabaseHas('verification_challenges', [
            'user_id' => $user->id,
            'channel' => VerificationChallenge::EMAIL,
        ]);

        $this->actingAs($user)->post(route('contact-verification.verify', 'email'), ['code' => $code])
            ->assertSessionHas('status', 'Email verified.');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user->fresh())->get(route('account.profile.edit'))
            ->assertOk()->assertDontSee('Account verification');
    }

    public function test_user_chooses_email_before_a_code_is_sent(): void
    {
        Mail::fake();
        $user = User::factory()->create(['verification_channel' => null, 'email_verified_at' => null]);

        $this->actingAs($user)->post(route('contact-verification.choose'), ['channel' => 'email'])
            ->assertRedirect(route('contact-verification.show'))
            ->assertSessionHas('status', 'Email verification code sent.');

        $this->assertSame(VerificationChallenge::EMAIL, $user->fresh()->verification_channel);
        Mail::assertSent(OneTimePasscodeMail::class);
    }

    public function test_phone_choice_requires_a_valid_phone_number(): void
    {
        $user = User::factory()->create(['verification_channel' => null, 'phone' => null]);

        $this->actingAs($user)->post(route('contact-verification.choose'), ['channel' => 'phone', 'phone' => 'invalid'])
            ->assertSessionHasErrors('phone');

        $this->assertNull($user->fresh()->verification_channel);
    }

    public function test_user_can_choose_phone_verification_without_sending_a_laravel_sms_code(): void
    {
        $user = User::factory()->create(['phone' => null, 'verification_channel' => null]);

        $this->actingAs($user)->post(route('contact-verification.choose'), ['channel' => 'phone', 'phone' => '09123456789'])
            ->assertRedirect(route('contact-verification.show'))
            ->assertSessionHas('status', 'Continue with Firebase to verify your phone number.');

        $this->assertSame(VerificationChallenge::PHONE, $user->fresh()->verification_channel);
        $this->assertSame('09123456789', $user->fresh()->phone);
        $this->assertDatabaseMissing('verification_challenges', ['user_id' => $user->id, 'channel' => VerificationChallenge::PHONE]);
    }

    public function test_phone_verification_requires_firebase_configuration(): void
    {
        $user = User::factory()->create(['phone' => '09123456789', 'phone_verified_at' => null, 'verification_channel' => 'phone']);

        $this->actingAs($user)->get(route('contact-verification.show'))
            ->assertOk()->assertSee('Firebase Phone Auth has not been configured yet.');
    }

    public function test_phone_verification_passes_a_philippine_number_to_firebase_in_international_format(): void
    {
        Config::set('services.firebase.api_key', 'public-api-key');
        Config::set('services.firebase.auth_domain', 'farsell-test.firebaseapp.com');
        Config::set('services.firebase.project_id', 'farsell-test');
        Config::set('services.firebase.app_id', '1:1:web:test');
        $user = User::factory()->create(['phone' => '09123456789', 'verification_channel' => 'phone']);

        $this->actingAs($user)->get(route('contact-verification.show'))
            ->assertOk()->assertSee("signInWithPhoneNumber(auth, \"+639123456789\"", false);
    }

    public function test_verification_codes_cannot_be_resent_immediately(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email_verified_at' => null, 'verification_channel' => 'email']);

        $this->actingAs($user)->post(route('contact-verification.send', 'email'));
        $this->actingAs($user)->post(route('contact-verification.send', 'email'))
            ->assertSessionHasErrors('code');
    }

    public function test_firebase_phone_verification_rejects_a_malformed_token(): void
    {
        Config::set('services.firebase.project_id', 'farsell-test');
        $user = User::factory()->create(['phone' => '09123456789', 'verification_channel' => 'phone']);

        $this->actingAs($user)->post(route('contact-verification.phone.firebase'), ['id_token' => 'not-a-token'])
            ->assertRedirect(route('contact-verification.show'))
            ->assertSessionHasErrors('token');
        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_failed_delivery_does_not_create_a_resend_lock(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email_verified_at' => null, 'verification_channel' => 'email']);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        try {
            app(ContactVerificationService::class)->send($user, VerificationChallenge::EMAIL);
        } catch (\RuntimeException) {
            // The caller presents a retry message; no code should be recorded until delivery succeeds.
        }

        $this->assertDatabaseMissing('verification_challenges', [
            'user_id' => $user->id,
            'channel' => VerificationChallenge::EMAIL,
        ]);
    }
}
