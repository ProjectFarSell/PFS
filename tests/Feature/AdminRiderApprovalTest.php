<?php

namespace Tests\Feature;

use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRiderApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function application(array $attributes = [], ?User $user = null): RiderProfile
    {
        return ($user ?? User::factory()->create())->riderProfile()->create([
            'status' => RiderStatus::Pending, 'vehicle_type' => 'bicycle',
            'license_no' => 'TEST-123', 'city' => 'Quezon City', ...$attributes,
        ]);
    }

    private function reviewData(RiderProfile $profile, array $attributes = []): array
    {
        return ['decision' => 'approve', 'confirmed' => '1', 'review_token' => $profile->fresh()->reviewToken(), ...$attributes];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_only_admins_can_list_review_or_download_applications(): void
    {
        $profile = $this->application();
        $document = $profile->documents()->create(['document_type' => 'id', 'file_path' => 'rider-documents/id.pdf']);
        $urls = [route('admin.riders.index'), route('admin.riders.show', $profile), route('admin.riders.document', [$profile, $document])];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post(route('admin.riders.review', $profile), $this->reviewData($profile))->assertRedirect(route('login'));

        foreach ([UserRole::Buyer, UserRole::Seller, UserRole::Rider] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ($urls as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->post(route('admin.riders.review', $profile), $this->reviewData($profile))->assertForbidden();
        }
        $this->assertSame(RiderStatus::Pending, $profile->fresh()->status);
        $this->assertSame(UserRole::Buyer, $profile->user->fresh()->role);
    }

    public function test_admin_can_approve_and_applicant_immediately_gets_rider_dashboard(): void
    {
        $profile = $this->application();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.riders.index'), false)->assertDontSee('Seller Dashboard');
        $this->get(route('admin.riders.show', $profile))->assertOk()
            ->assertSee('Approve rider')->assertSee('No documents uploaded.');
        $this->post(route('admin.riders.review', $profile), $this->reviewData($profile))
            ->assertRedirect(route('admin.riders.show', $profile))->assertSessionHasNoErrors();
        $profile->refresh();
        $this->assertSame(RiderStatus::Approved, $profile->status);
        $this->assertSame(UserRole::Rider, $profile->user->fresh()->role);
        $this->assertSame($admin->id, $profile->reviewed_by);
        $this->assertNotNull($profile->reviewed_at);
        $this->get(route('admin.riders.show', $profile))->assertOk()->assertDontSee('Approve rider');
        $this->actingAs($profile->user->fresh())->get(route('rider.dashboard'))->assertOk()
            ->assertSee('Your active deliveries')->assertSee('No active deliveries assigned to you yet.');
        $this->get(route('account.profile'))->assertOk()->assertSee('Rider Profile')
            ->assertDontSee('My Orders')->assertDontSee('My Addresses');
    }

    public function test_rejection_requires_reason_and_allows_resubmission_without_role_promotion(): void
    {
        $profile = $this->application();
        $this->actingAs($this->admin());
        $url = route('admin.riders.review', $profile);
        $this->post($url, $this->reviewData($profile, ['decision' => 'reject']))->assertSessionHasErrors('review_note');
        $this->assertSame(RiderStatus::Pending, $profile->fresh()->status);
        $this->post($url, $this->reviewData($profile, ['decision' => 'reject', 'review_note' => 'Please correct your license number.']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(RiderStatus::Rejected, $profile->fresh()->status);
        $this->assertSame(UserRole::Buyer, $profile->user->fresh()->role);
        $this->actingAs($profile->user)->get(route('rider.profile'))->assertOk()->assertSee('Please correct your license number.');
        $this->post(route('rider.register'), ['vehicle_type' => 'bicycle', 'license_no' => 'CORRECTED', 'city' => 'Quezon City'])
            ->assertRedirect(route('rider.profile'));
        $profile->refresh();
        $this->assertSame(RiderStatus::Pending, $profile->status);
        $this->assertNull($profile->reviewed_at);
        $this->assertNull($profile->reviewed_by);
        $this->assertNull($profile->review_note);
        $this->assertSame(UserRole::Buyer, $profile->user->fresh()->role);
    }

    public function test_review_validation_and_non_pending_transitions_do_not_change_records(): void
    {
        $profile = $this->application();
        $this->actingAs($this->admin());
        foreach ([['decision' => 'suspend'], ['confirmed' => null], ['review_token' => null], ['review_note' => str_repeat('a', 1001)]] as $invalid) {
            $this->postJson(route('admin.riders.review', $profile), $this->reviewData($profile, $invalid))->assertUnprocessable();
        }
        $this->assertSame(UserRole::Buyer, $profile->user->fresh()->role);
        foreach ([RiderStatus::Approved, RiderStatus::Rejected, RiderStatus::Suspended] as $status) {
            $profile->update(['status' => $status]);
            $this->postJson(route('admin.riders.review', $profile), $this->reviewData($profile))->assertUnprocessable();
            $this->assertSame($status, $profile->fresh()->status);
            $this->assertSame(UserRole::Buyer, $profile->user->fresh()->role);
        }
        $this->get(route('admin.riders.review', $profile))->assertStatus(405);
        $this->post(route('admin.riders.review', 999999), [])->assertNotFound();
    }

    public function test_duplicate_and_stale_reviews_cannot_overwrite_a_decision_or_changed_application(): void
    {
        $profile = $this->application();
        $stale = $this->reviewData($profile);
        $this->actingAs($profile->user)->post(route('rider.register'), [
            'vehicle_type' => 'van', 'license_no' => 'UPDATED', 'city' => 'Manila',
        ])->assertRedirect();
        $this->actingAs($this->admin())->postJson(route('admin.riders.review', $profile), $stale)
            ->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertSame(UserRole::Buyer, $profile->user->fresh()->role);
        $current = $this->reviewData($profile);
        $this->post(route('admin.riders.review', $profile), $current)->assertSessionHasNoErrors();
        $reviewedAt = $profile->fresh()->reviewed_at->toISOString();
        $this->postJson(route('admin.riders.review', $profile), [...$current, 'decision' => 'reject', 'review_note' => 'Duplicate review'])
            ->assertUnprocessable();
        $this->assertSame(RiderStatus::Approved, $profile->fresh()->status);
        $this->assertSame($reviewedAt, $profile->fresh()->reviewed_at->toISOString());
    }

    public function test_document_downloads_are_private_scoped_and_missing_files_fail_safely(): void
    {
        Storage::fake('local');
        $profile = $this->application();
        Storage::disk('local')->put('rider-documents/test.pdf', 'private document contents');
        $document = $profile->documents()->create(['document_type' => 'license', 'file_path' => 'rider-documents/test.pdf']);
        $this->actingAs($this->admin())->get(route('admin.riders.document', [$profile, $document]))
            ->assertOk()->assertDownload('test.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('admin.riders.document', [$this->application(), $document]))->assertNotFound();
        Storage::disk('local')->delete('rider-documents/test.pdf');
        $this->get(route('admin.riders.document', [$profile, $document]))->assertNotFound();
        $document->update(['file_path' => 'rider-documents/../secret.txt']);
        $this->get(route('admin.riders.document', [$profile, $document]))->assertNotFound();
    }

    public function test_upload_changes_invalidate_review_and_approval_does_not_verify_documents(): void
    {
        Storage::fake('local');
        $profile = $this->application();
        $stale = $this->reviewData($profile);
        $this->actingAs($profile->user)->post(route('rider.register'), [
            'vehicle_type' => 'bicycle', 'license_no' => 'TEST-123', 'city' => 'Quezon City',
            'id_document' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($this->admin())->postJson(route('admin.riders.review', $profile), $stale)->assertUnprocessable();
        $this->post(route('admin.riders.review', $profile), $this->reviewData($profile))->assertSessionHasNoErrors();
        $this->assertFalse($profile->documents()->firstOrFail()->verified);
    }

    public function test_seller_and_admin_roles_cannot_be_overwritten_by_approval(): void
    {
        $this->actingAs($this->admin());
        foreach ([UserRole::Seller, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $profile = $this->application([], $user);
            $this->get(route('admin.riders.show', $profile))->assertOk()->assertDontSee('Approve rider');
            $this->postJson(route('admin.riders.review', $profile), $this->reviewData($profile))->assertUnprocessable();
            $this->assertSame($role, $user->fresh()->role);
            $this->assertSame(RiderStatus::Pending, $profile->fresh()->status);
        }
    }

    public function test_approved_and_suspended_applications_cannot_be_reset_by_applicant(): void
    {
        foreach ([RiderStatus::Approved, RiderStatus::Suspended] as $status) {
            $profile = $this->application(['status' => $status, 'reviewed_at' => now()]);
            $this->actingAs($profile->user)->get(route('rider.register'))->assertRedirect(route('rider.profile'));
            $this->post(route('rider.register'), ['vehicle_type' => 'car', 'license_no' => 'RESET', 'city' => 'Manila'])
                ->assertSessionHasErrors('application');
            $this->assertSame($status, $profile->fresh()->status);
            $this->assertSame('TEST-123', $profile->fresh()->license_no);
            $this->assertNotNull($profile->fresh()->reviewed_at);
        }
    }

    public function test_admin_list_defaults_to_pending_filters_and_paginates_oldest_first(): void
    {
        $approved = $this->application(['status' => RiderStatus::Approved]);
        $pending = collect(range(1, 11))->map(fn () => $this->application());
        $this->actingAs($this->admin())->get(route('admin.riders.index'))->assertOk()
            ->assertDontSee($approved->user->email)->assertSee($pending->first()->user->email)
            ->assertDontSee($pending->last()->user->email)
            ->assertViewHas('applications', fn ($rows) => $rows->total() === 11 && $rows->count() === 10);
        $this->get(route('admin.riders.index', ['page' => 2, 'status' => 'pending']))->assertOk()->assertSee($pending->last()->user->email);
        $this->get(route('admin.riders.index', ['status' => 'approved']))->assertOk()->assertSee($approved->user->email);
        $this->getJson(route('admin.riders.index', ['status' => 'invalid']))->assertUnprocessable();
        $this->get(route('admin.riders.index', ['status' => 'rejected']))->assertOk()->assertSee('No rejected rider applications.');
    }

    public function test_admin_profile_has_no_seller_dashboard_but_sellers_keep_it(): void
    {
        $this->actingAs($this->admin())->get(route('account.profile'))->assertOk()->assertSee('Admin Dashboard')
            ->assertDontSee('Seller Dashboard')->assertDontSee(route('seller.dashboard'), false);
        $this->actingAs(User::factory()->create(['role' => UserRole::Seller]))->get(route('account.profile'))->assertOk()
            ->assertSee('Seller Dashboard')->assertSee(route('seller.dashboard'), false);
    }

    public function test_role_change_rolls_back_if_profile_approval_cannot_be_saved(): void
    {
        $profile = $this->application();
        $payload = $this->reviewData($profile);
        RiderProfile::updating(function (RiderProfile $saving) {
            if ($saving->status === RiderStatus::Approved) {
                throw new \RuntimeException('Simulated profile write failure');
            }
        });

        try {
            $this->actingAs($this->admin())->post(route('admin.riders.review', $profile), $payload)->assertStatus(500);
            $this->assertSame(UserRole::Buyer, $profile->user->fresh()->role);
            $this->assertSame(RiderStatus::Pending, $profile->fresh()->status);
            $this->assertNull($profile->fresh()->reviewed_at);
            $this->assertNull($profile->fresh()->reviewed_by);
        } finally {
            RiderProfile::flushEventListeners();
        }
    }
}
