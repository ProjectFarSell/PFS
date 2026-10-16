<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SeedProductImages extends Command
{
    protected $signature = 'products:seed-images {--force : Overwrite existing product image assignments}';

    protected $description = 'Copies tracked sample images into storage and assigns them to database products by category';

    public function handle(): int
    {
        $sourceDir = database_path('data/product-images');
        $targetDir = storage_path('app/public/products');

        if (! File::isDirectory($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return Command::FAILURE;
        }

        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // Copy all source images into storage/app/public/products (ignoring hidden files like .gitkeep)
        $sourceFiles = array_filter(File::files($sourceDir), fn ($f) => ! str_starts_with($f->getFilename(), '.'));
        if (empty($sourceFiles)) {
            $this->info("No seed images found in {$sourceDir}. Skipping image seeding.");

            return Command::SUCCESS;
        }

        $copiedCount = 0;
        foreach ($sourceFiles as $file) {
            $dest = $targetDir.DIRECTORY_SEPARATOR.$file->getFilename();
            if (! File::exists($dest) || $this->option('force')) {
                File::copy($file->getPathname(), $dest);
                $copiedCount++;
            }
        }
        $this->info("Copied {$copiedCount} images into public storage.");

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
            'womens' => 'womens',
            'mens' => 'mens',
            'kids' => 'kids',
            'home' => 'home',
            'home-living' => 'home',
            'beauty' => 'beauty',
            'sports' => 'sports',
            'gadgets' => 'gadgets',
            'electronics' => 'gadgets',
            'surplus' => 'surplus',
        ];

        $products = Product::with('category')->get();
        if ($products->isEmpty()) {
            $this->info('No products found in database to update.');

            return Command::SUCCESS;
        }

        $updatedCount = 0;
        foreach ($products as $index => $product) {
            if ($product->image_path && ! $this->option('force') && Storage::disk('public')->exists($product->image_path)) {
                continue;
            }

            $catSlug = $product->category?->slug ?? 'surplus';
            $prefix = $categoryPrefixMap[$catSlug] ?? 'surplus';
            $available = $filesByCategory[$prefix] ?? $allFiles;

            // Pick a deterministic image based on product id
            $chosenImage = $available[$product->id % count($available)] ?? $allFiles[0];
            $imagePath = 'products/'.$chosenImage;

            $product->update(['image_path' => $imagePath]);

            // Also ensure ProductImage gallery records exist
            ProductImage::query()->where('product_id', $product->id)->delete();
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $imagePath,
                'alt_text' => $product->name,
                'sort_order' => 0,
            ]);

            // Add a secondary gallery image if available
            if (count($available) > 1) {
                $secondaryImage = $available[($product->id + 1) % count($available)];
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => 'products/'.$secondaryImage,
                    'alt_text' => $product->name.' - alternate view',
                    'sort_order' => 1,
                ]);
            }

            $updatedCount++;
        }

        $this->info("Successfully assigned pictures to {$updatedCount} products in the database.");

        return Command::SUCCESS;
    }
}
