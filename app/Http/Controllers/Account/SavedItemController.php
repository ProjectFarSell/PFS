<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SavedItem;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SavedItemController extends Controller
{
    public function index(Request $request): View
    {
        $saved = $request->user()->savedItems()->with('product.shop')->latest()->paginate(16);

        // Preload the set of saved product IDs for the current page so that
        // card.blade.php does not fire a hasSavedProduct() query per card (N+1).
        $savedProductIds = $saved->pluck('product_id')->all();

        return view('account.saved.index', compact('saved', 'savedProductIds'));
    }

    public function store(Request $request, Product $product): Response
    {
        if (! $product->is_active || ! $product->shop?->is_active) {
            abort(404);
        }

        SavedItem::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $product->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['saved' => true]);
        }

        return back();
    }

    public function destroy(Request $request, Product $product): Response
    {
        $request->user()->savedItems()->where('product_id', $product->id)->delete();

        if ($request->expectsJson()) {
            return response()->json(['saved' => false]);
        }

        return back();
    }
}
