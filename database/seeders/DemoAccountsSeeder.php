<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\DevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DevelopmentData::assertAllowed();
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

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoRiderProfileSeeder::class);
        }
    }
}
