<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_images_command_copies_images_and_assigns_to_products(): void
    {
        $category = Category::factory()->create(['slug' => 'gadgets']);
        $shop = Shop::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'shop_id' => $shop->id,
            'image_path' => null,
        ]);

        $this->artisan('products:seed-images', ['--force' => true])
            ->assertSuccessful();

        $product->refresh();
        $this->assertNotNull($product->image_path);
        $this->assertTrue(Storage::disk('public')->exists($product->image_path));
        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'path' => $product->image_path,
        ]);
    }

    public function test_backup_images_command_backs_up_storage_to_repo_folder(): void
    {
        $this->artisan('products:backup-images')
            ->assertSuccessful();

        $this->assertTrue(File::isDirectory(database_path('data/product-images')));
    }
}
