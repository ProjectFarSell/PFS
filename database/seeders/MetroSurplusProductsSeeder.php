<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MetroSurplusProductsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Metro Surplus demo catalog is restricted to local/testing.');
        }
        $rows = json_decode(file_get_contents(database_path('seeders/metro-surplus-products.json')), true, flags: JSON_THROW_ON_ERROR);
        if (count($rows) !== 238) {
            throw new RuntimeException('Expected 238 Metro Surplus catalog products.');
        }
        foreach ($rows as $row) {
            $path = $row['image_path'];
            if (! preg_match('~^metro-catalog/[a-f0-9]{64}\.(jpg|jpeg|png|webp)$~', $path)) {
                throw new RuntimeException('Invalid catalog image path.');
            }
            $size = @getimagesize(public_path($path));
            if (! $size || $size[0] !== 640 || $size[1] !== 640) {
                throw new RuntimeException('Missing or incorrectly sized catalog image: '.$path);
            }
        }
        DB::transaction(function () use ($rows) {
            $shop = Shop::where('slug', 'metro-surplus')->lockForUpdate()->first();
            if (! $shop || ! $shop->is_active || $shop->owner?->email !== 'seller@farsell.test' || $shop->owner?->role !== UserRole::Seller) {
                throw new RuntimeException('Run the development DatabaseSeeder first; Metro Surplus must belong to the demo seller.');
            }
            $names = [
                'womens' => "Women's", 'mens' => "Men's", 'kids' => 'Kids', 'home' => 'Home',
                'beauty' => 'Beauty', 'sports' => 'Sports', 'gadgets' => 'Gadgets', 'surplus' => 'Surplus',
                'figures-models' => 'Figures and Models', 'desk-accessories' => 'Desk Accessories',
                'keyboards' => 'Keyboards', 'custom-keyboards' => 'Custom Keyboards', 'gaming-audio' => 'Gaming & Audio',
            ];
            $categories = [];
            foreach ($names as $slug => $name) {
                $categories[$slug] = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'icon' => mb_substr($name, 0, 1), 'sort_order' => 10])->id;
            }
            foreach ($rows as $row) {
                $existing = Product::where('slug', $row['slug'])->first();
                if ($existing) {
                    if ($existing->shop_id !== $shop->id) {
                        throw new RuntimeException('Catalog slug belongs to another shop: '.$row['slug']);
                    }

                    continue;
                }
                $product = $shop->products()->create([
                    'name' => $row['name'], 'slug' => $row['slug'], 'sku' => $row['sku'],
                    'description' => $row['description'], 'category_id' => $categories[$row['category']],
                    'price' => $row['price'], 'stock' => $row['stock'], 'image_path' => $row['image_path'],
                    'is_active' => true, 'has_variants' => false,
                ]);
                $product->categories()->sync(array_map(fn ($slug) => $categories[$slug], $row['categories']));
            }
        });
    }
}
