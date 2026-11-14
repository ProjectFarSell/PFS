<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\FirebasePhoneVerificationService;
use App\Services\Security\SignInOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MobileFirebaseProjectTest extends TestCase
{
    use RefreshDatabase;

    private function phoneToken(string $project): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => __DIR__.'/../Fixtures/openssl.cnf']);
        $this->assertNotFalse($key);
        Cache::put('firebase-auth-certificates', ['mobile-project-test' => openssl_pkey_get_details($key)['key']], 3600);
        $encode = fn ($value) => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
        $claims = ['aud' => $project, 'iss' => 'https://securetoken.google.com/'.$project, 'sub' => 'phone-user',
            'phone_number' => '+639171234567', 'iat' => now()->timestamp, 'exp' => now()->addHour()->timestamp,
            'auth_time' => now()->timestamp, 'firebase' => ['sign_in_provider' => 'phone']];
        $signed = $encode(['alg' => 'RS256', 'kid' => 'mobile-project-test']).'.'.$encode($claims);
        openssl_sign($signed, $signature, $key, OPENSSL_ALGO_SHA256);

        return $signed.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    public function test_mobile_phone_configuration_does_not_enable_website_phone_without_web_configuration(): void
    {
        config(['mobile.firebase_project_id' => 'mobile-project', 'services.firebase.project_id' => null]);
        $this->assertTrue(app(SignInOtp::class)->phoneConfigured(true));
        $this->assertFalse(app(SignInOtp::class)->phoneConfigured());
    }

    public function test_website_rejects_a_signed_token_from_the_separate_mobile_project(): void
    {
        config(['mobile.firebase_project_id' => 'mobile-project', 'services.firebase.project_id' => 'web-project']);
        $this->expectException(ValidationException::class);
        app(FirebasePhoneVerificationService::class)->verify($this->phoneToken('mobile-project'));
    }

    public function test_api_phone_otp_accepts_only_its_configured_project_and_cannot_be_overridden_by_json(): void
    {
        config(['security.login_otp_required' => true, 'mobile.firebase_project_id' => 'mobile-project', 'services.firebase.project_id' => 'web-project']);
        $user = User::factory()->create(['phone' => '09171234567', 'password' => 'Password!12345']);
        $ticket = $this->postJson('/api/v1/auth/login', ['app' => 'buyer', 'email' => $user->email, 'password' => 'Password!12345'])
            ->assertStatus(202)->assertJsonPath('data.methods.1', 'phone')->json('data.challenge_token');
        $this->postJson('/api/v1/auth/challenges/send', ['challenge_token' => $ticket, 'channel' => 'phone'])->assertOk();
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'phone',
            'id_token' => $this->phoneToken('web-project'), 'project_id' => 'web-project', 'mobile.otp_context' => 'forged'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'phone',
            'id_token' => $this->phoneToken('mobile-project')])->assertOk()->assertJsonPath('data.user.phone_verified', true);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_rider_phone_otp_uses_the_rider_project_from_the_ticket_role(): void
    {
        config(['security.login_otp_required' => true, 'mobile.firebase_project_id' => 'buyer-project',
            'mobile.rider_firebase_project_id' => 'rider-project', 'services.firebase.project_id' => 'web-project']);
        $user = User::factory()->create(['role' => 'rider', 'phone' => '09171234567', 'password' => 'Password!12345']);
        $ticket = $this->postJson('/api/v1/auth/login', ['app' => 'rider', 'email' => $user->email, 'password' => 'Password!12345'])
            ->assertStatus(202)->assertJsonPath('data.methods.1', 'phone')->json('data.challenge_token');
        $this->postJson('/api/v1/auth/challenges/send', ['challenge_token' => $ticket, 'channel' => 'phone'])->assertOk();
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'phone',
            'id_token' => $this->phoneToken('buyer-project'), 'app' => 'buyer', 'project_id' => 'buyer-project'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'phone',
            'id_token' => $this->phoneToken('rider-project')])->assertOk()->assertJsonPath('data.user.role', 'rider');
    }

    public function test_buyer_phone_otp_cannot_use_a_valid_token_from_the_rider_project(): void
    {
        config(['security.login_otp_required' => true, 'mobile.firebase_project_id' => 'buyer-project',
            'mobile.rider_firebase_project_id' => 'rider-project']);
        $user = User::factory()->create(['phone' => '09171234567', 'password' => 'Password!12345']);
        $ticket = $this->postJson('/api/v1/auth/login', ['app' => 'buyer', 'email' => $user->email, 'password' => 'Password!12345'])->assertStatus(202)->json('data.challenge_token');
        $this->postJson('/api/v1/auth/challenges/send', ['challenge_token' => $ticket, 'channel' => 'phone'])->assertOk();
        $this->postJson('/api/v1/auth/challenges/verify', ['challenge_token' => $ticket, 'channel' => 'phone',
            'id_token' => $this->phoneToken('rider-project'), 'app' => 'rider'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
