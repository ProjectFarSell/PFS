<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerApplication;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CURATED_CATEGORIES = [
        'trading-cards' => ['name' => 'Trading Cards', 'icon' => 'cards', 'sort_order' => 1],
        'figures-models' => ['name' => 'Figures & Models', 'icon' => 'figure', 'sort_order' => 2],
        'custom-keyboards' => ['name' => 'Custom Keyboards', 'icon' => 'keyboard', 'sort_order' => 3],
        'gaming-audio' => ['name' => 'Gaming & Audio', 'icon' => 'gaming', 'sort_order' => 4],
        'anime-merch' => ['name' => 'Anime Merch', 'icon' => 'anime', 'sort_order' => 5],
        'desk-accessories' => ['name' => 'Desk Accessories', 'icon' => 'desk', 'sort_order' => 6],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $curatedIds = [];

            foreach (self::CURATED_CATEGORIES as $slug => $data) {
                $category = Category::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $data['name'], 'icon' => $data['icon'], 'sort_order' => $data['sort_order']]
                );
                $category->update([
                    'name' => $data['name'],
                    'icon' => $data['icon'],
                    'sort_order' => $data['sort_order'],
                ]);
                $curatedIds[$slug] = $category->id;
            }

            // Map old categories to curated ones for existing products
            $oldToNewMapping = [
                'womens' => $curatedIds['trading-cards'],
                'mens' => $curatedIds['trading-cards'],
                'kids' => $curatedIds['figures-models'],
                'home' => $curatedIds['desk-accessories'],
                'beauty' => $curatedIds['anime-merch'],
                'sports' => $curatedIds['gaming-audio'],
                'gadgets' => $curatedIds['custom-keyboards'],
                'surplus' => $curatedIds['gaming-audio'],
            ];

            foreach ($oldToNewMapping as $oldSlug => $newId) {
                $oldCategory = Category::where('slug', $oldSlug)->first();
                if ($oldCategory) {
                    Product::where('category_id', $oldCategory->id)->update(['category_id' => $newId]);
                }
            }

            // Fallback for any product pointing to a category not in curated categories
            Product::whereNotIn('category_id', array_values($curatedIds))
                ->update(['category_id' => $curatedIds['trading-cards']]);

            // Clean up seller application selected categories if they have obsolete IDs
            foreach (SellerApplication::whereNotNull('category_ids')->cursor() as $app) {
                if (is_array($app->category_ids)) {
                    $validIds = array_values(array_intersect($app->category_ids, array_values($curatedIds)));
                    $app->update(['category_ids' => $validIds]);
                }
            }

            // Delete obsolete categories
            Category::whereNotIn('slug', array_keys(self::CURATED_CATEGORIES))->delete();
        });
    }

    public function down(): void
    {
        // Re-seeding legacy categories on rollback
        $legacy = [
            ['name' => "Women's", 'slug' => 'womens', 'icon' => 'W', 'sort_order' => 1],
            ['name' => "Men's", 'slug' => 'mens', 'icon' => 'M', 'sort_order' => 2],
            ['name' => 'Kids', 'slug' => 'kids', 'icon' => 'K', 'sort_order' => 3],
            ['name' => 'Home', 'slug' => 'home', 'icon' => 'H', 'sort_order' => 4],
            ['name' => 'Beauty', 'slug' => 'beauty', 'icon' => 'B', 'sort_order' => 5],
            ['name' => 'Sports', 'slug' => 'sports', 'icon' => 'S', 'sort_order' => 6],
            ['name' => 'Gadgets', 'slug' => 'gadgets', 'icon' => 'G', 'sort_order' => 7],
            ['name' => 'Surplus', 'slug' => 'surplus', 'icon' => 'X', 'sort_order' => 8],
        ];

        foreach ($legacy as $row) {
            Category::query()->firstOrCreate(['slug' => $row['slug']], $row);
        }
    }
};
