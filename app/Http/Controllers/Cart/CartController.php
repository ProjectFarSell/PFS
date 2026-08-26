<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(): View
    {
        return view('cart.index', [
            'lines' => Cart::hydrated(),
            'subtotal' => Cart::subtotal(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::query()->where('is_active', true)->findOrFail($data['product_id']);

        Cart::add($product->id, $data['qty'] ?? 1);

        return back()->with('status', $product->name.' added to cart.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        Cart::update($product->id, $data['qty']);

        return back();
    }
}
