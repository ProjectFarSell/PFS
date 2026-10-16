<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SeedProductImages extends Command
{
    protected $signature = 'products:seed-images {--force : Overwrite existing product image assignments}';

    protected $description = 'Uploads tracked seed images to Cloudflare R2 and assigns them to database products by category';

    public function handle(): int
    {
        $sourceDir = database_path('data/product-images');

        if (! File::isDirectory($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return Command::FAILURE;
        }

        // Collect all image files, ignoring hidden files like .gitkeep
        $sourceFiles = array_filter(File::files($sourceDir), fn ($f) => ! str_starts_with($f->getFilename(), '.'));
        if (empty($sourceFiles)) {
            $this->info("No seed images found in {$sourceDir}. Skipping image seeding.");

            return Command::SUCCESS;
        }

        // Upload images to R2
        $uploadedCount = 0;
        foreach ($sourceFiles as $file) {
            $r2Path = 'products/' . $file->getFilename();
            if (! Storage::disk('r2')->exists($r2Path) || $this->option('force')) {
                Storage::disk('r2')->put($r2Path, File::get($file->getPathname()), 'public');
                $uploadedCount++;
            }
        }
        $this->info("Uploaded {$uploadedCount} image(s) to Cloudflare R2.");

        // Group available files by category prefix (e.g. gadgets-1.jpg, womens-2.jpg)
        $filesByCategory = [];
        $allFiles = [];
        foreach ($sourceFiles as $file) {
            $filename = $file->getFilename();
            $allFiles[] = $filename;
            $prefix = explode('-', $filename)[0] ?? 'surplus';
            $filesByCategory[$prefix][] = $filename;
        }

        // Category slug mapping to image prefix
        $categoryPrefixMap = [
            'womens'     => 'womens',
            'mens'       => 'mens',
            'kids'       => 'kids',
            'home'       => 'home',
            'home-living'=> 'home',
            'beauty'     => 'beauty',
            'sports'     => 'sports',
            'gadgets'    => 'gadgets',
            'electronics'=> 'gadgets',
            'surplus'    => 'surplus',
        ];

        $products = Product::with('category')->get();
        if ($products->isEmpty()) {
            $this->info('No products found in database to update.');

            return Command::SUCCESS;
        }

        $updatedCount = 0;
        foreach ($products as $product) {
            $imagePath = $product->image_path ? 'products/' . basename($product->image_path) : null;
            if ($imagePath && ! $this->option('force') && Storage::disk('r2')->exists($imagePath)) {
                continue;
            }

            $catSlug  = $product->category?->slug ?? 'surplus';
            $prefix   = $categoryPrefixMap[$catSlug] ?? 'surplus';
            $available = $filesByCategory[$prefix] ?? $allFiles;

            // Pick a deterministic image based on product id
            $chosenImage = $available[$product->id % count($available)] ?? $allFiles[0];
            $r2Path = 'products/' . $chosenImage;

            $product->update(['image_path' => $r2Path]);

            // Ensure ProductImage gallery records exist
            ProductImage::query()->where('product_id', $product->id)->delete();
            ProductImage::create([
                'product_id' => $product->id,
                'path'       => $r2Path,
                'alt_text'   => $product->name,
                'sort_order' => 0,
            ]);

            // Add a secondary gallery image if available
            if (count($available) > 1) {
                $secondaryImage = $available[($product->id + 1) % count($available)];
                ProductImage::create([
                    'product_id' => $product->id,
                    'path'       => 'products/' . $secondaryImage,
                    'alt_text'   => $product->name . ' - alternate view',
                    'sort_order' => 1,
                ]);
            }

            $updatedCount++;
        }

        $this->info("Successfully assigned pictures to {$updatedCount} products in the database.");

        return Command::SUCCESS;
    }
}
