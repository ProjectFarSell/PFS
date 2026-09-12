<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->bothify('SKU-????-####')),
            'option_name' => 'Size',
            'option_value' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'price_override' => null,
            'stock' => fake()->numberBetween(0, 50),
            'is_active' => true,
        ];
    }
}
