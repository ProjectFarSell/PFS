<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Shop;
use App\Support\DevelopmentData;
use Illuminate\Database\Seeder;

class AdditionalDemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        DevelopmentData::assertAllowed();
        $shop = Shop::where('slug', 'metro-surplus')->firstOrFail();
        $rows = json_decode(file_get_contents(database_path('seeders/additional-demo-products.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($rows as $data) {
            $category = Category::where('slug', $data['category_slug'])->firstOrFail();
            unset($data['category_slug']);
            $shop->products()->firstOrCreate(['slug' => $data['slug']], [...$data, 'category_id' => $category->id]);
        }
    }
}
