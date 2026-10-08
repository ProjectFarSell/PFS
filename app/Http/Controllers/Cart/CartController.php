<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(): View
    {
        Cart::clearBuyNow();

        return view('cart.index', [
            'lines' => Cart::hydrated(),
            'subtotal' => Cart::subtotal(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $product, $variant, $stock] = $this->purchaseLine($request);
        $lineKey = Cart::lineKey($product->id, $variant?->id);
        $requested = (Cart::lines()[$lineKey] ?? 0) + ($data['qty'] ?? 1);

        if ($requested > $stock) {
            throw ValidationException::withMessages([
                'qty' => 'Only '.$stock.' units are currently available for this option.',
            ]);
        }

        Cart::add($product->id, $data['qty'] ?? 1, $variant?->id);

        return back()->with('status', $product->name.' added to cart.');
    }

    public function buyNow(Request $request): RedirectResponse
    {
        [$data, $product, $variant, $stock] = $this->purchaseLine($request);
        $qty = $data['qty'] ?? 1;
        if ($qty > $stock) {
            throw ValidationException::withMessages(['qty' => 'Only '.$stock.' units are currently available for this option.']);
        }

        Cart::startBuyNow($product->id, $qty, $variant?->id);

        return redirect()->route('checkout.create');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        abort_if($data['qty'] > 0 && (! $product->is_active || ! $product->shop?->is_active), 404);

        $variantId = $request->validate(['variant_id' => ['nullable', 'integer', 'exists:product_variants,id']])['variant_id'] ?? null;
        $variant = $variantId ? ProductVariant::query()->where('product_id', $product->id)->findOrFail($variantId) : null;
        if ($data['qty'] > 0 && $product->has_variants && ! $variant) {
            abort(404);
        }
        abort_if($data['qty'] > 0 && $variant && ! $product->has_variants, 404);
        abort_if($data['qty'] > 0 && $variant && ! $variant->is_active, 404);
        if ($data['qty'] > ($variant?->stock ?? $product->stock)) {
            throw ValidationException::withMessages([
                'qty' => 'Only '.($variant?->stock ?? $product->stock).' units are currently available for this option.',
            ]);
        }

        Cart::update($product->id, $data['qty'], $variant?->id);

        return back();
    }

    /** @return array{array<string, mixed>, Product, ?ProductVariant, int} */
    private function purchaseLine(Request $request): array
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);
        $product = Product::query()->visible()->findOrFail($data['product_id']);
        $variant = isset($data['variant_id'])
            ? ProductVariant::query()->where('product_id', $product->id)->where('is_active', true)->findOrFail($data['variant_id'])
            : null;

        if ($variant && ! $product->has_variants) {
            throw ValidationException::withMessages(['variant_id' => 'This product no longer uses options. Reload the product page.']);
        }
        if ($product->has_variants && ! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'Choose an available option before continuing.']);
        }

        return [$data, $product, $variant, (int) ($variant?->stock ?? $product->stock)];
    }
}
