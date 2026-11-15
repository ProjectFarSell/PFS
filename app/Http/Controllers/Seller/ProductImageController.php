<?php

namespace App\Http\Controllers\Seller;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Products\ProductGallery;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    private function owned(Request $request, Product $product): void
    {
        abort_unless($request->user()->role === UserRole::Seller && $product->shop()->where('user_id', $request->user()->id)->where('is_active', true)->exists(), 404);
    }

    public function store(Request $request, Product $product, ProductGallery $gallery)
    {
        $this->owned($request, $product);
        $data = $request->validate(['images' => ['required', 'array', 'min:1', 'max:5'], 'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
        $gallery->upload($product, $data['images']);

        return to_route('seller.products.edit', $product)->with('status', 'Product photos uploaded.');
    }

    public function primary(Request $request, Product $product, ProductImage $image, ProductGallery $gallery)
    {
        $this->owned($request, $product);
        $gallery->primary($product, $image);

        return to_route('seller.products.edit', $product)->with('status', 'Primary product photo updated.');
    }

    public function destroy(Request $request, Product $product, ProductImage $image, ProductGallery $gallery)
    {
        $this->owned($request, $product);
        $gallery->remove($product, $image);

        return to_route('seller.products.edit', $product)->with('status', 'Product photo removed.');
    }

    public function destroyPrimary(Request $request, Product $product, ProductGallery $gallery)
    {
        $this->owned($request, $product);
        $gallery->remove($product);

        return to_route('seller.products.edit', $product)->with('status', 'Primary product photo removed.');
    }
}
