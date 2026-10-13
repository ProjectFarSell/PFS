<?php

namespace Database\Factories;

use App\Models\PsgcRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PsgcRegion>
 */
class PsgcRegionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'psgc_code' => fake()->unique()->numerify('##########'),
            'region_code' => fake()->unique()->lexify('??'),
            'name' => fake()->unique()->state(),
        ];
    }
}
