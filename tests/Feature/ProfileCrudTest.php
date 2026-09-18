<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileCrudTest extends TestCase
{
    use RefreshDatabase;

    private function details(User $user, array $overrides = []): array
    {
        return ['name' => 'Updated Name', 'email' => $user->email, 'phone' => '+63 917 123 4567', 'current_password' => 'password', ...$overrides];
    }

    private function newPassword(array $overrides = []): array
    {
        return ['current_password' => 'password', 'password' => 'New-Secure-Password-123', 'password_confirmation' => 'New-Secure-Password-123', ...$overrides];
    }

    public function test_profile_mutations_and_editor_require_authentication(): void
    {
        $this->get(route('account.profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('account.profile.update'), [])->assertRedirect(route('login'));
        $this->put(route('account.profile.password'), [])->assertRedirect(route('login'));
        $this->delete(route('account.profile.destroy'), [])->assertRedirect(route('login'));
    }

    public function test_all_roles_can_edit_their_own_details_but_not_role_or_another_account(): void
    {
        $other = User::factory()->create();
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $originalPassword = $user->password;
            $this->actingAs($user)->get(route('account.profile'))->assertOk()->assertSee(route('account.profile.edit'), false);
            $this->get(route('account.profile.edit', ['user_id' => $other->id]))->assertOk()->assertSee($user->email)->assertDontSee($other->email);
            $this->patch(route('account.profile.update'), $this->details($user, [
                'user_id' => $other->id, 'id' => $other->id, 'role' => 'admin', 'status' => 'approved',
                'password' => 'injected-password', 'email_verified_at' => now()->toISOString(),
            ]))->assertRedirect(route('account.profile'))->assertSessionHasNoErrors();
            $user->refresh();
            $this->assertSame('Updated Name', $user->name);
            $this->assertSame('+63 917 123 4567', $user->phone);
            $this->assertSame($role, $user->role);
            $this->assertSame($originalPassword, $user->password);
        }
        $this->assertSame($other->name, $other->fresh()->name);
        $this->assertSame($other->email, $other->fresh()->email);
    }

    public function test_email_change_clears_verification_and_old_reset_token_but_not_other_users_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('password_reset_tokens')->insert([
            ['email' => $user->email, 'token' => 'old-token', 'created_at' => now()],
            ['email' => 'other@example.test', 'token' => 'other-token', 'created_at' => now()],
        ]);
        $oldEmail = $user->email;
        $this->actingAs($user)->patch(route('account.profile.update'), $this->details($user, ['email' => 'updated@example.test']))
            ->assertRedirect(route('account.profile'));
        $this->assertSame('updated@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'other@example.test']);
    }

    public function test_unchanged_email_keeps_verification_and_phone_can_be_cleared(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'phone' => '09171234567']);
        $verifiedAt = $user->email_verified_at->toISOString();
        $this->actingAs($user)->patch(route('account.profile.update'), $this->details($user, ['phone' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->phone);
        $this->assertSame($verifiedAt, $user->fresh()->email_verified_at->toISOString());
    }

    public function test_invalid_details_and_case_insensitive_duplicate_email_are_rejected_without_password_flash(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'Taken@Example.test']);
        $this->actingAs($user);
        foreach ([
            ['name' => ''], ['email' => 'bad-email'], ['email' => 'taken@example.test'],
            ['phone' => 'not a phone'], ['phone' => str_repeat('1', 31)], ['current_password' => 'wrong'],
        ] as $invalid) {
            $this->from(route('account.profile.edit'))->patch(route('account.profile.update'), $this->details($user, $invalid))
                ->assertRedirect(route('account.profile.edit'))->assertSessionHasErrors(array_keys($invalid), null, 'profile')
                ->assertSessionMissing('_old_input.current_password');
        }
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_profile_edits_do_not_modify_saved_addresses_or_rider_review_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::Rider]);
        $profile = $user->riderProfile()->create([
            'vehicle_type' => 'bicycle', 'license_no' => 'TEST', 'city' => 'Manila', 'status' => RiderStatus::Approved,
        ]);
        $address = Address::factory()->create(['user_id' => $user->id, 'phone' => '09171234567']);
        $this->actingAs($user)->patch(route('account.profile.update'), $this->details($user))->assertSessionHasNoErrors();
        $this->assertSame(RiderStatus::Approved, $profile->fresh()->status);
        $this->assertSame('09171234567', $address->fresh()->phone);
    }

    public function test_password_change_hashes_password_clears_session_and_requires_new_credentials(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $user->remember_token;
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset', 'created_at' => now()]);
        $this->actingAs($user)->withSession(['cart' => ['test' => 1]])
            ->put(route('account.profile.password'), $this->newPassword(['user_id' => $other->id]))
            ->assertRedirect(route('login'))->assertSessionMissing('cart')
            ->assertSessionHas('status', 'Password changed. Sign in again with your new password.');
        $this->assertGuest();
        $this->assertTrue(Hash::check('New-Secure-Password-123', $user->fresh()->password));
        $this->assertNotSame('New-Secure-Password-123', $user->fresh()->password);
        $this->assertNotSame($token, $user->fresh()->remember_token);
        $this->assertTrue(Hash::check('password', $other->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email', null, 'login');
        $this->assertGuest();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'New-Secure-Password-123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_wrong_current_password_weak_password_and_mismatched_confirmation_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([
            ['current_password' => 'wrong'], ['password' => 'short', 'password_confirmation' => 'short'],
            ['password_confirmation' => 'mismatch'], ['password' => 'password', 'password_confirmation' => 'password'],
        ] as $invalid) {
            $this->from(route('account.profile.edit'))->put(route('account.profile.password'), $this->newPassword($invalid))
                ->assertRedirect(route('account.profile.edit'))->assertSessionHasErrors([], null, 'password')
                ->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');
        }
        $this->assertSame($user->password, $user->fresh()->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_old_password_session_is_rejected_after_password_change(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->update(['password' => 'DifferentPassword123']);
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])
            ->get(route('account.profile'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_change_removes_only_own_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach ([['own-device-one', $user], ['own-device-two', $user], ['other-device', $other]] as [$id, $owner]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner->id, 'payload' => base64_encode('{}'), 'last_activity' => time()]);
        }
        $this->actingAs($user)->put(route('account.profile.password'), $this->newPassword())->assertRedirect(route('login'));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-device']);
    }

    public function test_eligible_buyer_can_delete_only_own_account_and_saved_addresses(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $otherAddress = Address::factory()->create(['user_id' => $other->id]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset', 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => 'another-device', 'user_id' => $user->id, 'payload' => base64_encode('{}'), 'last_activity' => time()]);
        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk()->assertSee('Permanently delete my account');
        $this->delete(route('account.profile.destroy'), ['current_password' => 'password', 'confirmation' => 'DELETE', 'user_id' => $other->id])
            ->assertRedirect(route('login'))->assertSessionHas('status', 'Your account and saved addresses have been permanently deleted.');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('addresses', ['id' => $otherAddress->id]);
    }

    public function test_account_deletion_requires_password_and_explicit_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([['current_password' => 'wrong', 'confirmation' => 'DELETE'], ['current_password' => 'password', 'confirmation' => 'delete']] as $invalid) {
            $this->from(route('account.profile.edit'))->delete(route('account.profile.destroy'), $invalid)
                ->assertRedirect(route('account.profile.edit'))->assertSessionHasErrors([], null, 'deletion');
        }
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_privileged_accounts_and_buyers_with_marketplace_records_cannot_self_delete(): void
    {
        $accounts = collect([UserRole::Admin, UserRole::Seller, UserRole::Rider])->map(fn ($role) => User::factory()->create(['role' => $role]));
        $shopOwner = User::factory()->create();
        $shop = Shop::factory()->create(['user_id' => $shopOwner->id]);
        $applicant = User::factory()->create();
        $profile = $applicant->riderProfile()->create(['vehicle_type' => 'bicycle', 'license_no' => 'TEST', 'city' => 'Manila', 'status' => RiderStatus::Rejected]);
        $buyer = User::factory()->create();
        $order = $buyer->orders()->create([
            'number' => 'KEEP-HISTORY', 'status' => OrderStatus::Delivered, 'payment_method' => PaymentMethod::Cod,
            'ship_to' => 'Original address', 'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149,
        ]);
        foreach ($accounts->concat([$shopOwner, $applicant, $buyer]) as $account) {
            $this->actingAs($account)->get(route('account.profile.edit'))->assertOk()->assertSee('Self-deletion is unavailable')
                ->assertDontSee('Permanently delete my account');
            $this->delete(route('account.profile.destroy'), ['current_password' => 'password', 'confirmation' => 'DELETE'])
                ->assertSessionHasErrors('account', null, 'deletion');
            $this->assertDatabaseHas('users', ['id' => $account->id]);
        }
        $this->assertDatabaseHas('shops', ['id' => $shop->id]);
        $this->assertDatabaseHas('rider_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $buyer->id]);
    }

    public function test_failed_forms_render_their_own_errors_and_never_repopulate_passwords(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from(route('account.profile.edit'))
            ->patch(route('account.profile.update'), $this->details($user, ['name' => '', 'current_password' => 'secret-invalid']))
            ->assertRedirect(route('account.profile.edit'));
        $this->get(route('account.profile.edit'))->assertOk()->assertSee('The name field is required.')
            ->assertDontSee('secret-invalid')->assertSee('name="_token"', false)
            ->assertSee('value="PATCH"', false)->assertSee('value="PUT"', false)->assertSee('value="DELETE"', false);
    }

    public function test_password_and_deletion_errors_are_visible_after_redirect(): void
    {
        $this->actingAs(User::factory()->create())->from(route('account.profile.edit'))
            ->put(route('account.profile.password'), $this->newPassword(['current_password' => 'secret-invalid']))
            ->assertRedirect(route('account.profile.edit'));
        $this->get(route('account.profile.edit'))->assertOk()->assertSee('The password is incorrect.')
            ->assertDontSee('secret-invalid')->assertDontSee('New-Secure-Password-123');

        $this->from(route('account.profile.edit'))->delete(route('account.profile.destroy'), [
            'current_password' => 'password', 'confirmation' => 'not-confirmed',
        ])->assertRedirect(route('account.profile.edit'));
        $this->get(route('account.profile.edit'))->assertOk()->assertSee('The selected confirmation is invalid.');
    }

    public function test_failed_deletion_rolls_back_token_removal_and_preserves_account_and_addresses(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'retained', 'created_at' => now()]);
        User::deleting(function () {
            throw new \RuntimeException('Simulated deletion failure');
        });
        try {
            $this->actingAs($user)->delete(route('account.profile.destroy'), ['current_password' => 'password', 'confirmation' => 'DELETE'])
                ->assertStatus(500);
            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertDatabaseHas('addresses', ['id' => $address->id]);
            $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
            $this->assertAuthenticatedAs($user);
        } finally {
            User::flushEventListeners();
        }
    }

    public function test_sensitive_mutations_are_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->deleteJson(route('account.profile.destroy'), ['current_password' => 'wrong', 'confirmation' => 'DELETE'])->assertUnprocessable();
        }
        $this->deleteJson(route('account.profile.destroy'), ['current_password' => 'wrong', 'confirmation' => 'DELETE'])->assertTooManyRequests();
    }
}
