<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_secondary_categories_filter_existing_seller_products(): void
    {
        $primary = Category::factory()->create();
        $secondary = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Collectible keyboard', 'category_id' => $primary->id, 'is_active' => true]);
        $product->shop->update(['is_active' => true]);
        $product->categories()->syncWithoutDetaching([$secondary->id]);
        $this->get(route('catalog.index', ['category' => $secondary->id]))->assertOk()->assertSee('Collectible keyboard');
        $this->assertSame($primary->id, $product->fresh()->category_id);
    }

    public function test_seller_category_changes_replace_primary_membership_and_preserve_secondary(): void
    {
        $old = Category::factory()->create();
        $new = Category::factory()->create();
        $secondary = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $old->id]);
        $product->categories()->syncWithoutDetaching([$secondary->id]);
        $product->update(['category_id' => $new->id]);
        $this->assertEqualsCanonicalizing([$new->id, $secondary->id], $product->categories()->pluck('categories.id')->all());
    }

    public function test_local_images_and_cloud_images_keep_separate_base_urls(): void
    {
        config(['filesystems.disks.r2.url' => 'https://images.example.test']);
        $local = new Product(['name' => 'Local item', 'image_path' => 'metro-catalog/example.jpg']);
        $cloud = new Product(['name' => 'Cloud item', 'image_path' => 'products/example.jpg']);
        $this->assertStringContainsString('/metro-catalog/example.jpg?size=640-v1', $local->imageUrl());
        $this->assertSame($local->imageUrl(), $local->galleryImages()->first()['url']);
        $this->assertSame('https://images.example.test/products/example.jpg', $cloud->imageUrl());
    }
}
