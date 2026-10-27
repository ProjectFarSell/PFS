<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        foreach ([
            ['email' => 'buyer@farsell.test', 'name' => 'Guest Buyer', 'role' => UserRole::Buyer],
            ['email' => 'seller@farsell.test', 'name' => 'Demo Seller', 'role' => UserRole::Seller],
            ['email' => 'rider@farsell.test', 'name' => 'Demo Rider', 'role' => UserRole::Rider],
        ] as $account) {
            // Preserve existing credentials and profile changes on subsequent runs.
            User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'password' => Hash::make('password'),
                ]
            );
        }
    }
}
