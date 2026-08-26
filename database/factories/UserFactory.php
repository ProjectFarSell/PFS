<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Buyer,
            'phone' => fake()->numerify('09#########'),
            'remember_token' => Str::random(10),
        ];
    }

    public function seller(): static
    {
        return $this->state(['role' => UserRole::Seller]);
    }

    public function rider(): static
    {
        return $this->state(['role' => UserRole::Rider]);
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::Admin]);
    }
}
