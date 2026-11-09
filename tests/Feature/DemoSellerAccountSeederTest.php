<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Shop;
use App\Models\SignInChallenge;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AdminTwoFactor;
use App\Support\DemoAccount;
use Database\Seeders\DemoSellerAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class DemoSellerAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_repair_replaces_unlinked_buyer_revokes_recovery_and_creates_exempt_seller_with_own_shop(): void
    {
        config(['session.driver' => 'database', 'security.seller_mfa_required' => true, 'security.login_otp_required' => true]);
        $old = User::factory()->create(['email' => 'seller@farsell.test']);
        $token = Password::createToken($old);
        DB::table('sessions')->insert(['id' => 'old-seller-device', 'user_id' => $old->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        SignInChallenge::create(['user_id' => $old->id, 'context_hash' => str_repeat('a', 64), 'identity_hash' => str_repeat('b', 64),
            'channel' => 'email', 'code_hash' => Hash::make('123456'), 'sent_at' => now(), 'expires_at' => now()->addMinutes(10)]);
        $other = Shop::factory()->create(['slug' => 'metro-surplus']);
        $this->seed(DemoSellerAccountSeeder::class);
        $seller = User::where('email', 'seller@farsell.test')->sole();
        $this->assertNotEquals($old->id, $seller->id);
        $this->assertDatabaseMissing('users', ['id' => $old->id]);
        $this->assertEquals('seller', $seller->role->value);
        $this->assertTrue(DemoAccount::exempt($seller));
        $this->assertTrue(Hash::check('password', $seller->password));
        $this->assertTrue($seller->shop->is_active);
        $this->assertEquals(0, $seller->shop->products()->count());
        $this->assertEquals($other->user_id, $other->fresh()->user_id);
        $this->assertFalse(Password::tokenExists($seller, $token));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-seller-device']);
        $this->assertDatabaseCount('sign_in_challenges', 0);
        $this->post('/login', ['email' => $seller->email, 'password' => 'password'])->assertRedirect(route('seller.dashboard'));
        $this->get(route('seller.dashboard'))->assertOk()->assertSee('Demo Seller Shop');
    }

    public function test_reruns_preserve_existing_seller_password_mfa_and_shop(): void
    {
        $seller = User::factory()->seller()->create(['email' => 'seller@farsell.test', 'password' => 'changed-password']);
        $secret = app(AdminTwoFactor::class)->generate();
        $seller->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();
        $shop = Shop::factory()->create(['user_id' => $seller->id, 'name' => 'Existing Seller Shop']);
        $this->seed(DemoSellerAccountSeeder::class);
        $this->seed(DemoSellerAccountSeeder::class);
        $this->assertEquals($seller->id, User::where('email', 'seller@farsell.test')->sole()->id);
        $this->assertTrue(Hash::check('changed-password', $seller->fresh()->password));
        $this->assertSame($secret, $seller->fresh()->mfa_secret);
        $this->assertTrue(DemoAccount::exempt($seller->fresh()));
        $this->assertEquals($shop->id, $seller->fresh()->shop->id);
        $this->assertEquals('Existing Seller Shop', $shop->fresh()->name);
        $this->assertDatabaseCount('shops', 1);
    }

    public function test_linked_records_prevent_deleting_or_promoting_the_reserved_buyer(): void
    {
        $buyer = User::factory()->create(['email' => 'seller@farsell.test']);
        $address = Address::factory()->create(['user_id' => $buyer->id]);
        try {
            $this->seed(DemoSellerAccountSeeder::class);
            $this->fail('A linked account must not be replaced.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('linked records', $exception->getMessage());
        }
        $this->assertEquals('buyer', $buyer->fresh()->role->value);
        $this->assertFalse($buyer->fresh()->is_demo_account);
        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
        $this->assertDatabaseCount('shops', 0);
    }

    public function test_missing_seller_can_be_created_without_touching_other_users(): void
    {
        $other = User::factory()->create();
        $attributes = $other->fresh()->getAttributes();
        $this->seed(DemoSellerAccountSeeder::class);
        $this->assertSame($attributes, $other->fresh()->getAttributes());
        $seller = User::where('email', 'seller@farsell.test')->sole();
        $this->assertFalse(app(AccountSecurity::class)->requiresAuthenticator($seller));
        $this->assertTrue($seller->shop->is_active);
    }

    public function test_production_staging_suspension_and_unexpected_roles_are_refused(): void
    {
        foreach (['staging', 'production'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            try {
                app(DemoSellerAccountSeeder::class)->run();
                $this->fail('Repair must be restricted to local/testing.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('local/testing', $exception->getMessage());
            }
        }
        $this->app->detectEnvironment(fn () => 'testing');
        $user = User::factory()->create(['email' => 'seller@farsell.test', 'role' => 'admin']);
        foreach ([false, true] as $suspended) {
            $user->forceFill(['role' => $suspended ? 'seller' : 'admin', 'suspended_at' => $suspended ? now() : null])->save();
            try {
                $this->seed(DemoSellerAccountSeeder::class);
                $this->fail('Unexpected account state must be preserved.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('suspended or has an unexpected role', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('shops', 0);
    }
}
