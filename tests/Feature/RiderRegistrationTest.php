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

    public function test_updating_details_keeps_existing_documents_and_saves_the_phone_number(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('rider.register'), [
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'license_no' => 'LIC-12345',
            'city' => 'Quezon City',
            'license_document' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
            'id_document' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $profile = $user->riderProfile()->firstOrFail();
        $documents = $profile->documents()->get()->keyBy('document_type');
        $documents['license']->update(['verified' => true]);
        $this->get(route('rider.register'))->assertOk()
            ->assertSee('Your current documents stay on file')
            ->assertSee('Current: Verified');

        $this->post(route('rider.register'), [
            'phone' => '09179999999',
            'vehicle_type' => 'bicycle',
            'license_no' => 'LIC-UPDATED',
            'city' => 'Manila',
        ])->assertSessionHasNoErrors();

        $this->assertSame('09179999999', $user->fresh()->phone);
        $this->assertSame(2, $profile->documents()->count());
        $this->assertTrue($profile->documents()->where('document_type', 'license')->firstOrFail()->verified);
        foreach ($documents as $document) {
            Storage::disk('local')->assertExists($document->file_path);
        }
    }

    public function test_uploading_the_same_document_type_replaces_only_that_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('rider.register'), [
            'vehicle_type' => 'motorcycle',
            'license_no' => 'LIC-12345',
            'city' => 'Quezon City',
            'license_document' => UploadedFile::fake()->create('old-license.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document = $user->riderProfile->documents()->sole();
        $oldPath = $document->file_path;
        $document->update(['verified' => true]);

        $this->post(route('rider.register'), [
            'vehicle_type' => 'motorcycle',
            'license_no' => 'LIC-12345',
            'city' => 'Quezon City',
            'license_document' => UploadedFile::fake()->create('new-license.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(1, $document->riderProfile->documents()->count());
        $this->assertNotSame($oldPath, $document->file_path);
        $this->assertFalse($document->verified);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->file_path);
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
