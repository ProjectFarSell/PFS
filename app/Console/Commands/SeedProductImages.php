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

    protected $description = 'Syncs seed images to Cloudflare R2 and assigns available R2 images to database products';

    public function handle(): int
    {
        $sourceDir = database_path('data/product-images');
        File::ensureDirectoryExists($sourceDir);

        // 1. If local files exist in database/data/product-images, sync any new ones up to R2
        $sourceFiles = array_filter(File::files($sourceDir), fn ($f) => ! str_starts_with($f->getFilename(), '.'));
        if (! empty($sourceFiles)) {
            $uploadedCount = 0;
            foreach ($sourceFiles as $file) {
                $r2Path = 'products/' . $file->getFilename();
                if (! Storage::disk('r2')->exists($r2Path) || $this->option('force')) {
                    Storage::disk('r2')->put($r2Path, File::get($file->getPathname()), 'public');
                    $uploadedCount++;
                }
            }
            if ($uploadedCount > 0) {
                $this->info("Uploaded {$uploadedCount} new image(s) to Cloudflare R2.");
            }
        }

        // 2. Fetch all available images directly from Cloudflare R2
        $allFilenames = collect(Storage::disk('r2')->files('products'))
            ->filter(fn ($f) => ! str_starts_with(basename($f), '.'))
            ->map(fn ($f) => basename($f))
            ->values()
            ->all();

        if (empty($allFilenames)) {
            $this->warn('No product images found in Cloudflare R2 bucket. Skipping assignment.');

            return Command::SUCCESS;
        }

        $this->info('Found ' . count($allFilenames) . ' images in Cloudflare R2.');

        $products = Product::with('category')->get();
        if ($products->isEmpty()) {
            $this->info('No products found in database to update.');

            return Command::SUCCESS;
        }

        $updatedCount = 0;
        foreach ($products as $index => $product) {
            $currentPath = $product->image_path ? 'products/' . basename($product->image_path) : null;
            if ($currentPath && ! $this->option('force') && Storage::disk('r2')->exists($currentPath)) {
                continue;
            }

            // Pick a deterministic image based on product id
            $chosenImage = $allFilenames[$product->id % count($allFilenames)];
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
            if (count($allFilenames) > 1) {
                $secondaryImage = $allFilenames[($product->id + 1) % count($allFilenames)];
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
