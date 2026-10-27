<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
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
