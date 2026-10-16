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

    public function test_seed_images_command_handles_empty_source_gracefully(): void
    {
        $this->artisan('products:seed-images')
            ->assertSuccessful();
    }

    public function test_seed_images_command_copies_images_and_assigns_to_products(): void
    {
        $sourceDir = database_path('data/product-images');
        if (! File::isDirectory($sourceDir)) {
            File::makeDirectory($sourceDir, 0755, true);
        }
        $fixturePath = $sourceDir . DIRECTORY_SEPARATOR . 'test-sample.jpg';
        $destPath = storage_path('app/public/products' . DIRECTORY_SEPARATOR . 'test-sample.jpg');
        File::put($fixturePath, 'fake-jpg-content');

        try {
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
        } finally {
            if (File::exists($fixturePath)) {
                File::delete($fixturePath);
            }
            if (File::exists($destPath)) {
                File::delete($destPath);
            }
        }
    }

    public function test_backup_images_command_backs_up_storage_to_repo_folder(): void
    {
        $storageDir = storage_path('app/public/products');
        if (! File::isDirectory($storageDir)) {
            File::makeDirectory($storageDir, 0755, true);
        }
        $storageFile = $storageDir . DIRECTORY_SEPARATOR . 'test-backup-sample.jpg';
        $repoFile = database_path('data/product-images' . DIRECTORY_SEPARATOR . 'test-backup-sample.jpg');
        File::put($storageFile, 'fake-content');

        try {
            $this->artisan('products:backup-images')
                ->assertSuccessful();

            $this->assertTrue(File::exists($repoFile));
        } finally {
            if (File::exists($storageFile)) {
                File::delete($storageFile);
            }
            if (File::exists($repoFile)) {
                File::delete($repoFile);
            }
        }
    }
}
