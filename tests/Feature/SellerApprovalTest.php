<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SellerApplication;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        return ['shop_name' => 'Karl Test Shop', 'city' => 'Manila', 'tagline' => 'Surplus finds', 'description' => 'Household goods and collectibles'];
    }

    private function application(?User $user = null): SellerApplication
    {
        return ($user ?? User::factory()->create())->sellerApplication()->create([
            ...$this->data(), 'status' => 'pending', 'revision' => (string) Str::uuid(), 'submitted_at' => now(),
        ]);
    }

    private function decision(SellerApplication $application, array $extra = []): array
    {
        return ['decision' => 'approve', 'revision' => $application->revision, 'confirmed' => '1', ...$extra];
    }

    public function test_seller_signup_stays_buyer_and_routes_to_application(): void
    {
        $this->post(route('register'), ['name' => 'Seller applicant', 'email' => 'applicant@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'intent' => 'seller'])
            ->assertRedirect(route('seller.apply'));
        $user = User::sole();
        $this->assertSame(UserRole::Buyer, $user->role);
        $this->get(route('seller.apply'))->assertOk()->assertSee('Open your FarSell shop');
        $this->post(route('seller.apply.store'), [...$this->data(), 'status' => 'approved', 'role' => 'seller', 'user_id' => 999])
            ->assertRedirect(route('seller.apply'));
        $this->assertSame('pending', $user->sellerApplication->status);
        $this->assertSame(UserRole::Buyer, $user->fresh()->role);
        $this->assertDatabaseCount('shops', 0);
        $this->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($user->fresh())->get(route('account.profile'))->assertOk()->assertSee('Seller Application');
    }

    public function test_only_admin_can_review_and_approval_atomically_creates_one_owned_shop(): void
    {
        $application = $this->application();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->get(route('admin.sellers.index'))->assertRedirect(route('login'));
        foreach ([UserRole::Buyer, UserRole::Seller, UserRole::Rider] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.sellers.index'))->assertForbidden();
            $this->get(route('admin.sellers.show', $application))->assertForbidden();
            $this->post(route('admin.sellers.review', $application), $this->decision($application))->assertForbidden();
        }
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Review seller applications');
        $this->get(route('admin.sellers.show', $application))->assertOk()->assertSee('Approve seller');
        $this->post(route('admin.sellers.review', $application), $this->decision($application))->assertSessionHasNoErrors()->assertRedirect();
        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame(UserRole::Seller, $application->user->role);
        $this->assertSame('Karl Test Shop', $application->user->shop->name);
        $this->assertTrue($application->user->shop->is_active);
        $this->postJson(route('admin.sellers.review', $application), $this->decision($application))->assertUnprocessable();
        $this->assertDatabaseCount('shops', 1);
        $this->actingAs($application->user)->get(route('seller.apply'))->assertRedirect(route('seller.dashboard'));
        $this->get(route('seller.dashboard'))->assertOk()->assertSee('Add product');
    }

    public function test_rejection_requires_note_and_reapplication_invalidates_old_review(): void
    {
        $application = $this->application();
        $old = $this->decision($application);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->postJson(route('admin.sellers.review', $application), $this->decision($application, ['decision' => 'reject']))->assertUnprocessable();
        $this->post(route('admin.sellers.review', $application), $this->decision($application, ['decision' => 'reject', 'review_note' => 'Describe your products more clearly.']))->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Buyer, $application->user->role);
        $this->actingAs($application->user)->get(route('seller.apply'))->assertOk()->assertSee('Describe your products more clearly.');
        $this->post(route('seller.apply.store'), $this->data())->assertSessionHasNoErrors();
        $application->refresh();
        $this->assertSame('pending', $application->status);
        $this->assertNull($application->reviewed_by);
        $this->assertNotSame($old['revision'], $application->revision);
        $this->actingAs($admin)->postJson(route('admin.sellers.review', $application), $old)->assertUnprocessable();
        $this->assertDatabaseCount('shops', 0);
    }

    public function test_application_cannot_overwrite_other_roles_or_existing_shop(): void
    {
        $this->get(route('seller.apply'))->assertRedirect(route('login'));
        $this->post(route('seller.apply.store'), $this->data())->assertRedirect(route('login'));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        foreach ([UserRole::Rider, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('seller.apply'))->assertForbidden();
            $this->post(route('seller.apply.store'), $this->data())->assertForbidden();
            $application = $this->application($user);
            $this->actingAs($admin)->postJson(route('admin.sellers.review', $application), $this->decision($application))->assertUnprocessable();
            $this->assertSame($role, $user->fresh()->role);
        }
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $application = $this->application($seller);
        $this->actingAs($admin)->postJson(route('admin.sellers.review', $application), $this->decision($application))->assertUnprocessable();
        $this->assertSame($shop->name, $shop->fresh()->name);
        $this->assertDatabaseCount('shops', 1);
    }

    public function test_application_validation_ownership_and_account_deletion_guard(): void
    {
        $other = $this->application();
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->get(route('seller.apply', ['user_id' => $other->user_id]))->assertOk()->assertDontSee($other->shop_name);
        $this->postJson(route('seller.apply.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['shop_name', 'city', 'description']);
        $this->post(route('seller.apply.store'), $this->data())->assertSessionHasNoErrors();
        $this->delete(route('account.profile.destroy'), ['current_password' => 'password', 'confirmation' => 'DELETE'])->assertSessionHasErrors('account', null, 'deletion');
        $this->assertDatabaseHas('users', ['id' => $buyer->id]);
    }

    public function test_admin_queue_filter_pagination_and_validation(): void
    {
        $applications = collect(range(1, 11))->map(fn () => $this->application());
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('admin.sellers.index'))
            ->assertOk()->assertViewHas('applications', fn ($rows) => $rows->total() === 11 && $rows->first()->id === $applications->first()->id);
        $this->get(route('admin.sellers.index', ['page' => 2]))->assertOk()->assertViewHas('applications', fn ($rows) => $rows->count() === 1);
        $this->get(route('admin.sellers.index', ['status' => 'approved']))->assertOk()->assertSee('No approved seller applications.');
        $this->getJson(route('admin.sellers.index', ['status' => 'bogus']))->assertUnprocessable();
        $first = $applications->first();
        $this->postJson(route('admin.sellers.review', $first), $this->decision($first, ['confirmed' => null]))->assertUnprocessable();
    }

    public function test_approval_failure_rolls_back_shop_and_role(): void
    {
        $application = $this->application();
        SellerApplication::updating(function ($record) {
            if ($record->status === 'approved') {
                throw new \RuntimeException('Simulated save failure');
            }
        });
        try {
            $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->post(route('admin.sellers.review', $application), $this->decision($application))->assertStatus(500);
            $this->assertDatabaseCount('shops', 0);
            $this->assertSame(UserRole::Buyer, $application->user->fresh()->role);
            $this->assertSame('pending', $application->fresh()->status);
        } finally {
            SellerApplication::flushEventListeners();
        }
    }
}
