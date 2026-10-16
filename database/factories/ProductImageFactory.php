<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefixes = ['gadgets', 'mens', 'womens', 'home', 'beauty', 'sports', 'kids', 'surplus'];
        $prefix = fake()->randomElement($prefixes);
        $num = fake()->numberBetween(1, 3);

        return [
            'product_id' => Product::factory(),
            'path' => "products/{$prefix}-{$num}.jpg",
            'alt_text' => fake()->words(3, true),
            'sort_order' => 0,
        ];
    }
}
