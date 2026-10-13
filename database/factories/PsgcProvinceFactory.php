<?php

namespace Database\Factories;

use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PsgcProvince>
 */
class PsgcProvinceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'psgc_region_id' => PsgcRegion::factory(),
            'psgc_code' => fake()->unique()->numerify('##########'),
            'province_code' => fake()->unique()->lexify('???'),
            'name' => fake()->unique()->city(),
        ];
    }
}
