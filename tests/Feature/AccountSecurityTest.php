<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\SecurityAuditEvent;
use App\Models\Shop;
use App\Models\User;
use App\Services\Security\AdminTwoFactor;
use App\Support\PrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use OTPHP\InternalClock;
use OTPHP\TOTP;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function enrolled(): array
    {
        $user = $this->admin();
        $secret = app(AdminTwoFactor::class)->generate();
        [$codes, $hashes] = app(AdminTwoFactor::class)->recoveryCodes();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now(), 'mfa_recovery_codes' => $hashes])->save();

        return [$user, $secret, $codes];
    }

    private function code(string $secret): string
    {
        return TOTP::createFromSecret($secret, new InternalClock)->at(now()->timestamp);
    }

    public function test_admin_must_enroll_then_pass_mfa_and_sensitive_actions_expire(): void
    {
        config(['security.admin_mfa_required' => true, 'security.sensitive_confirmation_required' => true]);
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('security.index'));
        $this->post('/admin/categories', ['name' => 'Blocked'])->assertStatus(423);
        $this->assertDatabaseMissing('categories', ['name' => 'Blocked']);
        $this->post(route('security.mfa.setup'))->assertSessionHasErrors('identity');
        $this->post(route('security.confirm'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->post(route('security.mfa.setup'))->assertSessionHasNoErrors();
        $pending = session('security.pending_mfa');
        $secret = Crypt::decryptString($pending['secret']);
        $this->get(route('security.index'))->assertOk()->assertSee('data:image/png;base64,', false)->assertSee('Scan this private FarSell authenticator setup QR code');
        $this->post(route('security.mfa.enable'), ['code' => $this->code($secret), 'setup_id' => substr(hash('sha256', $secret), 0, 32)])->assertRedirect()->assertSessionHasNoErrors();
        $admin->refresh();
        $this->assertNotSame($secret, DB::table('users')->where('id', $admin->id)->value('mfa_secret'));
        $this->assertCount(8, $admin->mfa_recovery_codes);
        $display = json_decode(Crypt::decryptString(session('security.recovery_display')), true);
        $this->get(route('security.index'))->assertOk()->assertSee($display[0]);
        $this->get(route('security.index'))->assertDontSee($display[0])->assertDontSee($secret);
        $this->get('/admin')->assertOk();
        $this->travel(301)->seconds();
        $this->post('/admin/categories', ['name' => 'Needs confirmation'])->assertRedirect(route('security.index'));
        $this->assertDatabaseMissing('categories', ['name' => 'Needs confirmation']);
        $this->post(route('security.confirm'), ['code' => $this->code($secret)])->assertSessionHasNoErrors();
        $this->post('/admin/categories', ['name' => 'Confirmed category'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', ['name' => 'Confirmed category']);
    }

    public function test_enrolled_admin_cannot_use_password_instead_of_mfa_and_codes_cannot_replay(): void
    {
        config(['security.admin_mfa_required' => true]);
        [$admin, $secret, $codes] = $this->enrolled();
        $this->actingAs($admin)->post(route('security.confirm'), ['password' => 'password'])->assertSessionHasErrors('identity');
        $this->post(route('security.confirm'), ['code' => $this->code($secret)])->assertSessionHasNoErrors();
        $this->assertFalse(app(AdminTwoFactor::class)->verify($admin, $this->code($secret)));
        $this->assertTrue(app(AdminTwoFactor::class)->verify($admin, $codes[0]));
        $this->assertFalse(app(AdminTwoFactor::class)->verify($admin, $codes[0]));
        $this->assertCount(7, $admin->fresh()->mfa_recovery_codes);
        $json = json_encode($admin->fresh()->toArray());
        $this->assertStringNotContainsString($secret, $json);
        $this->assertStringNotContainsString('mfa_recovery_codes', $json);
    }

    public function test_security_code_attempts_are_rate_limited(): void
    {
        [$admin] = $this->enrolled();
        $this->actingAs($admin);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('security.confirm'), ['code' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson(route('security.confirm'), ['code' => 'wrong'])->assertTooManyRequests();
    }

    public function test_restarting_setup_keeps_the_active_key_already_added_to_the_phone(): void
    {
        $this->actingAs($this->admin())->post(route('security.confirm'), ['password' => 'password'])->assertRedirect();
        $this->post(route('security.mfa.setup'))->assertRedirect();
        $pending = session('security.pending_mfa');
        $this->post(route('security.mfa.setup'))->assertRedirect();
        $this->assertSame($pending, session('security.pending_mfa'));
    }

    public function test_old_unconfirmed_long_key_is_retired_but_enabled_authenticators_keep_working(): void
    {
        $legacySecret = TOTP::generate(new InternalClock, 64)->getSecret();
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['security' => ['version' => 0, 'pending_mfa' => ['secret' => Crypt::encryptString($legacySecret), 'expires' => now()->addMinutes(10)->timestamp]]])
            ->get(route('security.index'))->assertOk()->assertViewHas('setupUpdated', true)->assertDontSee($legacySecret)->assertSessionMissing('security.pending_mfa');
        $admin->forceFill(['mfa_secret' => $legacySecret, 'mfa_confirmed_at' => now()])->save();
        $this->assertTrue(app(AdminTwoFactor::class)->verify($admin, $this->code($legacySecret)));
        $this->assertSame($legacySecret, $admin->fresh()->mfa_secret);
    }

    public function test_stale_setup_form_cannot_enable_a_different_key(): void
    {
        $admin = $this->admin();
        $secret = app(AdminTwoFactor::class)->generate();
        $this->actingAs($admin)->withSession(['security' => [
            'version' => 0, 'confirmed_user' => $admin->id, 'confirmed_at' => now()->timestamp,
            'pending_mfa' => ['secret' => Crypt::encryptString($secret), 'expires' => now()->addMinutes(10)->timestamp],
        ]])->post(route('security.mfa.enable'), ['code' => $this->code($secret), 'setup_id' => str_repeat('0', 32)])->assertSessionHasErrors('code');
        $this->assertNull($admin->fresh()->mfa_confirmed_at);
    }

    public function test_signing_out_other_devices_revokes_old_sessions_but_keeps_current_one(): void
    {
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->post(route('security.confirm'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->post(route('security.sessions.revoke'))->assertSessionHasNoErrors();
        $this->assertSame(1, $buyer->fresh()->security_version);
        $this->get(route('account.profile'))->assertOk();
        $this->withSession(['security' => ['version' => 0]])->get(route('account.profile'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_session_revocation_does_not_turn_google_confirmation_into_mfa(): void
    {
        config(['security.admin_mfa_required' => true]);
        [$admin] = $this->enrolled();
        $this->actingAs($admin)->withSession(['security' => ['version' => 0, 'confirmed_user' => $admin->id, 'confirmed_at' => now()->timestamp]])
            ->post(route('security.sessions.revoke'))->assertSessionHasNoErrors();
        $this->get('/admin')->assertRedirect(route('security.index'));
    }

    public function test_suspension_requires_recent_confirmation_hides_shop_and_revokes_access(): void
    {
        config(['security.sensitive_confirmation_required' => true]);
        $admin = $this->admin();
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.security.accounts.update', $seller), ['action' => 'suspend', 'reason' => 'Confirmed account abuse'])->assertRedirect(route('security.index'));
        $this->assertNull($seller->fresh()->suspended_at);
        $this->post(route('security.confirm'), ['password' => 'password']);
        $this->post(route('admin.security.accounts.update', $seller), ['action' => 'suspend', 'reason' => 'Confirmed account abuse'])->assertSessionHasNoErrors();
        $this->assertNotNull($seller->fresh()->suspended_at);
        $this->assertFalse($shop->fresh()->is_active);
        $this->actingAs($seller->fresh())->get('/seller')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $seller->email, 'password' => 'password'])->assertSessionHasErrors('email', null, 'login');
        $this->assertGuest();
        $this->actingAs($admin)->withSession(['security' => ['version' => 0, 'confirmed_user' => $admin->id, 'confirmed_at' => now()->timestamp]])
            ->post(route('admin.security.accounts.update', $seller), ['action' => 'restore', 'reason' => 'Review completed', 'restore_shop' => 1])->assertSessionHasNoErrors();
        $this->assertNull($seller->fresh()->suspended_at);
        $this->assertTrue($shop->fresh()->is_active);
        $this->assertSame(2, $seller->fresh()->security_version);
        $this->post(route('admin.security.accounts.update', $admin), ['action' => 'suspend', 'reason' => 'Self suspension'])->assertForbidden();
    }

    public function test_audit_records_changes_excludes_secrets_and_is_admin_only(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/categories', ['name' => 'Old name'])->assertSessionHasNoErrors();
        $category = Category::query()->where('name', 'Old name')->sole();
        $this->put(route('admin.categories.update', $category), ['name' => 'New name'])->assertSessionHasNoErrors();
        $event = SecurityAuditEvent::query()->where('action', 'Category.updated')->sole();
        $this->assertSame(['before' => 'Old name', 'after' => 'New name'], $event->changes['name']);
        $admin->forceFill(['password' => 'TopSecret-New-Password-123!', 'mfa_secret' => 'UNLOGGEDSECRET'])->save();
        $audit = json_encode(SecurityAuditEvent::query()->get()->toArray());
        $this->assertStringNotContainsString('UNLOGGEDSECRET', $audit);
        $this->assertStringNotContainsString('TopSecret', $audit);
        $this->assertSame($admin->id, $event->actor_id);
        $this->actingAs(User::factory()->create())->get(route('admin.security.audit'))->assertForbidden();
        $this->actingAs(User::factory()->seller()->create())->get(route('admin.security.accounts'))->assertForbidden();
        $this->expectException(\LogicException::class);
        $event->update(['action' => 'tampered']);
    }

    public function test_active_and_suspended_accounts_have_separate_searchable_pages(): void
    {
        $admin = $this->admin();
        $active = User::factory()->create(['name' => 'Active Example']);
        $suspended = User::factory()->create(['name' => 'Suspended Example']);
        $suspended->forceFill(['suspended_at' => now()])->save();
        $this->actingAs($admin)->get(route('admin.security.accounts'))->assertOk()->assertSee($active->email)->assertDontSee($suspended->email)->assertSee('Suspend account')->assertDontSee('name="action" value="restore"', false);
        $this->get(route('admin.security.accounts.suspended'))->assertOk()->assertSee($suspended->email)->assertDontSee($active->email)->assertDontSee('name="action"', false);
        $this->get(route('admin.security.accounts.restore'))->assertOk()->assertSee($suspended->email)->assertDontSee($active->email)->assertSee('name="action" value="restore"', false)->assertDontSee('name="action" value="suspend"', false);
        $this->get(route('admin.security.accounts.suspended', ['q' => 'Active Example']))->assertOk()->assertSee('No suspended accounts found.')->assertDontSee($active->email);
        $this->actingAs($active)->get(route('admin.security.accounts.suspended'))->assertForbidden();
        $this->get(route('admin.security.accounts.restore'))->assertForbidden();
    }

    public function test_google_confirmation_is_bound_to_the_current_account_and_never_grants_mfa(): void
    {
        config(['security.admin_mfa_required' => true, 'services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        [$admin] = $this->enrolled();
        $admin->forceFill(['google_id' => 'google-admin'])->save();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'dummy']), 'openidconnect.googleapis.com/*' => Http::response(['sub' => 'google-admin', 'email' => $admin->email, 'email_verified' => true, 'name' => 'Admin'])]);
        $this->actingAs($admin)->post(route('security.google'))->assertRedirect();
        $state = session('google_oauth_state');
        $this->get(route('auth.google.callback', ['code' => 'dummy', 'state' => $state]))->assertRedirect(route('security.index'));
        $this->assertSame($admin->id, session('security.confirmed_user'));
        $this->assertNull(session('security.mfa'));
        $this->get('/admin')->assertRedirect(route('security.index'));
    }

    public function test_google_sign_in_refuses_suspended_accounts(): void
    {
        $user = User::factory()->create(['google_id' => 'blocked-google']);
        $user->forceFill(['suspended_at' => now()])->save();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'dummy']), 'openidconnect.googleapis.com/*' => Http::response(['sub' => 'blocked-google', 'email' => $user->email, 'email_verified' => true, 'name' => 'Blocked'])]);
        $this->withSession(['google_oauth_state' => 'state', 'google_privacy_consent' => PrivacyNotice::VERSION])->get(route('auth.google.callback', ['code' => 'dummy', 'state' => 'state']))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_operator_mfa_reset_requires_explicit_confirmation_and_revokes_sessions(): void
    {
        [$admin] = $this->enrolled();
        $this->artisan('ops:reset-admin-mfa', ['email' => $admin->email])
            ->expectsQuestion('Type RESET '.$admin->email.' after independently verifying this administrator', 'cancel')
            ->assertFailed();
        $this->assertNotNull($admin->fresh()->mfa_confirmed_at);
        $this->artisan('ops:reset-admin-mfa', ['email' => $admin->email])
            ->expectsQuestion('Type RESET '.$admin->email.' after independently verifying this administrator', 'RESET '.$admin->email)
            ->assertSuccessful();
        $this->assertNull($admin->fresh()->mfa_secret);
        $this->assertSame(1, $admin->fresh()->security_version);
        $this->assertDatabaseHas('security_audit_events', ['action' => 'mfa.reset_by_operator', 'target_id' => $admin->id]);
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('ops:reset-admin-mfa', ['email' => $admin->email])->assertFailed();
    }

    public function test_wrong_google_account_cannot_confirm_identity(): void
    {
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        $buyer = User::factory()->create(['google_id' => 'correct-subject']);
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'dummy']), 'openidconnect.googleapis.com/*' => Http::response(['sub' => 'wrong-subject', 'email' => 'different@example.test', 'email_verified' => true, 'name' => 'Other'])]);
        $this->actingAs($buyer)->post(route('security.google'))->assertRedirect();
        $state = session('google_oauth_state');
        $this->get(route('auth.google.callback', ['code' => 'dummy', 'state' => $state]))->assertSessionHasErrors('identity');
        $this->assertNull(session('security.confirmed_user'));
        $this->assertSame($buyer->id, auth()->id());
    }

    public function test_database_session_listing_is_private_and_revocation_removes_other_rows(): void
    {
        config(['session.driver' => 'database']);
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'buyer-other-device', 'user_id' => $buyer->id, 'user_agent' => 'Buyer known browser', 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp],
            ['id' => 'someone-else-session', 'user_id' => $other->id, 'user_agent' => 'Other buyer private browser', 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp],
        ]);
        $this->actingAs($buyer)->get(route('security.index'))->assertOk()->assertSee('Buyer known browser')->assertDontSee('Other buyer private browser')->assertDontSee('buyer-other-device');
        $this->post(route('security.confirm'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->post(route('security.sessions.revoke'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'buyer-other-device']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else-session']);
        $this->get(route('account.profile'))->assertOk();
    }
}
