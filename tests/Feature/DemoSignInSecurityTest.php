<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Mobile\MobileFixtures;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AdminTwoFactor;
use App\Support\DemoAccount;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DemoSignInSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.admin_mfa_required' => true, 'security.seller_mfa_required' => true,
            'security.login_otp_required' => true, 'security.sensitive_confirmation_required' => true]);
    }

    public function test_all_four_seeded_demos_skip_second_factor_without_changing_real_account_policy(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        foreach (['admin' => 'admin.dashboard', 'buyer' => 'home', 'seller' => 'seller.dashboard', 'rider' => 'rider.dashboard'] as $role => $destination) {
            $user = User::where('email', $role.'@farsell.test')->sole();
            $this->assertTrue($user->is_demo_account);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route($destination));
            $this->assertNull(session('security.otp'));
            $this->assertNull(session('security.mfa'));
            $this->get(route('account.profile'))->assertOk();
            $this->post(route('logout'));
        }
        $this->assertDatabaseCount('sign_in_challenges', 0);
        $this->assertTrue(config('security.admin_mfa_required'));
        $this->assertTrue(config('security.seller_mfa_required'));
        $this->assertTrue(config('security.login_otp_required'));
    }

    public function test_regular_accounts_including_test_email_addresses_keep_their_required_second_factor(): void
    {
        foreach (['buyer', 'rider', 'seller', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'email' => $role.'-real@example.test']);
            $this->assertFalse($user->fresh()->is_demo_account);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route(in_array($role, ['seller', 'admin']) ? 'security.index' : 'security.otp.show'));
            $this->post(route('logout'));
        }
        $reservedButNotSeeded = User::factory()->create(['email' => 'buyer@farsell.test']);
        $this->assertTrue(app(AccountSecurity::class)->requiresOtp($reservedButNotSeeded));
    }

    public function test_seeded_mobile_demo_accounts_skip_web_and_api_second_factor_but_pending_riders_remain_blocked(): void
    {
        app(MobileFixtures::class)->seed();
        foreach (['admin' => 'admin.dashboard', 'buyer' => 'home', 'seller' => 'seller.dashboard', 'rider' => 'rider.dashboard', 'pending-rider' => 'rider.dashboard'] as $name => $destination) {
            $user = User::where('email', 'mobile-'.$name.'@farsell.test')->sole();
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route($destination));
            $this->post(route('logout'));
            if (in_array($user->role->value, ['buyer', 'rider'], true)) {
                $token = $this->postJson('/api/v1/auth/login', ['app' => $user->role->value, 'email' => $user->email, 'password' => 'password'])
                    ->assertOk()->assertJsonMissingPath('data.challenge_token')->json('data.access_token');
                if ($name === 'pending-rider') {
                    $this->withToken($token)->getJson('/api/v1/rider/shipments')->assertForbidden();
                }
            }
        }
        $this->assertDatabaseCount('sign_in_challenges', 0);
        foreach (['staging', 'production'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            foreach (User::where('email', 'like', 'mobile-%@farsell.test')->get() as $demo) {
                $this->assertFalse(DemoAccount::exempt($demo));
                $this->assertTrue($demo->usesAuthenticator()
                    ? app(AccountSecurity::class)->requiresAuthenticator($demo)
                    : app(AccountSecurity::class)->requiresOtp($demo));
            }
        }
    }

    public function test_demo_login_discards_a_remembered_second_factor_destination(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        foreach (['security.index', 'security.otp.show'] as $route) {
            $this->withSession(['url.intended' => route($route)])
                ->post('/login', ['email' => 'buyer@farsell.test', 'password' => 'password'])->assertRedirect(route('home'));
            $this->post(route('logout'));
        }
    }

    public function test_demo_marker_alone_or_wrong_role_or_changed_email_cannot_grant_an_exemption(): void
    {
        $user = User::factory()->create(['email' => 'ordinary@example.test', 'is_demo_account' => true]);
        $this->assertTrue(app(AccountSecurity::class)->requiresOtp($user));
        $user->update(['email' => 'admin@farsell.test']);
        $this->assertTrue(app(AccountSecurity::class)->requiresOtp($user));
        $user->update(['role' => 'admin']);
        $this->assertFalse(app(AccountSecurity::class)->requiresAuthenticator($user));
        $user->update(['email' => 'new-admin@example.test']);
        $this->assertTrue(app(AccountSecurity::class)->requiresAuthenticator($user));
    }

    public function test_demo_exemption_never_applies_in_staging_or_production(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $admin = User::where('email', 'admin@farsell.test')->sole();
        $buyer = User::where('email', 'buyer@farsell.test')->sole();
        foreach (['staging', 'production'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->assertFalse(DemoAccount::exempt($admin));
            $this->assertTrue(app(AccountSecurity::class)->requiresAuthenticator($admin));
            $this->assertTrue(app(AccountSecurity::class)->requiresOtp($buyer));
        }
    }

    public function test_old_demo_authenticator_is_preserved_but_demo_confirmation_uses_password_and_admin_writes_still_require_identity(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $admin = User::where('email', 'admin@farsell.test')->sole();
        $secret = app(AdminTwoFactor::class)->generate();
        $admin->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk();
        $this->post('/admin/categories', ['name' => 'Demo category'])->assertSessionHasNoErrors();
        $this->travel(301)->seconds();
        $this->post('/admin/categories', ['name' => 'Needs password'])->assertRedirect(route('security.index'));
        $this->get(route('security.index'))->assertOk()->assertSee('Demo account:')->assertSee('Current password')->assertDontSee('Set up authenticator');
        $this->post(route('security.confirm'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->post('/admin/categories', ['name' => 'After confirmation'])->assertSessionHasNoErrors();
        $this->assertSame($secret, $admin->fresh()->mfa_secret);
        $this->assertNull(session('security.mfa'));
        $this->post(route('security.mfa.setup'))->assertForbidden();
    }

    public function test_demo_accounts_still_require_correct_password_role_and_unsuspended_account(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $this->post('/login', ['email' => 'buyer@farsell.test', 'password' => 'wrong'])->assertSessionHasErrors('email', null, 'login');
        $buyer = User::where('email', 'buyer@farsell.test')->sole();
        $buyer->forceFill(['suspended_at' => now()])->save();
        $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])->assertSessionHasErrors('email', null, 'login');
        $this->assertGuest();
        $this->assertDatabaseCount('sign_in_challenges', 0);
    }

    public function test_registration_and_profile_cannot_set_the_demo_marker(): void
    {
        $this->post('/register', ['name' => 'Regular Buyer', 'email' => 'new@example.test', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'privacy_consent' => 1, 'is_demo_account' => 1])->assertRedirect(route('security.otp.show'));
        $user = User::where('email', 'new@example.test')->sole();
        $this->assertFalse($user->is_demo_account);
        $this->assertFalse((new User)->isFillable('is_demo_account'));
        $this->withSession(['security.otp' => app(AccountSecurity::class)->otpFingerprint($user)])
            ->patch(route('account.profile.update'), ['name' => $user->name, 'email' => $user->email,
                'current_password' => 'Password123!', 'is_demo_account' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->is_demo_account);
    }

    public function test_migration_backfills_only_reserved_demo_identity_and_role_without_resetting_credentials(): void
    {
        $legacy = User::factory()->create(['email' => 'seller@farsell.test', 'role' => 'seller', 'password' => 'changed-password']);
        $normal = User::factory()->create(['email' => 'another@example.test']);
        $wrongRole = User::factory()->create(['email' => 'admin@farsell.test', 'role' => 'buyer']);
        Schema::table('users', function ($table) {
            $table->dropIndex(['is_demo_account']);
            $table->dropColumn('is_demo_account');
        });
        $migration = require database_path('migrations/2026_11_09_000002_mark_seeded_demo_accounts.php');
        $migration->up();
        $this->assertTrue($legacy->fresh()->is_demo_account);
        $this->assertTrue(Hash::check('changed-password', $legacy->fresh()->password));
        $this->assertFalse($normal->fresh()->is_demo_account);
        $this->assertFalse($wrongRole->fresh()->is_demo_account);
    }
}
