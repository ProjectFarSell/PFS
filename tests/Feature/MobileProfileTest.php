<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MobileTicket;
use App\Models\User;
use App\Services\Mobile\GoogleIdentity;
use App\Services\Mobile\MobileAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\TestCase;

class MobileProfileTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($user, $user->role->value)['access_token']];
    }

    public function test_buyer_can_edit_and_clear_optional_profile_details_without_exposing_other_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $headers = $this->headers($user);
        $this->getJson('/api/v1/buyer/profile?user_id='.$other->id, $headers)->assertOk()->assertJsonPath('data.email', $user->email)->assertDontSee($other->email);
        $this->patchJson('/api/v1/buyer/profile', ['name' => 'Updated Buyer', 'bio' => 'Local finds.', 'gender' => 'prefer_not_to_say', 'birthday' => '2005-06-07'], $headers)
            ->assertOk()->assertJsonPath('data.name', 'Updated Buyer')->assertJsonPath('data.bio', 'Local finds.')->assertJsonPath('data.birthday', '2005-06-07');
        $this->patchJson('/api/v1/buyer/profile', ['bio' => null, 'gender' => null, 'birthday' => null], $headers)
            ->assertOk()->assertJsonPath('data.bio', null)->assertJsonPath('data.birthday', null);
        $this->assertArrayNotHasKey('birthday', $user->fresh()->toArray());
    }

    public function test_profile_validation_rejects_future_dates_oversized_bios_and_account_privilege_fields(): void
    {
        $user = User::factory()->create();
        $headers = $this->headers($user);
        foreach ([['birthday' => now()->addDay()->format('Y-m-d')], ['bio' => str_repeat('x', 281)], ['role' => 'admin'], ['phone_verified' => true]] as $payload) {
            $this->patchJson('/api/v1/buyer/profile', $payload, $headers)->assertUnprocessable();
        }
        $this->assertSame(UserRole::Buyer, $user->fresh()->role);
    }

    public function test_contact_changes_require_same_token_confirmation_and_invalidate_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'Password!12345', 'email_verified_at' => now(), 'phone_verified_at' => now(), 'phone' => '+639171234567']);
        $first = $this->headers($user);
        $second = $this->headers($user);
        $this->patchJson('/api/v1/buyer/profile', ['phone' => '+639181234567'], $first)->assertUnprocessable();
        $foreignTicket = $this->postJson('/api/v1/auth/reauthenticate', ['password' => 'Password!12345'], $second)->assertOk()->json('data.reauth_token');
        $this->patchJson('/api/v1/buyer/profile', ['phone' => '+639181234567', 'reauth_token' => $foreignTicket], $first)->assertForbidden();
        $ticket = $this->postJson('/api/v1/auth/reauthenticate', ['password' => 'Password!12345'], $first)->assertOk()->json('data.reauth_token');
        $this->patchJson('/api/v1/buyer/profile', ['email' => 'UPDATED@example.test', 'phone' => '09181234567', 'reauth_token' => $ticket], $first)
            ->assertOk()->assertJsonPath('data.email', 'updated@example.test')->assertJsonPath('data.phone', '+639181234567')
            ->assertJsonPath('data.email_verified', false)->assertJsonPath('data.phone_verified', false);
        $this->getJson('/api/v1/session', $first)->assertOk();
        $this->getJson('/api/v1/session', $second)->assertUnauthorized();
        $this->assertDatabaseHas('mobile_auth_tickets', ['secret_hash' => hash('sha256', $ticket)]);
        $this->assertNotNull(MobileTicket::where('secret_hash', hash('sha256', $ticket))->first()->consumed_at);
    }

    public function test_contact_changes_do_not_steal_an_existing_email(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.test']);
        $user = User::factory()->create(['password' => 'Password!12345']);
        $headers = $this->headers($user);
        $ticket = $this->postJson('/api/v1/auth/reauthenticate', ['password' => 'Password!12345'], $headers)->assertOk()->json('data.reauth_token');
        $this->patchJson('/api/v1/buyer/profile', ['email' => 'TAKEN@example.test', 'reauth_token' => $ticket], $headers)->assertUnprocessable();
        $this->assertSame('taken@example.test', $other->fresh()->email);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_linked_google_confirmation_requires_the_same_google_subject(): void
    {
        $user = User::factory()->create(['google_id' => 'own-subject']);
        $headers = $this->headers($user);
        $this->mock(GoogleIdentity::class)->shouldReceive('verify')->with('google-proof', 300)->twice()
            ->andReturn(['sub' => 'different-subject'], ['sub' => 'own-subject']);
        $this->postJson('/api/v1/auth/reauthenticate', ['id_token' => 'google-proof'], $headers)->assertUnprocessable();
        $this->postJson('/api/v1/auth/reauthenticate', ['id_token' => 'google-proof'], $headers)->assertOk()->assertJsonStructure(['data' => ['reauth_token']]);
    }

    public function test_avatar_is_validated_owned_and_safe_to_retry(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $headers = [...$this->headers($user), 'Idempotency-Key' => (string) Str::uuid()];
        $avatar = UploadedFile::fake()->image('avatar.png');
        $response = $this->post('/api/v1/buyer/profile/avatar', ['avatar' => $avatar], $headers)->assertOk()->assertJsonPath('data.has_avatar', true);
        $this->assertMobileResponse($response, 'POST', '/api/v1/buyer/profile/avatar');
        $this->post('/api/v1/buyer/profile/avatar', ['avatar' => $avatar], $headers)->assertOk()->assertJsonPath('data.avatar_version', $response->json('data.avatar_version'));
        $this->assertCount(1, Storage::disk('public')->allFiles('avatars'));
        $this->get('/api/v1/buyer/profile/avatar', $headers)->assertOk()->assertHeader('Content-Type', 'image/png');
        $other = User::factory()->create();
        $this->getJson('/api/v1/buyer/profile/avatar', $this->headers($other))->assertNotFound();
        $this->post('/api/v1/buyer/profile/avatar', ['avatar' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')], $headers)->assertUnprocessable();
    }

    public function test_profile_is_buyer_only_and_requires_authentication(): void
    {
        $this->getJson('/api/v1/buyer/profile')->assertUnauthorized();
        $rider = User::factory()->create(['role' => UserRole::Rider]);
        $this->getJson('/api/v1/buyer/profile', $this->headers($rider))->assertForbidden();
    }

    public function test_failed_photo_storage_does_not_claim_success_or_save_a_receipt(): void
    {
        $user = User::factory()->create();
        $headers = [...$this->headers($user), 'Idempotency-Key' => (string) Str::uuid()];
        $disk = \Mockery::mock();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $this->post('/api/v1/buyer/profile/avatar', ['avatar' => UploadedFile::fake()->image('photo.jpg')], $headers)->assertStatus(503);
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertDatabaseCount('mobile_receipts', 0);
    }
}
