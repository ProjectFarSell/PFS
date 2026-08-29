<?php

namespace Tests\Feature;

use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_submit_rider_application(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/rider/apply', [
            'vehicle_type' => 'motorcycle',
            'license_no' => 'LIC-12345',
            'city' => 'Quezon City',
        ]);

        $response->assertRedirect(route('rider.profile'));
        $this->assertDatabaseHas('rider_profiles', [
            'user_id' => $user->id,
            'status' => RiderStatus::Pending->value,
        ]);
    }

    public function test_submitting_application_does_not_change_user_role(): void
    {
        // Regression test: applying to be a rider must NOT immediately grant
        // the rider role. Role only changes once an admin approves.
        $user = User::factory()->create();

        $this->actingAs($user)->post('/rider/apply', [
            'vehicle_type' => 'motorcycle',
            'license_no' => 'LIC-12345',
            'city' => 'Quezon City',
        ]);

        $user->refresh();

        $this->assertEquals(UserRole::Buyer, $user->role);
    }

    public function test_uploaded_documents_are_stored_as_rider_documents(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('license.pdf', 500, 'application/pdf');

        $response = $this->actingAs($user)->post('/rider/apply', [
            'vehicle_type' => 'motorcycle',
            'license_no' => 'LIC-12345',
            'city' => 'Quezon City',
            'license_document' => $file,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('rider_documents', [
            'document_type' => 'license',
            'verified' => false,
        ]);
    }

    public function test_rider_application_requires_license_number(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/rider/apply', [
            'vehicle_type' => 'motorcycle',
            'city' => 'Quezon City',
        ]);

        $response->assertSessionHasErrors('license_no');
    }
}
