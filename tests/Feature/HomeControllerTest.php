<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SavedItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_popular_products_are_ranked_by_saves_then_id_and_limited_to_four(): void
    {
        $products = Product::factory()->count(5)->sequence(
            ['created_at' => '2026-01-01 00:00:00'],
            ['created_at' => '2026-01-05 00:00:00'],
            ['created_at' => '2026-01-04 00:00:00'],
            ['created_at' => '2026-01-03 00:00:00'],
            ['created_at' => '2026-01-02 00:00:00'],
        )->create(['is_flash' => false]);
        $buyers = User::factory()->count(3)->create();
        foreach ([3, 1, 2, 2, 1] as $index => $saveCount) {
            foreach ($buyers->take($saveCount) as $buyer) {
                SavedItem::create(['user_id' => $buyer->id, 'product_id' => $products[$index]->id]);
            }
        }

        $response = $this->get('/');

        $response->assertOk()->assertViewIs('home');
        $popular = $response->viewData('popular');
        $this->assertSame([$products[0]->id, $products[3]->id, $products[2]->id, $products[4]->id], $popular->modelKeys());
        $this->assertSame([3, 2, 2, 1], $popular->pluck('saved_items_count')->all());
        foreach ($popular as $product) {
            $this->assertTrue($product->relationLoaded('shop'));
            $this->assertTrue($product->relationLoaded('category'));
        }
    }

    public function test_popular_products_exclude_hidden_products_and_products_without_saves(): void
    {
        $buyer = User::factory()->create();
        $saved = Product::factory()->create(['is_flash' => false]);
        $draft = Product::factory()->create(['is_active' => false, 'is_flash' => false]);
        $hidden = Product::factory()->for(Shop::factory()->state(['is_active' => false]))
            ->create(['is_flash' => false]);
        Product::factory()->create(['is_flash' => false]);
        foreach ([$saved, $draft, $hidden] as $product) {
            SavedItem::create(['user_id' => $buyer->id, 'product_id' => $product->id]);
        }

        $response = $this->get('/');

        $response->assertOk();
        $this->assertSame([$saved->id], $response->viewData('popular')->modelKeys());
    }

    public function test_popular_products_remain_empty_without_saves(): void
    {
        Product::factory()->create(['is_flash' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $this->assertCount(0, $response->viewData('popular'));
    }

    public function test_category_counts_include_only_visible_products_and_keep_empty_categories(): void
    {
        $category = Category::factory()->create(['sort_order' => 1]);
        $emptyCategory = Category::factory()->create(['sort_order' => 0]);
        Product::factory()->count(2)->for($category)->create(['is_flash' => false]);
        Product::factory()->for($category)->create(['is_active' => false, 'is_flash' => false]);
        Product::factory()->for($category)->for(Shop::factory()->state(['is_active' => false]))
            ->create(['is_flash' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $categories = $response->viewData('categories');
        $this->assertSame([$emptyCategory->id, $category->id], $categories->modelKeys());
        $this->assertSame([0, 2], $categories->pluck('products_count')->all());
    }

    public function test_flash_stock_uses_eager_loaded_active_variants_without_extra_queries(): void
    {
        $product = Product::factory()->create(['is_flash' => true, 'has_variants' => true, 'stock' => 99]);
        ProductVariant::factory()->for($product)->create(['stock' => 3]);
        ProductVariant::factory()->for($product)->create(['stock' => 4]);
        ProductVariant::factory()->for($product)->create(['stock' => 50, 'is_active' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $flash = $response->viewData('flash');
        $this->assertSame([$product->id], $flash->modelKeys());
        $this->assertTrue($flash->first()->relationLoaded('variants'));
        DB::enableQueryLog();
        try {
            $this->assertSame(7, $flash->first()->availableStock());
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
