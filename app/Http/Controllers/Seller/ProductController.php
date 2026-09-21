<?php

namespace App\Http\Controllers\Seller;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    private function shop(Request $request): Shop
    {
        // No admin bypass and no shop/user IDs accepted from input.
        abort_unless($request->user()->role === UserRole::Seller, 403);
        $shop = $request->user()->shop()->first();
        abort_unless($shop && $shop->is_active, 403, 'An active, approved shop is required to manage listings.');

        return $shop;
    }

    private function owned(Request $request, Product $product): Shop
    {
        $shop = $this->shop($request);
        abort_unless($product->shop_id === $shop->id, 404);

        return $shop;
    }

    public function create(Request $request): View
    {
        $this->shop($request);

        return view('seller.product-form', ['product' => new Product, 'categories' => Category::orderBy('sort_order')->get()]);
    }

    public function edit(Request $request, Product $product): View
    {
        $this->owned($request, $product);

        if ($request->session()->get('errors')?->has('version')) {
            // Never pair stale submitted stock with a fresh version after a conflict.
            $request->session()->forget('_old_input');
        }

        return view('seller.product-form', ['product' => $product, 'categories' => Category::orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $data = $this->validateProduct($request);
        $path = null;
        try {
            $product = DB::transaction(function () use ($request, $shop, $data, &$path) {
                $lockedShop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedShop->is_active, 403);
                $path = $request->file('image')?->store('products', 'public');
                if ($path === false) {
                    throw ValidationException::withMessages(['image' => 'Image could not be saved. Please try again.']);
                }

                return $lockedShop->products()->create([
                    ...$data, 'slug' => Str::slug($data['name']).'-'.Str::uuid(), 'image_path' => $path, 'is_flash' => false,
                ]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return to_route('seller.products.edit', $product)->with('status', 'Product created. '.($product->is_active ? 'It is now visible to buyers.' : 'It is saved as an unpublished draft.'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $shop = $this->owned($request, $product);
        $data = $this->validateProduct($request);
        $request->validate(['version' => ['required', 'string', 'size:64']]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $shop, $product, $data, &$path) {
                $lockedShop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedShop->is_active, 403);
                $product = $lockedShop->products()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                if (! hash_equals($product->editVersion(), $request->string('version')->toString())) {
                    throw ValidationException::withMessages(['version' => 'This listing or its stock changed. Reload the editor before saving; your changes were not applied.']);
                }
                if ($request->hasFile('image')) {
                    $path = $request->file('image')->store('products', 'public');
                    if (! $path) {
                        throw ValidationException::withMessages(['image' => 'Image could not be saved. Please try again.']);
                    }
                    $product->image_path = $path;
                }
                // Preserve slug, shop ownership, order snapshots, variants and reserved stock.
                $product->fill($data)->save();
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return to_route('seller.products.edit', $product)->with('status', 'Listing updated. Existing order details have not changed.');
    }

    private function validateProduct(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        unset($data['image']);

        return $data;
    }
}
