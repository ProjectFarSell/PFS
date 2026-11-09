<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\DemoAccount;
use App\Support\DevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DevelopmentData::assertAllowed();
        $user = User::query()->firstOrCreate(
            ['email' => 'admin@farsell.test'],
            [
                'name' => 'FarSell Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
            ]
        );
        DemoAccount::mark($user);
    }
}
