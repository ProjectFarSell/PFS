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

    public function test_seed_images_command_syncs_and_assigns_r2_images(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('products/sample-test.jpg', 'test-content', 'public');

        $category = Category::factory()->create();
        $shop = Shop::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'shop_id'     => $shop->id,
            'image_path'  => null,
        ]);

        $this->artisan('products:seed-images', ['--force' => true])
            ->assertSuccessful();

        $product->refresh();
        $this->assertNotNull($product->image_path);
        $this->assertStringContainsString('products/', $product->image_path);
    }

    public function test_backup_images_command_downloads_from_r2_to_repo_folder(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('products/backup-sample.jpg', 'fake-image-bytes');

        $this->artisan('products:backup-images')
            ->assertSuccessful();

        $repoFile = database_path('data/product-images/backup-sample.jpg');
        $this->assertTrue(File::exists($repoFile));

        if (File::exists($repoFile)) {
            File::delete($repoFile);
        }
    }
}
