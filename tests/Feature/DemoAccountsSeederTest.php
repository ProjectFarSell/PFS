<?php

namespace Tests\Feature;

use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoRiderProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_can_log_in_to_each_role_destination(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        foreach ([
            'admin' => 'admin.dashboard',
            'buyer' => 'home',
            'seller' => 'seller.dashboard',
            'rider' => 'rider.dashboard',
        ] as $role => $destination) {
            $user = User::query()->where('email', "$role@farsell.test")->firstOrFail();
            $this->assertSame(UserRole::from($role), $user->role);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route($destination));
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_demo_rider_has_usable_dashboard_and_request_panel_after_seeding(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $rider = User::where('email', 'rider@farsell.test')->sole();
        $this->assertSame(RiderStatus::Approved, $rider->riderProfile->status);
        $this->assertSame('Quezon City', $rider->riderProfile->city);
        $this->assertFalse($rider->riderProfile->is_available);
        $this->assertSame(0, $rider->riderProfile->documents()->count());
        $this->post('/login', ['email' => $rider->email, 'password' => 'password'])->assertRedirect('/rider/dashboard');
        $this->get('/rider/dashboard')->assertOk()->assertViewHas('canViewDeliveries', true)->assertDontSee('Rider profile not found');
        $this->getJson('/rider/delivery-requests')->assertOk()->assertJsonPath('count', 0);
        $this->assertDatabaseCount('rider_profiles', 1);
    }

    public function test_missing_profile_is_repaired_without_resetting_rider_credentials(): void
    {
        $rider = User::factory()->rider()->create(['email' => 'rider@farsell.test', 'name' => 'Existing Demo Rider', 'password' => 'changed-demo-password']);
        $before = $rider->fresh()->getAttributes();
        $this->seed(DemoAccountsSeeder::class);
        $this->assertSame($before, $rider->fresh()->getAttributes());
        $this->assertTrue($rider->fresh()->riderProfile->isApproved());
        $this->seed(DemoAccountsSeeder::class);
        $this->assertDatabaseCount('rider_profiles', 1);
        $this->post('/login', ['email' => $rider->email, 'password' => 'changed-demo-password'])->assertRedirect('/rider/dashboard');
    }

    public function test_existing_application_decisions_and_profile_details_are_not_overwritten(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $rider = User::where('email', 'rider@farsell.test')->sole();
        foreach (RiderStatus::cases() as $status) {
            $profile = $rider->riderProfile()->firstOrFail();
            $profile->forceFill(['status' => $status, 'city' => 'Pasig City', 'license_no' => 'EXISTING-RECORD', 'is_available' => true])->save();
            $before = $profile->fresh()->getAttributes();
            $this->seed(DemoAccountsSeeder::class);
            $this->assertSame($before, $profile->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('rider_profiles', 1);
    }

    public function test_existing_non_rider_or_suspended_account_is_not_promoted_or_enabled(): void
    {
        $user = User::factory()->create(['email' => 'rider@farsell.test', 'role' => UserRole::Buyer]);
        $this->seed(DemoAccountsSeeder::class);
        $this->assertSame(UserRole::Buyer, $user->fresh()->role);
        $this->assertDatabaseCount('rider_profiles', 0);
        $user->forceFill(['role' => UserRole::Rider, 'suspended_at' => now()])->save();
        $this->seed(DemoAccountsSeeder::class);
        $this->assertNotNull($user->fresh()->suspended_at);
        $this->assertDatabaseCount('rider_profiles', 0);
    }

    public function test_demo_profile_seeder_is_blocked_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only available in local/testing');
        $this->seed(DemoRiderProfileSeeder::class);
    }

    public function test_reseeding_preserves_existing_accounts_and_recreates_missing_accounts(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $admin = User::query()->where('email', 'admin@farsell.test')->firstOrFail();
        $admin->update(['name' => 'Updated Admin', 'password' => 'changed-password', 'role' => UserRole::Buyer]);
        $attributes = $admin->fresh()->getAttributes();
        User::query()->where('email', 'rider@farsell.test')->delete();

        $this->seed(DemoAccountsSeeder::class);

        $this->assertDatabaseCount('users', 4);
        $this->assertSame($attributes, $admin->fresh()->getAttributes());
        $this->assertDatabaseHas('users', ['email' => 'rider@farsell.test', 'role' => 'rider']);
    }
}
