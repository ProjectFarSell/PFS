<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_own_address(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/account/addresses', [
            'line1' => '123 Test Street',
            'city' => 'Manila',
        ]);

        $response->assertRedirect(route('account.addresses.index'));
        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'line1' => '123 Test Street',
        ]);
    }

    public function test_user_cannot_edit_another_users_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $address = Address::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->get("/account/addresses/{$address->id}/edit");

        $response->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $address = Address::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->delete("/account/addresses/{$address->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    public function test_guest_cannot_access_address_routes(): void
    {
        $response = $this->get('/account/addresses');

        $response->assertRedirect(route('login'));
    }
}
