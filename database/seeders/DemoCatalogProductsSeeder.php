<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoCatalogProductsSeeder extends Seeder
{
    /** Seed the 24 sample catalog listings used by the demo shop. */
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'metro-surplus')->firstOrFail();
        $category = Category::query()->where('name', "Women's")->firstOrFail();
        $path = database_path('seeders/demo-catalog-products.json');
        $products = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (count($products) !== 24) {
            throw new RuntimeException('The demo catalog must contain exactly 24 sample products.');
        }

        foreach ($products as $data) {
            $shop->products()->firstOrCreate(
                ['slug' => $data['slug']],
                [...$data, 'category_id' => $category->id]
            );
        }
    }
}
