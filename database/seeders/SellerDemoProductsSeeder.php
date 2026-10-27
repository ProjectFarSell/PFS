<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Database\Seeder;
use RuntimeException;

class SellerDemoProductsSeeder extends Seeder
{
    /** Seed a small, repeatable catalog for exercising seller variants and checkout. */
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'metro-surplus')->firstOrFail();

        $products = [
            [
                'slug' => 'demo-aga',
                'name' => 'aga',
                'category' => "Men's",
                'description' => 'Seller demo listing for trying variant selection, SKU display, and stock tracking.',
                'price' => 123.00,
                'stock' => 99,
                'image_path' => 'products/prod-24-ph-11134207-7rase-m6w744f3a959bd.jpg',
                'sku' => 'AGAP-BKXLS',
                'options' => ['Size' => 'XL', 'Color' => 'Black'],
            ],
            [
                'slug' => 'demo-borg',
                'name' => 'borg',
                'category' => 'Home',
                'description' => 'Seller demo listing for trying color and size options during checkout.',
                'price' => 140.00,
                'stock' => 100,
                'image_path' => 'products/EAsF1BaG8QWUeGtB7RCWfanVpPzTrG1InTAAgcNK.jpg',
                'sku' => 'BORQ-ORSXT',
                'options' => ['Size' => 'S', 'Color' => 'Orange'],
            ],
            [
                'slug' => 'demo-balls',
                'name' => 'balls',
                'category' => 'Surplus',
                'description' => 'Seller demo listing for tracing SKU, inventory, and a checkout order.',
                'price' => 200.00,
                'stock' => 131,
                'image_path' => 'products/fzXpEGUUVk6tqUC9vNgPdFhf7ksT6CCUtCzhFPIk.jpg',
                'sku' => 'BALR-BKSXU',
                'options' => ['Size' => 'S', 'Color' => 'Black'],
            ],
        ];

        foreach ($products as $data) {
            $existingVariant = ProductVariant::query()
                ->with('product')
                ->where('sku', $data['sku'])
                ->first();

            if ($existingVariant) {
                if ($existingVariant->product?->name !== $data['name'] || $existingVariant->options !== $data['options']) {
                    throw new RuntimeException("Demo SKU {$data['sku']} is already assigned to a different product or option set.");
                }

                // Reuse a matching seller listing and preserve its current inventory.
                continue;
            }

            $product = $shop->products()->firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id' => Category::query()->where('name', $data['category'])->value('id'),
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'stock' => $data['stock'],
                    'image_path' => $data['image_path'],
                    'has_variants' => true,
                    'is_active' => true,
                ]
            );

            if ($product->name !== $data['name']) {
                throw new RuntimeException("Demo slug {$data['slug']} is already assigned to a different product.");
            }

            ProductVariant::query()->firstOrCreate(
                ['sku' => $data['sku']],
                [
                    'product_id' => $product->id,
                    'option_name' => 'Size',
                    'option_value' => $data['options']['Size'],
                    'options' => $data['options'],
                    'stock' => $data['stock'],
                    'is_active' => true,
                ]
            );
        }
    }
}
