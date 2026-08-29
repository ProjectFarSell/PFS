<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Buyer',
            'email' => 'buyer@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'buyer@test.com']);
        $response->assertRedirect(route('home'));
    }

    public function test_role_stays_buyer_regardless_of_intent(): void
    {
        // Regression test: registering with intent=rider or intent=seller must
        // NOT grant that role immediately. Role only changes on actual approval.
        $this->post('/register', [
            'name' => 'Aspiring Rider',
            'email' => 'aspiring-rider@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'intent' => 'rider',
        ]);

        $user = User::where('email', 'aspiring-rider@test.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals(UserRole::Buyer, $user->role);
    }

    public function test_rider_intent_redirects_to_rider_registration(): void
    {
        $response = $this->post('/register', [
            'name' => 'Aspiring Rider',
            'email' => 'aspiring-rider2@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'intent' => 'rider',
        ]);

        $response->assertRedirect(route('rider.register'));
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'mismatch@test.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }
}
