<?php

namespace Database\Factories;

use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PsgcCityMunicipality>
 */
class PsgcCityMunicipalityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'psgc_province_id' => PsgcProvince::factory(),
            'psgc_code' => fake()->unique()->numerify('##########'),
            'city_municipality_code' => fake()->unique()->numerify('#####'),
            'name' => fake()->unique()->city(),
        ];
    }
}
