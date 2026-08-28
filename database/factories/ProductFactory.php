<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);
        $price = fake()->randomFloat(2, 99, 4999);

        return [
            'shop_id' => Shop::factory(),
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->paragraph(),
            'price' => $price,
            'compare_at_price' => fake()->boolean(40) ? $price * 1.25 : null,
            'stock' => fake()->numberBetween(3, 80),
            'is_flash' => fake()->boolean(25),
            'is_active' => true,
        ];
    }
}
