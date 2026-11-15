<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Services\Security\SafeImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductGallery
{
    public function upload(Product $product, array $files): void
    {
        $paths = [];
        try {
            foreach ($files as $file) {
                $paths[] = app(SafeImage::class)->store($file, 'products', 'r2', 'images');
            }
            DB::transaction(function () use ($product, $paths) {
                Shop::whereKey($product->shop_id)->lockForUpdate()->firstOrFail();
                $row = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $this->normalize($row);
                if ($row->images()->count() + count($paths) > 5) {
                    throw ValidationException::withMessages(['images' => 'A product can have up to five photos. Remove a photo before uploading more.']);
                }
                $sort = (int) $row->images()->max('sort_order');
                foreach ($paths as $path) {
                    $row->images()->create(['path' => $path, 'alt_text' => $row->name, 'sort_order' => ++$sort]);
                    if (! $row->image_path) {
                        $row->image_path = $path;
                    }
                }
                $row->save();
            }, 3);
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('r2')->delete($path);
            }
            throw $exception;
        }
    }

    public function primary(Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($product, $image) {
            Shop::whereKey($product->shop_id)->lockForUpdate()->firstOrFail();
            $row = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $owned = $row->images()->whereKey($image->id)->firstOrFail();
            $row->forceFill(['image_path' => $owned->path])->save();
        }, 3);
    }

    public function remove(Product $product, ?ProductImage $image = null): void
    {
        DB::transaction(function () use ($product, $image) {
            Shop::whereKey($product->shop_id)->lockForUpdate()->firstOrFail();
            $row = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($image) {
                $owned = $row->images()->whereKey($image->id)->firstOrFail();
                $path = $owned->path;
                $owned->delete();
            } else {
                $path = $row->image_path;
                $row->images()->where('path', $path)->delete();
            }
            if ($row->image_path === $path) {
                $row->forceFill(['image_path' => $row->images()->orderBy('sort_order')->orderBy('id')->value('path')])->save();
            }
            $this->deleteAfterCommit($path);
        }, 3);
    }

    /** Existing single-photo listing form remains compatible with gallery rows. */
    public function replacePrimaryLocked(Product $product, string $newPath): void
    {
        $oldPath = $product->image_path;
        if ($oldPath) {
            $product->images()->where('path', $oldPath)->delete();
        }
        if ($product->images()->count() >= 5) {
            throw ValidationException::withMessages(['image' => 'A product can have up to five photos. Remove a photo first.']);
        }
        $product->images()->create(['path' => $newPath, 'alt_text' => $product->name, 'sort_order' => 0]);
        $product->image_path = $newPath;
        $this->deleteAfterCommit($oldPath);
    }

    private function normalize(Product $product): void
    {
        if ($product->image_path) {
            $product->images()->firstOrCreate(['path' => $product->image_path], ['alt_text' => $product->name, 'sort_order' => 0]);
        }
    }

    private function deleteAfterCommit(?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'products/') || str_contains($path, '..')) {
            return;
        }
        DB::afterCommit(function () use ($path) {
            if (! Product::where('image_path', $path)->exists() && ! ProductImage::where('path', $path)->exists()) {
                try {
                    Storage::disk('r2')->delete($path);
                } catch (\Throwable) { /* Do not invalidate a committed product because orphan cleanup failed. */
                }
            }
        });
    }
}
