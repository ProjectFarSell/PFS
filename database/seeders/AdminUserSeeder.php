<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@farsell.test'],
            [
                'name' => 'FarSell Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
            ]
        );
    }
}
