<?php

namespace Tests\Feature;

use App\Mail\OneTimePasscodeMail;
use App\Models\User;
use App\Models\VerificationChallenge;
use App\Services\Auth\ContactVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.otp.sms_driver', 'log');
        Config::set('services.otp.semaphore.api_key', null);
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

    public function test_an_invalid_code_does_not_verify_a_phone_number(): void
    {
        $user = User::factory()->create(['phone' => '09123456789', 'phone_verified_at' => null, 'verification_channel' => 'phone']);
        app(ContactVerificationService::class)->send($user, VerificationChallenge::PHONE);

        $this->actingAs($user)->post(route('contact-verification.verify', 'phone'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_verification_codes_cannot_be_resent_immediately(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email_verified_at' => null, 'verification_channel' => 'email']);

        $this->actingAs($user)->post(route('contact-verification.send', 'email'));
        $this->actingAs($user)->post(route('contact-verification.send', 'email'))
            ->assertSessionHasErrors('code');
    }

    public function test_semaphore_sms_uses_the_otp_endpoint_with_a_philippine_number(): void
    {
        Config::set('services.otp.sms_driver', 'semaphore');
        Config::set('services.otp.semaphore.api_key', 'test-api-key');
        Http::fake(['https://api.semaphore.co/*' => Http::response([], 200)]);

        $user = User::factory()->create(['phone' => '09123456789', 'verification_channel' => 'phone']);
        app(ContactVerificationService::class)->send($user, VerificationChallenge::PHONE);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.semaphore.co/api/v4/otp'
                && $request['apikey'] === 'test-api-key'
                && $request['number'] === '639123456789'
                && $request['code'] !== null;
        });
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
