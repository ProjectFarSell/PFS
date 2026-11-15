<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogSections;
use App\Support\DevelopmentData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoProductCategoriesSeeder extends Seeder
{
    /** Apply only the approved image/category mapping; preserve stock and seller edits. */
    public function run(): void
    {
        DevelopmentData::assertAllowed();
        DB::transaction(function (): void {
            $ids = [];
            $order = 1;
            foreach (CatalogSections::SECTIONS as $slug => [$name]) {
                $ids[$slug] = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'sort_order' => $order++])->id;
            }

            $rows = json_decode(file_get_contents(database_path('seeders/demo-catalog-products.json')), true, flags: JSON_THROW_ON_ERROR);
            foreach ($rows as $row) {
                if (isset($row['category_slug'])) {
                    Product::whereHas('shop', fn ($q) => $q->where('slug', 'metro-surplus'))
                        ->where('slug', $row['slug'])->where('image_path', $row['image_path'])
                        ->update(['category_id' => $ids[$row['category_slug']]]);
                }
            }

            // The Sun & Moon image belongs to the separate seller demo listing.
            Product::where('image_path', 'products/prod-24-ph-11134207-7rase-m6w744f3a959bd.jpg')
                ->whereHas('variants', fn ($q) => $q->where('sku', 'AGAP-BKXLS'))
                ->update(['category_id' => $ids['trading-cards']]);
        });
    }
}
