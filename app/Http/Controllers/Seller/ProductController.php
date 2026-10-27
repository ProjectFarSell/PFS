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
use Illuminate\Validation\Rule;
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

        $product->load('variants');
        return view('seller.product-form', ['product' => $product, 'categories' => Category::orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $data = $this->validateProduct($request);
        $variants = $data['variants'] ?? [];
        unset($data['variants']);
        $hasVariants = (bool) ($data['has_variants'] ?? false);
        $data['has_variants'] = $hasVariants;
        if ($hasVariants) {
            $data['stock'] = 0;
            $data['sku'] = null;
        }
        $path = null;
        try {
            $product = DB::transaction(function () use ($request, $shop, $data, $variants, $hasVariants, &$path) {
                $lockedShop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedShop->is_active, 403);
                $path = $request->file('image')?->store('products', 'r2');
                if ($path === false) {
                    throw ValidationException::withMessages(['image' => 'Image could not be saved. Please try again.']);
                }

                $product = $lockedShop->products()->create([
                    ...$data, 'slug' => Str::slug($data['name']).'-'.Str::uuid(), 'image_path' => $path, 'is_flash' => false,
                ]);
                if (! $hasVariants && blank($product->sku)) {
                    $product->forceFill(['sku' => $this->generatedProductSku($product)])->save();
                }
                if ($hasVariants) $this->saveVariants($product, $variants);
                return $product;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('r2')->delete($path);
            }
            throw $e;
        }

        return to_route('seller.products.edit', $product)->with('status', 'Product created. '.($product->is_active ? 'It is now visible to buyers.' : 'It is saved as an unpublished draft.'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $shop = $this->owned($request, $product);
        $data = $this->validateProduct($request, $product);
        $variants = $data['variants'] ?? [];
        unset($data['variants']);
        $hasVariants = (bool) ($data['has_variants'] ?? false);
        $data['has_variants'] = $hasVariants;
        if ($hasVariants) {
            $data['stock'] = 0;
            $data['sku'] = null;
        }
        $request->validate(['version' => ['required', 'string', 'size:64']]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $shop, $product, $data, $variants, $hasVariants, &$path) {
                $lockedShop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedShop->is_active, 403);
                $product = $lockedShop->products()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                if (! hash_equals($product->editVersion(), $request->string('version')->toString())) {
                    throw ValidationException::withMessages(['version' => 'This listing or its stock changed. Reload the editor before saving; your changes were not applied.']);
                }
                if ($request->hasFile('image')) {
                    $path = $request->file('image')->store('products', 'r2');
                    if (! $path) {
                        throw ValidationException::withMessages(['image' => 'Image could not be saved. Please try again.']);
                    }
                    $oldPath = $product->image_path;
                    $product->image_path = $path;
                }
                // Preserve slug, shop ownership, order snapshots and reserved stock.
                $product->fill($data)->save();
                if ($hasVariants) $this->saveVariants($product, $variants);
                else {
                    $product->variants()->update(['is_active' => false]);
                    if (blank($product->sku)) {
                        $product->forceFill(['sku' => $this->generatedProductSku($product)])->save();
                    }
                }
                // Delete old R2 image after successful save
                if (isset($oldPath) && $oldPath) {
                    Storage::disk('r2')->delete($oldPath);
                }
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('r2')->delete($path);
            }
            throw $e;
        }

        return to_route('seller.products.edit', $product)->with('status', 'Listing updated. Existing order details have not changed.');
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $skuRules = ['nullable', 'string', 'min:8', 'max:12'];
        $skuRules[] = $product
            ? Rule::unique('products', 'sku')->ignore($product->id)
            : Rule::unique('products', 'sku');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => $skuRules,
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'has_variants' => ['nullable', 'boolean'],
            'variants' => ['required_if:has_variants,1', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['nullable', 'string', 'min:8', 'max:12'],
            'variants.*.options_text' => ['required_if:has_variants,1', 'string', 'max:500'],
            'variants.*.price_override' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'variants.*.stock' => ['required_if:has_variants,1', 'integer', 'min:0', 'max:1000000'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        unset($data['image']);

        if (filled($data['sku'] ?? null) && \App\Models\ProductVariant::where('sku', $data['sku'])->exists()) {
            throw ValidationException::withMessages(['sku' => 'That SKU is already in use by a product variant. Enter a unique SKU.']);
        }

        return $data;
    }

    private function generatedProductSku(Product $product): string
    {
        $base = $this->productSkuPrefix($product).'-'.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT);
        $sku = $base;
        while (Product::where('sku', $sku)->where('id', '!=', $product->id)->exists() || \App\Models\ProductVariant::where('sku', $sku)->exists()) {
            $sku = $base.'-'.Str::upper(Str::random(2));
        }

        return $sku;
    }

    private function productSkuPrefix(Product $product): string
    {
        $words = preg_split('/[^A-Z0-9]+/', Str::upper($product->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $code = count($words) > 1
            ? implode('', array_map(fn ($word) => $word[0], array_slice($words, 0, 3)))
            : substr($words[0] ?? 'PRD', 0, 3);

        return str_pad(substr($code ?: 'PRD', 0, 3), 3, 'X');
    }

    private function generatedVariantSku(Product $product, array $options, int $variantId): string
    {
        $ordered = collect($options)->sortBy(fn ($value, $name) => match (mb_strtolower($name)) {
            'color' => 0,
            'size' => 1,
            default => 2,
        });
        $optionCode = $ordered->take(2)->map(fn ($value, $name) => $this->optionSkuCode((string) $name, (string) $value))->join('');
        $productIdCode = strtoupper(base_convert((string) $product->id, 10, 36));
        $variantIdCode = strtoupper(base_convert((string) $variantId, 10, 36));
        $nameCode = $this->productSkuPrefix($product);
        $maxNameLength = max(1, 12 - strlen($productIdCode) - 1 - strlen($optionCode) - strlen($variantIdCode));

        return substr($nameCode, 0, $maxNameLength).$productIdCode.'-'.$optionCode.$variantIdCode;
    }

    private function optionSkuCode(string $name, string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $codes = [
            'black' => 'BK', 'white' => 'WH', 'black/white' => 'BW', 'black and white' => 'BW',
            'red' => 'RD', 'blue' => 'BL', 'green' => 'GN', 'yellow' => 'YL', 'pink' => 'PK',
            'purple' => 'PU', 'gray' => 'GY', 'grey' => 'GY', 'brown' => 'BN', 'orange' => 'OR',
            'xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL', 'xxl' => '2X', 'xxxl' => '3X',
        ];
        if (mb_strtolower($name) === 'size' && preg_match('/\d+(?:\.\d+)?/', $normalized, $match)) {
            return substr(preg_replace('/\D/', '', $match[0]), 0, 3) ?: 'SZ';
        }

        $code = $codes[$normalized] ?? strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $value) ?: 'XX', 0, 2));

        return str_pad(substr($code, 0, 2), 2, 'X');
    }

    private function saveVariants(Product $product, array $rows): void
    {
        $savedIds = [];
        $combinations = [];
        $dimensions = null;
        foreach ($rows as $row) {
            $options = [];
            foreach (explode(',', (string) ($row['options_text'] ?? '')) as $pair) {
                [$name, $value] = array_pad(explode(':', $pair, 2), 2, '');
                $name = trim($name);
                $value = trim($value);
                if ($name !== '' && $value !== '') $options[Str::title($name)] = $value;
            }
            if (! $options) {
                throw ValidationException::withMessages(['variants' => 'Enter options as Name: Value, such as Size: M, Color: Red.']);
            }
            $normalized = collect($options)->mapWithKeys(fn ($value, $name) => [mb_strtolower($name) => mb_strtolower($value)])->sortKeys()->all();
            $currentDimensions = array_keys($normalized);
            if ($dimensions !== null && $dimensions !== $currentDimensions) {
                throw ValidationException::withMessages(['variants' => 'Use the same option names for every combination, such as Size and Color.']);
            }
            $dimensions = $currentDimensions;
            $combination = json_encode($normalized, JSON_THROW_ON_ERROR);
            if (isset($combinations[$combination])) {
                throw ValidationException::withMessages(['variants' => 'Each option combination must be unique.']);
            }
            $combinations[$combination] = true;
            if (filled($row['id'] ?? null)) {
                $variant = $product->variants()->whereKey($row['id'])->firstOrFail();
            } else {
                $variant = $product->variants()->make();
            }
            $sku = trim((string) ($row['sku'] ?? '')) ?: $variant->sku;
            if (blank($sku)) {
                $variant->options = $options;
                $variant->option_name = array_key_first($options);
                $variant->option_value = reset($options);
                $variant->sku = 'TMP'.Str::upper(Str::random(9));
                $variant->save();
                $sku = $this->generatedVariantSku($product, $options, (int) $variant->id);
            }
            if (Product::where('sku', $sku)->exists() || \App\Models\ProductVariant::where('sku', $sku)->where('id', '!=', $variant->id ?? 0)->exists()) {
                throw ValidationException::withMessages(['variants' => "SKU {$sku} is already in use. Each product or variant must have a unique SKU."]);
            }
            $variant->sku = $sku;
            $variant->options = $options;
            $variant->option_name = array_key_first($options);
            $variant->option_value = reset($options);
            $variant->price_override = $row['price_override'] ?? null;
            $variant->stock = (int) $row['stock'];
            $variant->is_active = (bool) ($row['is_active'] ?? true);
            $variant->save();
            $savedIds[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $savedIds ?: [0])->update(['is_active' => false]);
        $product->forceFill(['stock' => $product->variants()->where('is_active', true)->sum('stock')])->save();
    }
}
